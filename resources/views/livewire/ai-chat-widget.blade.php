{{--
    Filament 관리자 패널 CSS는 필라멘트 자체 패키지에서 미리 빌드되어 오는 번들이라(이 프로젝트에
    Tailwind 빌드 파이프라인이 없음), 여기서 새 Tailwind 유틸리티 클래스를 써봤자 컴파일된 CSS에
    없어서 스타일이 전혀 안 먹힌다(실제로 겪은 문제). 그래서 이 위젯만 순수 CSS로 직접 그린다 —
    어떤 빌드 상태에서도 항상 보이도록. <style>은 반드시 이 컴포넌트의 유일한 최상위 요소인
    아래 <div> "안쪽"에 둬야 한다 — 밖에 형제로 두면 Livewire가 <style> 태그 자체를 컴포넌트
    루트로 착각해서 wire:snapshot 등을 거기 붙여버리는 사고가 난다(실제로 겪은 문제).
--}}
<div class="ai-chat-widget-root" x-data>
    <style>
    .ai-chat-widget-root { position: fixed; bottom: 24px; right: 24px; z-index: 9999; font-family: inherit; }
    .ai-chat-widget-toggle {
        width: 56px; height: 56px; border-radius: 9999px; background: #f59e0b; color: #fff;
        border: none; box-shadow: 0 4px 12px rgba(0,0,0,.25); display: flex; align-items: center;
        justify-content: center; cursor: pointer;
    }
    .ai-chat-widget-toggle:hover { background: #d97706; }
    .ai-chat-widget-panel {
        width: 384px; max-width: calc(100vw - 48px); height: 512px; max-height: calc(100vh - 128px);
        background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.3);
        border: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow: hidden;
    }
    .ai-chat-widget-header {
        display: flex; align-items: center; justify-content: space-between; padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb; background: #f59e0b; color: #fff;
    }
    .ai-chat-widget-header button { background: none; border: none; color: #fff; opacity: .8; cursor: pointer; font-size: 18px; }
    .ai-chat-widget-header button:hover { opacity: 1; }
    .ai-chat-widget-convlist { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #e5e7eb; overflow-x: auto; font-size: 12px; }
    .ai-chat-widget-newconv { flex-shrink: 0; padding: 4px 8px; border-radius: 6px; background: #fef3c7; color: #92400e; border: none; cursor: pointer; white-space: nowrap; }
    .ai-chat-widget-conv { flex-shrink: 0; display: flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; background: #f3f4f6; }
    .ai-chat-widget-conv.active { background: #e5e7eb; }
    .ai-chat-widget-conv button.title { max-width: 128px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; background: none; border: none; cursor: pointer; }
    .ai-chat-widget-conv button.del { background: none; border: none; color: #9ca3af; cursor: pointer; }
    .ai-chat-widget-conv button.del:hover { color: #ef4444; }
    .ai-chat-widget-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 12px; font-size: 14px; }
    .ai-chat-widget-msg-row.user { text-align: right; }
    .ai-chat-widget-msg-row.assistant { text-align: left; }
    .ai-chat-widget-bubble { display: inline-block; max-width: 85%; border-radius: 8px; padding: 8px 12px; }
    .ai-chat-widget-bubble.user { background: #f59e0b; color: #fff; }
    .ai-chat-widget-bubble.assistant { background: #f3f4f6; color: #111827; }
    .ai-chat-widget-bubble img { border-radius: 6px; max-width: 100%; margin-bottom: 8px; }
    .ai-chat-widget-bubble p { white-space: pre-wrap; margin: 0; }
    .ai-chat-widget-actions { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px; }
    .ai-chat-widget-actions button { font-size: 12px; padding: 2px 8px; border-radius: 6px; border: none; cursor: pointer; }
    .ai-chat-widget-actions button.image { background: #dbeafe; color: #1d4ed8; }
    .ai-chat-widget-actions button.field { background: #f3f4f6; color: #374151; }
    .ai-chat-widget-empty { color: #9ca3af; text-align: center; margin-top: 32px; }
    .ai-chat-widget-loading { color: #9ca3af; font-size: 12px; }
    .ai-chat-widget-form { border-top: 1px solid #e5e7eb; padding: 8px; }
    .ai-chat-widget-form-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 8px; }
    .ai-chat-widget-form select { border-radius: 6px; border: 1px solid #d1d5db; padding: 4px; }
    .ai-chat-widget-input-row { display: flex; gap: 8px; }
    .ai-chat-widget-input-row textarea { flex: 1; font-size: 14px; border-radius: 6px; border: 1px solid #d1d5db; resize: none; padding: 6px; }
    .ai-chat-widget-input-row button { padding: 0 12px; border-radius: 6px; background: #f59e0b; color: #fff; border: none; cursor: pointer; }
    .ai-chat-widget-input-row button:hover { background: #d97706; }
    </style>

    @if (! empty($providers))
        @if ($isOpen)
            <div class="ai-chat-widget-panel">
                <div class="ai-chat-widget-header">
                    <span style="font-weight:600;font-size:14px;">AI 비서</span>
                    <button type="button" wire:click="toggle">&times;</button>
                </div>

                <div class="ai-chat-widget-convlist">
                    <button type="button" class="ai-chat-widget-newconv" wire:click="newConversation">+ 새 대화</button>
                    @foreach ($this->conversations() as $conversation)
                        <div class="ai-chat-widget-conv {{ $activeConversationId === $conversation->id ? 'active' : '' }}">
                            <button type="button" class="title" wire:click="selectConversation({{ $conversation->id }})">
                                {{ $conversation->title ?: '(제목 없음)' }}
                            </button>
                            <button type="button" class="del" wire:click="deleteConversation({{ $conversation->id }})"
                                wire:confirm="이 대화를 삭제하시겠습니까?">&times;</button>
                        </div>
                    @endforeach
                </div>

                <div class="ai-chat-widget-messages">
                    @forelse ($this->messages() as $message)
                        <div class="ai-chat-widget-msg-row {{ $message->role }}">
                            <div class="ai-chat-widget-bubble {{ $message->role }}">
                                @if ($message->image_path)
                                    <img src="{{ url($message->image_path) }}" alt="생성된 이미지">
                                @endif
                                @if ($message->content)
                                    <p>{{ $message->content }}</p>
                                @endif
                            </div>

                            @if ($message->role === 'assistant')
                                <div class="ai-chat-widget-actions">
                                    @if ($message->image_path && $this->targetImageField())
                                        <button type="button" class="image"
                                            wire:click="useImage({{ $message->id }})"
                                            wire:confirm="이 이미지를 폼에 적용하시겠습니까?">
                                            이 이미지 사용하기
                                        </button>
                                    @endif
                                    @if ($message->content)
                                        @foreach ($this->targetTextFields() as $field => $label)
                                            <button type="button" class="field"
                                                wire:click="fillTextField({{ $message->id }}, '{{ $field }}')">
                                                {{ $label }}에 채우기
                                            </button>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="ai-chat-widget-empty">무엇을 도와드릴까요?</p>
                    @endforelse

                    <div wire:loading wire:target="send" class="ai-chat-widget-loading">AI가 응답을 생성하는 중...</div>
                </div>

                <form wire:submit="send" class="ai-chat-widget-form">
                    <div class="ai-chat-widget-form-row">
                        <select wire:model="selectedProvider">
                            @foreach ($providers as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select wire:model="mode">
                            <option value="chat">텍스트 생성</option>
                            <option value="image">이미지 생성</option>
                        </select>
                    </div>
                    <div class="ai-chat-widget-input-row">
                        <textarea wire:model="input" rows="2"
                            placeholder="{{ $mode === 'image' ? '생성할 이미지를 설명해주세요' : '메시지를 입력하세요' }}"
                            wire:keydown.enter.prevent="send"
                        ></textarea>
                        <button type="submit" wire:loading.attr="disabled" wire:target="send">전송</button>
                    </div>
                </form>
            </div>
        @else
            <button type="button" class="ai-chat-widget-toggle" wire:click="toggle" title="AI 비서">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="28" height="28">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8M8 14h5m6.75 -1.75c0 4.28 -3.806 7.75 -8.5 7.75a9.4 9.4 0 0 1 -2.61 -.364L4 21l1.395 -3.72C4.512 16.176 4 14.933 4 13.75c0 -4.28 3.806 -7.75 8.5 -7.75s8.5 3.47 8.5 7.75Z" />
                </svg>
            </button>
        @endif
    @endif
</div>
