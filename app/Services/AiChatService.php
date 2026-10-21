<?php

namespace App\Services;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\User;
use App\Services\Ai\AiBlockedException;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\AiSystemPromptBuilder;
use App\Services\Ai\FakeChatProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Support\AiText;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiChatService
{
    // 모델에 다시 보내는 대화 이력의 최대 메시지 수(오래된 것부터 버린다).
    private const HISTORY_LIMIT = 30;

    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly UploadService $uploadService,
        private readonly AdminAuditLogService $auditLog,
        private readonly AiUsageService $usage,
        private readonly AiProposalService $proposals,
        private readonly AiSystemPromptBuilder $promptBuilder,
    ) {}

    /**
     * @return array<string, AiProviderContract>
     */
    private function providers(): array
    {
        $providers = [
            'openai' => new OpenAiProvider($this->siteSettings),
            'gemini' => new GeminiProvider($this->siteSettings),
        ];

        // 개발용 가짜 제공자 - local 환경에서만 존재한다(운영·시험에서는 목록에도 호출에도 나오지 않는다).
        if (app()->environment('local')) {
            $providers['fake'] = new FakeChatProvider;
        }

        return $providers;
    }

    // API 키가 설정된 제공자만 반환한다 — 퀵메뉴 위젯은 이 목록이 비어 있으면 아예 표시되지 않는다.
    public function availableProviders(): array
    {
        $available = [];

        foreach ($this->providers() as $key => $provider) {
            if ($provider->isConfigured()) {
                $available[$key] = $provider->label();
            }
        }

        return $available;
    }

    public function provider(string $key): AiProviderContract
    {
        return $this->providers()[$key] ?? throw new InvalidArgumentException("Unknown AI provider: {$key}");
    }

    // 도구 호출 사용 여부 - 제공자가 지원하고 사이트 설정(기본 켬)에서 꺼 두지 않았을 때만. 꺼지면 도구·카드 없이
    // 일반 대화로 내려간다(모델을 바꿨더니 도구 호출이 안 될 때 기능 전체가 멈추지 않게 하는 폴백).
    public function toolsEnabled(AiProviderContract $provider): bool
    {
        return $provider->supportsTools() && $this->siteSettings->get('ai_tools_enabled', '1') !== '0';
    }

    /**
     * @throws \App\Services\Ai\AiLimitExceededException 오늘 한도 초과(제공자는 호출하지 않았다)
     * @throws \App\Services\Ai\AiBusyException 같은 관리자의 이전 요청이 처리 중
     */
    public function sendMessage(User $admin, ?int $conversationId, string $providerKey, string $message, ?string $screenPath = null): AiChatMessage
    {
        $provider = $this->requireConfigured($providerKey);
        // 남의 대화면 여기서 바로 실패한다(비용이 들기 전에).
        $conversation = $conversationId !== null ? $this->ownedConversation($admin, $conversationId) : null;

        // 제공자 호출 "직전에" 한도를 선점한다(먼저 기록 → 센 뒤 초과면 되돌림). 초과면 예외 - 대화·메시지도 만들지 않는다.
        $reservation = $this->usage->reserve($admin, $providerKey, AiUsageService::KIND_CHAT);

        try {
            $conversation ??= $this->createConversation($admin, $providerKey, $message);
            $conversation->messages()->create(['role' => AiChatMessage::ROLE_USER, 'content' => $message]);

            $toolsOn = $this->toolsEnabled($provider);
            $cards = [];
            $blockedCode = null;

            try {
                $result = $provider->chatWithTools(
                    $this->promptBuilder->build($admin, $screenPath, $toolsOn),
                    $this->historyFor($conversation),
                    $toolsOn ? $this->proposals->declarations($admin) : [],
                );
                $reply = trim($result->text);
                $cards = $toolsOn ? $this->proposals->normalize($admin, $result->toolCalls) : [];
                $success = true;
            } catch (AiBlockedException $e) {
                // 정책상 차단(안전·저작권 유사 등) - 이유별 안내를 그대로 보여 주고 일일 한도는 소모하지 않는다(재시도 없음).
                $reply = $e->getMessage();
                $success = false;
                $blockedCode = $e->reasonCode;
                $reservation->refund();
            } catch (Throwable) {
                // 빈 후보·잘못된 도구 인자·HTTP 오류 모두 재시도 없이 일반 오류 문구(재시도는 곧 비용).
                $reply = __('AI 응답을 받아오지 못했습니다. 잠시 후 다시 시도해주세요.');
                $success = false;
            }

            if ($success && $reply === '') {
                $reply = $this->synthesizeReply($cards);
            }

            $this->auditLog->recordAiUsage($admin, $providerKey, 'chat', $success, $blockedCode);

            $assistant = $conversation->messages()->create(['role' => AiChatMessage::ROLE_ASSISTANT, 'content' => $reply]);
            $this->proposals->persist($assistant, $cards);

            return $assistant->load('proposals');
        } finally {
            $reservation->release();
        }
    }

    // 생성된 이미지는 여기서 바로 폼에 반영되지 않는다 — 채팅창에 미리보기 메시지로만 남고,
    // 관리자가 "이 이미지 사용하기"로 확정해야만(Livewire 위젯 쪽) fill-form-field가 발생한다.
    public function generateImage(User $admin, ?int $conversationId, string $providerKey, string $prompt): AiChatMessage
    {
        $provider = $this->requireConfigured($providerKey);
        $conversation = $conversationId !== null ? $this->ownedConversation($admin, $conversationId) : null;

        $reservation = $this->usage->reserve($admin, $providerKey, AiUsageService::KIND_IMAGE);

        try {
            $conversation ??= $this->createConversation($admin, $providerKey, $prompt);
            $conversation->messages()->create(['role' => AiChatMessage::ROLE_USER, 'content' => $prompt]);

            $blockedCode = null;

            try {
                $storedPath = $this->storeGeneratedImage($provider, AiText::singleLine($prompt, 1000));
                $content = null;
                $success = true;
            } catch (AiBlockedException $e) {
                // 정책상 차단(예: IMAGE_RECITATION) - 이유별 안내를 대화에 남기고 이미지 한도는 소모하지 않는다.
                $storedPath = null;
                $content = $e->getMessage();
                $success = false;
                $blockedCode = $e->reasonCode;
                $reservation->refund();
            } catch (Throwable) {
                // 5xx·타임아웃·연결 오류·이미지 없는 STOP 응답은 비용이 들었을 수 있어 한도에서 되돌리지 않는다(소모, java-cms 와 같은 기준).
                $storedPath = null;
                $content = __('이미지 생성에 실패했습니다. 잠시 후 다시 시도해주세요.');
                $success = false;
            }

            $this->auditLog->recordAiUsage($admin, $providerKey, 'image', $success, $blockedCode);

            return $conversation->messages()->create([
                'role' => AiChatMessage::ROLE_ASSISTANT,
                'content' => $content,
                'image_path' => $storedPath,
            ]);
        } finally {
            $reservation->release();
        }
    }

    /**
     * 행동 카드의 "이미지 만들고 열기": 소유자·만료·상태를 확인하고 제안에 이미지 1장을 만들어 붙인다(이미지 한도 적용).
     * 이미 이미지가 있으면 다시 만들지 않는다. 제목·날짜 등은 제안 생성 때 이미 검증되어 있다.
     *
     * @throws RuntimeException 제안을 찾을 수 없음/만료/이미지 생성 실패
     */
    public function generateProposalImage(User $admin, int $proposalId): AiChatProposal
    {
        $proposal = $this->proposals->resolveOwned($admin, $proposalId);

        if ($proposal === null) {
            throw new RuntimeException(__('제안을 찾을 수 없거나 만료되었습니다. 다시 요청해주세요.'));
        }

        if (filled($proposal->image_path) || blank($proposal->payload['image_prompt'] ?? null)) {
            return $proposal;
        }

        $providerKey = (string) $proposal->message->conversation->provider;
        $provider = $this->requireConfigured($providerKey);

        if (! $provider->supportsImageGeneration()) {
            throw new RuntimeException(__('이 AI 제공자는 이미지 생성을 지원하지 않습니다.'));
        }

        $reservation = $this->usage->reserve($admin, $providerKey, AiUsageService::KIND_IMAGE);

        try {
            try {
                $storedPath = $this->storeGeneratedImage($provider, AiText::singleLine($proposal->payload['image_prompt'], 500));
                $this->auditLog->recordAiUsage($admin, $providerKey, 'image', true);
            } catch (AiBlockedException $e) {
                // 정책상 차단 - 이유별 안내를 관리자에게 그대로 보여 주고(제안은 그대로, 이미지 없이 열 수 있다) 이미지 한도는 소모하지 않는다.
                $reservation->refund();
                $this->auditLog->recordAiUsage($admin, $providerKey, 'image', false, $e->reasonCode);

                throw new RuntimeException($e->getMessage());
            } catch (Throwable) {
                $this->auditLog->recordAiUsage($admin, $providerKey, 'image', false);

                throw new RuntimeException(__('이미지 생성에 실패했습니다. 잠시 후 다시 시도해주세요.'));
            }

            // 원자적 상태 전이 - 다른 탭이 먼저 끝냈으면 우리가 만든 파일은 지우고 그쪽 결과를 쓴다.
            $updated = AiChatProposal::query()
                ->whereKey($proposal->id)
                ->where('status', AiChatProposal::STATUS_PROPOSED)
                ->whereNull('image_path')
                ->update(['status' => AiChatProposal::STATUS_IMAGE_READY, 'image_path' => $storedPath]);

            if ($updated === 0) {
                $this->uploadService->delete($storedPath);
            }
        } finally {
            $reservation->release();
        }

        return AiChatProposal::query()->with('message.conversation')->findOrFail($proposal->id);
    }

    // 제공자에게 이미지를 만들게 하고 uploads 에 저장한 상대 경로를 돌려준다(임시 파일은 지운다).
    private function storeGeneratedImage(AiProviderContract $provider, string $prompt): string
    {
        $tempPath = $provider->generateImage($prompt);

        try {
            // 확장자는 제공자가 돌려준 임시 파일의 확장자를 따른다(OpenAI 는 png, Gemini 는 응답 MIME 에 따라 jpg 등).
            // 그 밖의 값이면 png 로 두고, 허용 목록 검사는 UploadService 가 한다.
            $extension = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION)) ?: 'png';

            return $this->uploadService->uploadFromPath($tempPath, 'ai_generated', $extension);
        } finally {
            @unlink($tempPath);
        }
    }

    private function requireConfigured(string $providerKey): AiProviderContract
    {
        $provider = $this->provider($providerKey);

        if (! $provider->isConfigured()) {
            throw new InvalidArgumentException("AI provider is not configured: {$providerKey}");
        }

        return $provider;
    }

    // 소유자 조건을 직접 건다 — visibleTo()는 슈퍼관리자 전체 열람용(AiChatLogResource 조회 전용) 스코프라,
    // 여기(채팅 이어서 보내기)에 쓰면 슈퍼관리자가 다른 관리자의 대화에 메시지를 이어 보낼 수 있게 되는 사고가 난다.
    // 위젯은 슈퍼관리자도 자기 대화만 만지도록 설계되어 있어야 한다.
    private function ownedConversation(User $admin, int $conversationId): AiChatConversation
    {
        return AiChatConversation::query()->where('user_id', $admin->id)->findOrFail($conversationId);
    }

    private function createConversation(User $admin, string $providerKey, string $firstMessage): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $admin->id,
            'provider' => $providerKey,
            'title' => Str::limit(AiText::singleLine($firstMessage, 100), 50),
        ]);
    }

    /**
     * 모델에 다시 보낼 대화 이력(평문만). 이미지만 있는 답변과 행동 카드는 평문 한 줄로 바꾸고(빈 content 금지 - 제공자가
     * 400 으로 거절한다), 같은 역할이 연달아 오면 합치며, 첫 턴은 항상 사용자 턴이다. 최근 N개만 보낸다.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function historyFor(AiChatConversation $conversation): array
    {
        $messages = $conversation->messages()->with('proposals')->orderByDesc('id')->limit(self::HISTORY_LIMIT)->get()->reverse();
        $turns = [];

        foreach ($messages as $message) {
            $text = trim((string) $message->content);

            if ($message->image_path && $text === '') {
                $text = '(이미지를 생성해 보여 드렸습니다)';
            }

            foreach ($message->proposals as $proposal) {
                $text = trim($text."\n".$this->proposals->summaryLine($proposal));
            }

            if ($text === '') {
                continue;
            }

            $last = count($turns) - 1;

            if ($last >= 0 && $turns[$last]['role'] === $message->role) {
                $turns[$last]['content'] .= "\n\n".$text;
            } else {
                $turns[] = ['role' => $message->role, 'content' => $text];
            }
        }

        while ($turns !== [] && $turns[0]['role'] !== AiChatMessage::ROLE_USER) {
            array_shift($turns);
        }

        return $turns;
    }

    // 모델이 텍스트 없이 도구만 호출했을 때 서버가 만드는 고정 안내(빈 content 금지).
    private function synthesizeReply(array $cards): string
    {
        if ($cards === []) {
            return __('요청을 이해하지 못했어요. 어떤 화면에서 무엇을 하고 싶은지 조금 더 구체적으로 말씀해 주세요.');
        }

        if ($cards[0]['kind'] === AiChatProposal::KIND_OPEN_PAGE) {
            return __('해당 화면으로 가는 버튼을 준비했어요. 아래 버튼을 눌러 이동하세요.');
        }

        $label = AiProposalService::kindLabel($cards[0]['kind']);

        return __(':label을(를) 준비했어요. 아래 버튼으로 작성 화면을 열어 내용을 확인하고 직접 저장해 주세요.', ['label' => $label]);
    }

    // 보관 기간이 지난 대화를 완전히 삭제한다(소프트삭제 여부 무관). PruneAiChatHistory
    // 미들웨어가 하루 1회만 호출 — AdminAuditLogService::pruneExpired()와 동일한 트리거 방식.
    public function pruneExpired(): void
    {
        $days = (int) $this->siteSettings->get('ai_chat_retention_days', '90');

        // 사용 기록(한도 계산용)은 대화 보관 기간과 무관하게 최소 일수만 남기고 정리한다.
        $this->usage->pruneOld($days);

        if ($days <= 0) {
            return;
        }

        $expiredIds = AiChatConversation::withTrashed()
            ->where('created_at', '<', now()->subDays($days))
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            return;
        }

        AiChatMessage::whereIn('conversation_id', $expiredIds)
            ->whereNotNull('image_path')
            ->get()
            ->each(fn (AiChatMessage $message) => $this->uploadService->delete($message->image_path));

        // 카드에서 만든 이미지 중 아직 어떤 레코드에도 쓰이지 않은 것(status != used)은 고아 파일이므로 함께 지운다.
        // 사용된(used) 것은 팝업·배너·페이지가 그 파일을 쓰고 있으니 지우면 안 된다.
        AiChatProposal::query()
            ->whereIn('message_id', AiChatMessage::whereIn('conversation_id', $expiredIds)->select('id'))
            ->whereNotNull('image_path')
            ->where('status', '!=', AiChatProposal::STATUS_USED)
            ->get()
            ->each(fn (AiChatProposal $proposal) => $this->uploadService->delete($proposal->image_path));

        AiChatMessage::whereIn('conversation_id', $expiredIds)->delete();
        AiChatConversation::withTrashed()->whereIn('id', $expiredIds)->forceDelete();
    }
}
