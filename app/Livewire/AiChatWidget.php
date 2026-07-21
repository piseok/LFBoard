<?php

namespace App\Livewire;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\AiChatService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Component;

// 관리자 패널 전역에 떠 있는 AI 비서 채팅 위젯(AdminPanelProvider의 renderHook으로 모든 페이지에
// 항상 렌더링됨). 대화 기록은 로그인한 관리자 본인 것만 조회한다 — 슈퍼관리자의 전체 열람/삭제는
// 여기가 아니라 별도의 AiChatLogResource에서만 가능하다(이 위젯은 "내 채팅"만 다룬다).
// AI가 생성한 내용은 fill-form-field 이벤트로 현재 열려 있는 작성 폼에 채워주기만 하고,
// 이 컴포넌트 자체에는 게시글/팝업 등을 저장하는 코드가 전혀 없다.
class AiChatWidget extends Component
{
    public array $providers = [];

    public ?string $selectedProvider = null;

    public string $mode = 'chat';

    public bool $isOpen = false;

    public ?int $activeConversationId = null;

    public string $input = '';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user && $user->hasAdminPermission('ai_assistant')) {
            $this->providers = app(AiChatService::class)->availableProviders();
        }

        $this->selectedProvider = array_key_first($this->providers) ?: null;
    }

    public function conversations(): Collection
    {
        return AiChatConversation::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get(['id', 'title', 'provider']);
    }

    public function messages(): Collection|SupportCollection
    {
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

        return $conversation->messages()->orderBy('id')->get();
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
    }

    public function newConversation(): void
    {
        $this->activeConversationId = null;
        $this->input = '';
    }

    public function deleteConversation(int $id): void
    {
        AiChatConversation::query()->where('user_id', auth()->id())->findOrFail($id)->delete();

        if ($this->activeConversationId === $id) {
            $this->activeConversationId = null;
        }
    }

    public function send(): void
    {
        if (! $this->selectedProvider || trim($this->input) === '') {
            return;
        }

        $service = app(AiChatService::class);
        $prompt = $this->input;
        $this->input = '';

        $message = $this->mode === 'image'
            ? $service->generateImage(auth()->user(), $this->activeConversationId, $this->selectedProvider, $prompt)
            : $service->sendMessage(auth()->user(), $this->activeConversationId, $this->selectedProvider, $prompt);

        $this->activeConversationId = $message->conversation_id;
    }

    // 텍스트만 현재 폼에 채워준다 — 저장은 여기서 절대 하지 않는다.
    public function fillTextField(int $messageId, string $field): void
    {
        $message = AiChatMessage::whereHas(
            'conversation', fn ($q) => $q->where('user_id', auth()->id())
        )->findOrFail($messageId);

        $this->dispatch('fill-form-field', field: $field, value: $message->content);
    }

    // 이미지는 반드시 이 액션(버튼에 wire:confirm이 걸려 있음)을 통해서만 폼에 반영된다.
    public function useImage(int $messageId): void
    {
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
     * 현재 열려 있는 화면(콘텐츠 관리 그룹의 작성/수정 폼)에 따라 채워넣을 수 있는
     * 텍스트 필드 목록을 반환한다. 미디어라이브러리 등 대상 밖 화면에서는 빈 배열.
     *
     * @return array<string, string>
     */
    public function targetTextFields(): array
    {
        $prefix = config('app.admin_path', 'admin');

        return match (true) {
            request()->is("{$prefix}/posts/create") || request()->is("{$prefix}/posts/*/edit") => [
                'title' => '제목', 'content' => '본문(에디터형 게시판)', 'content_text' => '본문(텍스트형 게시판)',
            ],
            request()->is("{$prefix}/boards/create") || request()->is("{$prefix}/boards/*/edit") => [
                'name' => '게시판명', 'description' => '설명',
            ],
            request()->is("{$prefix}/inquiries/*/edit") => [
                'admin_reply' => '답변 내용',
            ],
            request()->is("{$prefix}/popups/create") || request()->is("{$prefix}/popups/*/edit") => [
                'title' => '제목', 'html_content' => 'HTML 내용',
            ],
            request()->is("{$prefix}/banners/create") || request()->is("{$prefix}/banners/*/edit") => [
                'title' => '제목', 'alt_text' => 'ALT 텍스트', 'link_url' => '링크 URL',
            ],
            request()->is("{$prefix}/pages/create") || request()->is("{$prefix}/pages/*/edit") => [
                'title' => '제목', 'content' => '본문', 'meta_title' => '메타 타이틀',
                'meta_description' => '메타 설명', 'meta_keywords' => '메타 키워드',
            ],
            default => [],
        };
    }

    public function targetImageField(): ?string
    {
        $prefix = config('app.admin_path', 'admin');

        return match (true) {
            request()->is("{$prefix}/popups/create") || request()->is("{$prefix}/popups/*/edit") => 'image_path',
            request()->is("{$prefix}/banners/create") || request()->is("{$prefix}/banners/*/edit") => 'image_path',
            request()->is("{$prefix}/pages/create") || request()->is("{$prefix}/pages/*/edit") => 'og_image',
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.ai-chat-widget');
    }
}
