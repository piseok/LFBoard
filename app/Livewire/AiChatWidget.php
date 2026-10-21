<?php

namespace App\Livewire;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\Ai\AiFormFillSchema;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiUserFacingException;
use App\Services\AiChatService;
use App\Services\AiUsageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;
use Throwable;

// 관리자 패널 전역에 떠 있는 AI 비서 채팅 위젯(AdminPanelProvider의 renderHook으로 모든 페이지에
// 항상 렌더링됨). 대화 기록은 로그인한 관리자 본인 것만 조회한다 — 슈퍼관리자의 전체 열람/삭제는
// 여기가 아니라 별도의 AiChatLogResource에서만 가능하다(이 위젯은 "내 채팅"만 다룬다).
//
// 안전 원칙: AI가 만든 내용은 (1) fill-form-field 이벤트로 "현재 열려 있는 작성 폼에 채워주기만" 하거나
// (2) 행동 카드의 링크로 해당 작성 화면을 열어 폼 미리 채우기만 한다. 이 컴포넌트 자체에는 게시글/팝업 등을
// 저장하는 코드가 전혀 없다(저장은 Filament 작성 화면에서 사람이 저장 버튼을 눌러야만 일어남).
class AiChatWidget extends Component
{
    // 서버가 정하는 값은 Locked 로 둔다 - 브라우저에서 바꿀 수 있으면 권한이 없는 관리자가 providers 를 채워 넣을 수 있다.
    /** @var array<string, string> */
    #[Locked]
    public array $providers = [];

    // 이 위젯이 처음 그려진 "페이지"의 경로(예: admin/posts/create). Livewire 갱신 요청의 경로는 항상
    // livewire/update 라서 request()->is() 로는 현재 화면을 알 수 없다 - 마운트 때 한 번 담아 둔다.
    #[Locked]
    public string $screenPath = '';

    public ?string $selectedProvider = null;

    public string $mode = 'chat';

    // 전체 새로고침으로 페이지를 옮겨도(행동 카드로 작성 화면을 열 때) 열림 상태와 보던 대화를 유지한다.
    #[Session('ai_chat_widget_open')]
    public bool $isOpen = false;

    #[Session('ai_chat_widget_conversation')]
    public ?int $activeConversationId = null;

    public string $input = '';

    // 한도 초과·진행 중·카드 실패 같은 일시 안내(저장하지 않음).
    public ?string $notice = null;

    // 닫을 때 토글 버튼으로 포커스를 돌려주기 위한 표시.
    public bool $focusToggle = false;

    public function mount(?string $screenPath = null): void
    {
        $user = auth()->user();

        if ($this->canUse($user)) {
            $this->providers = app(AiChatService::class)->availableProviders();
        }

        $this->screenPath = trim($screenPath ?? request()->path(), '/');
        $this->selectedProvider = array_key_first($this->providers) ?: null;
    }

    private function canUse(?User $user): bool
    {
        return $user !== null && $user->isAdmin() && $user->hasAdminPermission('ai_assistant');
    }

    private function requireUser(): User
    {
        $user = auth()->user();

        abort_unless($this->canUse($user) && $this->providers !== [], 403);

        return $user;
    }

    // 서비스 호출 중 생긴 예외를 관리자에게 보여 줄 안내로 바꾼다. 서버가 쓴 안내(AiUserFacingException - 한도·진행 중·정책 차단·제안 만료 등)만
    // 메시지를 그대로 보여 준다. 그 밖의 예외는 RuntimeException 계열이라도 보여 주지 않는다 - QueryException·RecordsNotFoundException 같은
    // 것의 메시지에는 SQL 문구가 들어 있고, 처리되지 않으면 500 이 되므로 report() 한 뒤 일반 문구로 바꾼다(리뷰 M2).
    private function noticeFor(Throwable $e): string
    {
        if ($e instanceof AiUserFacingException) {
            return $e->getMessage();
        }

        if ($e instanceof ModelNotFoundException) {
            // 다른 탭에서 지운 대화 등 - 보던 대화를 놓고 새 대화로 시작하게 한다.
            $this->activeConversationId = null;

            return __('대화를 찾을 수 없습니다. 새 대화로 시작해주세요.');
        }

        report($e);

        return __('AI 요청을 처리하지 못했습니다. 잠시 후 다시 시도해주세요.');
    }

    /** @return Collection<int, AiChatConversation> */
    public function conversations(): Collection
    {
        // 권한을 박탈당한 관리자가 브라우저에서 $wire.conversations() 로 직접 부르지 못하게 - 렌더 중에는 이미 $showable 로 걸러진다(리뷰 L7).
        $this->requireUser();

        return AiChatConversation::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->limit(50)
            ->get(['id', 'title', 'provider']);
    }

    public function messages(): Collection|SupportCollection
    {
        $this->requireUser();

        if (! $this->activeConversationId) {
            return collect();
        }

        $conversation = AiChatConversation::query()
            ->where('user_id', auth()->id())
            ->find($this->activeConversationId);

        if (! $conversation) {
            $this->activeConversationId = null;

            return collect();
        }

        return $conversation->messages()->with('proposals')->orderByDesc('id')->limit(200)->get()->reverse()->values();
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
        $this->focusToggle = ! $this->isOpen;
        $this->notice = null;

        // 사용자가 직접 열었을 때만 입력창에 포커스를 준다(페이지를 옮길 때마다 포커스를 빼앗지 않도록 마운트 때는 주지 않는다).
        if ($this->isOpen) {
            $this->dispatch('ai-chat-focus-input');
            $this->dispatch('ai-chat-refresh');
        }
    }

    public function selectConversation(int $id): void
    {
        $this->requireUser();
        $this->activeConversationId = AiChatConversation::query()->where('user_id', auth()->id())->whereKey($id)->value('id');
        $this->notice = null;
        $this->dispatch('ai-chat-refresh');
    }

    public function newConversation(): void
    {
        $this->activeConversationId = null;
        $this->input = '';
        $this->notice = null;
        $this->dispatch('ai-chat-refresh');
    }

    public function deleteConversation(int $id): void
    {
        $this->requireUser();

        AiChatConversation::query()->where('user_id', auth()->id())->findOrFail($id)->delete();

        if ($this->activeConversationId === $id) {
            $this->activeConversationId = null;
        }
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    // 추천 질문 칩 - 입력창에 넣기만 한다(보내는 것은 관리자가 직접: 보내는 순간 비용이 든다).
    public function useSuggestion(int $index): void
    {
        $suggestion = $this->suggestions()[$index] ?? null;

        if ($suggestion !== null) {
            $this->input = $suggestion;
            $this->dispatch('ai-chat-focus-input');
        }
    }

    public function send(): void
    {
        $user = $this->requireUser();
        $prompt = mb_substr(trim($this->input), 0, 4000);

        if ($prompt === '' || ! isset($this->providers[$this->selectedProvider ?? ''])) {
            return;
        }

        $this->notice = null;
        $service = app(AiChatService::class);

        try {
            $message = $this->mode === 'image'
                ? $service->generateImage($user, $this->activeConversationId, $this->selectedProvider, $prompt)
                : $service->sendMessage($user, $this->activeConversationId, $this->selectedProvider, $prompt, $this->screenPath);
        } catch (Throwable $e) {
            // 입력한 글은 그대로 둔다(한도가 풀리거나 이전 요청이 끝나면 다시 보낼 수 있게).
            $this->notice = $this->noticeFor($e);

            return;
        }

        $this->input = '';
        $this->activeConversationId = $message->conversation_id;
        $this->dispatch('ai-chat-refresh');
    }

    // 텍스트만 현재 폼에 채워준다 — 저장은 여기서 절대 하지 않는다.
    public function fillTextField(int $messageId, string $field): void
    {
        $this->requireUser();
        $context = AiFormFillSchema::detect($this->screenPath);

        // 현재 화면의 허용 필드 표에 없는 필드면 이벤트를 보내지 않는다.
        if ($context === null || ! array_key_exists($field, $context['fields'])) {
            return;
        }

        $message = AiChatMessage::whereHas(
            'conversation', fn ($q) => $q->where('user_id', auth()->id())
        )->findOrFail($messageId);

        $value = AiFormFillSchema::formatValue($context['screen'], $field, (string) $message->content);

        if ($value === null || $value === '') {
            return;
        }

        $this->dispatch('fill-form-field', field: $field, value: $value);
    }

    // 이미지는 반드시 이 액션(버튼에 wire:confirm이 걸려 있음)을 통해서만 폼에 반영된다.
    public function useImage(int $messageId): void
    {
        $this->requireUser();
        $field = $this->targetImageField();

        if (! $field) {
            return;
        }

        $message = AiChatMessage::whereHas(
            'conversation', fn ($q) => $q->where('user_id', auth()->id())
        )->findOrFail($messageId);

        if (! $message->image_path) {
            return;
        }

        $this->dispatch('fill-form-field', field: $field, value: $message->image_path);
    }

    /**
     * 행동 카드의 "이미지 만들고 작성 화면 열기": 소유자·만료·상태를 확인한 뒤 이미지 1장을 만들어(이미지 한도 적용)
     * 제안에 붙이고 작성 화면으로 이동한다. 저장은 하지 않는다 - 작성 화면이 폼에 채우기만 한다.
     */
    public function openProposalWithImage(int $proposalId): void
    {
        $user = $this->requireUser();
        $this->notice = null;

        try {
            $proposal = app(AiChatService::class)->generateProposalImage($user, $proposalId);
        } catch (Throwable $e) {
            $this->notice = $this->noticeFor($e);

            return;
        }

        $url = app(AiProposalService::class)->openUrl($proposal);

        if ($url !== null) {
            $this->redirect($url);
        }
    }

    /**
     * 현재 열려 있는 화면(콘텐츠 관리 그룹의 작성/수정 폼)에 따라 채워넣을 수 있는 텍스트 필드 목록
     * (필드 이름 → 표시 이름). 대상 밖 화면에서는 빈 배열.
     *
     * @return array<string, string>
     */
    public function targetTextFields(): array
    {
        $context = AiFormFillSchema::detect($this->screenPath);

        return $context === null ? [] : array_map(fn (array $field) => $field['label'], $context['fields']);
    }

    public function targetImageField(): ?string
    {
        return AiFormFillSchema::detect($this->screenPath)['image_field'] ?? null;
    }

    /** @return array<int, string> */
    public function suggestions(): array
    {
        $suggestions = ['팝업 만들어줘', '배너는 어디서 관리해?', '게시판 새로 만드는 방법'];

        if ($this->targetTextFields() !== []) {
            array_unshift($suggestions, '이 화면에 채울 글 써줘');
        }

        return $suggestions;
    }

    public function render()
    {
        $user = auth()->user();
        $usage = app(AiUsageService::class);
        $showable = $this->providers !== [] && $this->canUse($user);
        // 패널이 닫혀 있으면 대화·사용량을 아예 계산하지 않는다 - 이 위젯은 모든 관리자 화면에 렌더되므로 닫힌 상태에서까지
        // 매 페이지 COUNT 쿼리 4개를 돌릴 이유가 없다(리뷰 M4). 사용량 숫자는 열린 패널(blade 의 $isOpen 분기)에서만 쓴다.
        $open = $showable && $this->isOpen;

        return view('livewire.ai-chat-widget', [
            'showable' => $showable,
            'conversations' => $open ? $this->conversations() : collect(),
            'messages' => $open ? $this->messages() : collect(),
            'chatUsage' => $open ? $usage->status($user, AiUsageService::KIND_CHAT) : null,
            'imageUsage' => $open ? $usage->status($user, AiUsageService::KIND_IMAGE) : null,
            'fillFields' => $this->targetTextFields(),
            'imageField' => $this->targetImageField(),
            'screenLabel' => AiFormFillSchema::detect($this->screenPath)['label'] ?? null,
            'suggestions' => $this->suggestions(),
        ]);
    }
}
