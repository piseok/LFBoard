<?php

namespace App\Services;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AiChatService
{
    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly UploadService $uploadService,
        private readonly AdminAuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, AiProviderContract>
     */
    private function providers(): array
    {
        return [
            'openai' => new OpenAiProvider($this->siteSettings),
            'gemini' => new GeminiProvider($this->siteSettings),
        ];
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

    public function sendMessage(User $admin, ?int $conversationId, string $providerKey, string $message): AiChatMessage
    {
        $conversation = $this->resolveConversation($admin, $conversationId, $providerKey, $message);
        $conversation->messages()->create(['role' => AiChatMessage::ROLE_USER, 'content' => $message]);

        $provider = $this->provider($providerKey);
        $history = $conversation->messages()->orderBy('id')->get()
            ->map(fn (AiChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        try {
            $reply = $provider->chat($history);
            $success = true;
        } catch (Throwable) {
            $reply = __('AI 응답을 받아오지 못했습니다. 잠시 후 다시 시도해주세요.');
            $success = false;
        }

        $this->auditLog->recordAiUsage($admin, $providerKey, 'chat', $success);

        return $conversation->messages()->create(['role' => AiChatMessage::ROLE_ASSISTANT, 'content' => $reply]);
    }

    // 생성된 이미지는 여기서 바로 폼에 반영되지 않는다 — 채팅창에 미리보기 메시지로만 남고,
    // 관리자가 "이 이미지 사용하기"로 확정해야만(Livewire 위젯 쪽) fill-form-field가 발생한다.
    public function generateImage(User $admin, ?int $conversationId, string $providerKey, string $prompt): AiChatMessage
    {
        $conversation = $this->resolveConversation($admin, $conversationId, $providerKey, $prompt);
        $conversation->messages()->create(['role' => AiChatMessage::ROLE_USER, 'content' => $prompt]);

        $provider = $this->provider($providerKey);

        try {
            $tempPath = $provider->generateImage($prompt);
            $storedPath = $this->uploadService->uploadFromPath($tempPath, 'ai_generated', 'png');
            @unlink($tempPath);
            $content = null;
            $success = true;
        } catch (Throwable) {
            $storedPath = null;
            $content = __('이미지 생성에 실패했습니다. 잠시 후 다시 시도해주세요.');
            $success = false;
        }

        $this->auditLog->recordAiUsage($admin, $providerKey, 'image', $success);

        return $conversation->messages()->create([
            'role' => AiChatMessage::ROLE_ASSISTANT,
            'content' => $content,
            'image_path' => $storedPath,
        ]);
    }

    private function resolveConversation(User $admin, ?int $conversationId, string $providerKey, string $firstMessage): AiChatConversation
    {
        if ($conversationId !== null) {
            // visibleTo()가 아니라 소유자 조건을 직접 건다 — visibleTo()는 슈퍼관리자 전체 열람용
            // (AiChatLogResource 조회 전용) 스코프라, 여기(채팅 이어서 보내기)에 쓰면 슈퍼관리자가
            // 다른 관리자의 대화에 메시지를 이어 보낼 수 있게 되는 사고가 난다. 위젯은 슈퍼관리자도
            // 자기 대화만 만지도록 설계되어 있어야 한다.
            return AiChatConversation::query()->where('user_id', $admin->id)->findOrFail($conversationId);
        }

        return AiChatConversation::create([
            'user_id' => $admin->id,
            'provider' => $providerKey,
            'title' => Str::limit($firstMessage, 50),
        ]);
    }

    // 보관 기간이 지난 대화를 완전히 삭제한다(소프트삭제 여부 무관). PruneAiChatHistory
    // 미들웨어가 하루 1회만 호출 — AdminAuditLogService::pruneExpired()와 동일한 트리거 방식.
    public function pruneExpired(): void
    {
        $days = (int) $this->siteSettings->get('ai_chat_retention_days', '90');

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

        AiChatMessage::whereIn('conversation_id', $expiredIds)->delete();
        AiChatConversation::withTrashed()->whereIn('id', $expiredIds)->forceDelete();
    }
}
