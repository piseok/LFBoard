{{--
    AI 비서 위젯(Livewire). 스타일은 public/css/ai-chat.css(토큰 색 + 라이트/다크 두 벌)로 분리했다 -
    예전에는 이 파일 안 <style> 에 라이트 색만 하드코딩돼 다크 모드에서 입력칸 글자가 안 보였다.
    Filament 패널 CSS 는 미리 빌드된 번들이라 새 Tailwind 유틸리티 클래스는 먹히지 않는다(그래서 aic-* 전용 클래스).
    이 컴포넌트의 루트 요소는 아래 <div> 하나여야 한다(밖에 형제 태그를 두면 Livewire 가 그걸 루트로 착각한다).

    동작 메모
    - 보내기: 폼 submit / Enter(Shift+Enter 는 줄바꿈, 한글 조합 중 Enter 는 무시 - 조합 확정 Enter 로 전송되던 문제 방지).
      보내는 동안 말풍선(pending)과 "작성 중" 표시를 바로 보여 주고, 끝나면 맨 아래로 스크롤하고 입력창에 포커스를 돌려준다.
    - 대화 로그는 role="log"(암묵적 aria-live=polite) 하나만 낭독한다.
    - AI 답변은 SafeMarkdown 으로 렌더한다(원문 HTML 이스케이프, http/https 링크만, 이미지·제목 태그 없음).
      Blade 출력은 {{ }} 이며 신뢰할 수 없는 문자열을 {!! !!} 로 찍지 않는다(SafeMarkdown 은 HtmlString 을 돌려준다).
--}}
<div class="aic-root"
    x-data="{
        busy: false,
        pending: '',
        scrollLog() { this.$nextTick(() => { const log = this.$refs.log; if (log) { log.scrollTop = log.scrollHeight; } }); },
        focusInput() { this.$nextTick(() => { if (this.$refs.box) { this.$refs.box.focus(); } }); },
        sendNow() {
            const box = this.$refs.box;
            const text = box ? box.value.trim() : '';
            if (this.busy || text === '') { return; }
            this.pending = text;
            this.busy = true;
            this.scrollLog();
            this.$wire.send().finally(() => { this.busy = false; this.pending = ''; this.scrollLog(); this.focusInput(); });
        },
    }"
    x-on:ai-chat-refresh.window="scrollLog()"
    x-on:ai-chat-focus-input.window="focusInput()">

    @if ($showable)
        @if ($isOpen)
            @php
                $mode = $mode === 'image' ? 'image' : 'chat';
                $usage = $mode === 'image' ? $imageUsage : $chatUsage;
                $blocked = $usage['remaining'] < 1;
                $canCreateImageCards = $imageUsage['remaining'];
            @endphp

            <section id="aic-panel" class="aic-panel" role="dialog" aria-modal="false" aria-labelledby="aic-title"
                x-on:keydown.escape.stop="$wire.toggle()">
                <div class="aic-head">
                    <h2 id="aic-title" class="aic-title">AI 비서</h2>
                    <div class="aic-head-actions">
                        <button type="button" class="aic-headbtn" wire:click="newConversation">새 대화</button>
                        <button type="button" class="aic-headbtn" wire:click="toggle" aria-label="AI 비서 닫기">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                @if ($conversations->isNotEmpty())
                    <div class="aic-convs" x-data="{ open: false }">
                        <button type="button" class="aic-linkbtn" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="aic-convlist">
                            이전 대화 ({{ $conversations->count() }})
                        </button>
                        <ul id="aic-convlist" class="aic-convlist" x-show="open" x-cloak>
                            @foreach ($conversations as $conversation)
                                <li class="aic-convitem {{ $activeConversationId === $conversation->id ? 'is-active' : '' }}" wire:key="conv-{{ $conversation->id }}">
                                    <button type="button" class="aic-convtitle" wire:click="selectConversation({{ $conversation->id }})"
                                        @if ($activeConversationId === $conversation->id) aria-current="true" @endif>
                                        {{ $conversation->title ?: '(제목 없음)' }}
                                    </button>
                                    <button type="button" class="aic-convdel" wire:click="deleteConversation({{ $conversation->id }})"
                                        wire:confirm="이 대화를 삭제하시겠습니까?"
                                        aria-label="대화 삭제: {{ $conversation->title ?: '제목 없음' }}">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div id="aic-log" class="aic-log" role="log" aria-label="AI 비서 대화" aria-relevant="additions" x-ref="log" tabindex="0"
                    x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight; })">

                    @if ($screenLabel)
                        <p class="aic-context">
                            지금 "{{ $screenLabel }}" 화면입니다. AI 답변 아래의 <strong>채우기</strong> 버튼으로 칸에 넣을 수 있고, 저장은 직접 눌러야 합니다.
                        </p>
                    @endif

                    @forelse ($messages as $message)
                        <article class="aic-row aic-row--{{ $message->role }}" wire:key="msg-{{ $message->id }}">
                            <div class="aic-bubble aic-bubble--{{ $message->role }}">
                                <span class="aic-sr-only">{{ $message->role === 'user' ? '나' : 'AI' }}:</span>
                                @if ($message->image_path)
                                    <img class="aic-img" src="{{ url($message->image_path) }}" alt="AI가 만든 이미지" loading="lazy">
                                @endif
                                @if ($message->content)
                                    @if ($message->role === 'assistant')
                                        <div class="aic-md">{{ \App\Support\SafeMarkdown::chatHtml($message->content) }}</div>
                                    @else
                                        <p class="aic-plain">{{ $message->content }}</p>
                                    @endif
                                @endif
                            </div>

                            @if ($message->role === 'assistant')
                                @foreach ($message->proposals as $proposal)
                                    <div class="aic-cardwrap" wire:key="proposal-{{ $proposal->id }}">
                                        <x-ai.proposal-card :proposal="$proposal" :interactive="true" :remaining-images="$canCreateImageCards" />
                                    </div>
                                @endforeach

                                @if (($message->image_path && $imageField) || ($message->content && $fillFields !== []))
                                    <div class="aic-actions" role="group" aria-label="폼에 채우기(저장은 직접)">
                                        @if ($message->image_path && $imageField)
                                            <button type="button" class="aic-btn"
                                                wire:click="useImage({{ $message->id }})"
                                                wire:confirm="이 이미지를 폼에 적용하시겠습니까? (저장은 화면의 저장 버튼을 직접 눌러야 합니다)">
                                                이 이미지 사용하기
                                            </button>
                                        @endif
                                        @if ($message->content && ! $message->image_path)
                                            @foreach ($fillFields as $field => $label)
                                                <button type="button" class="aic-btn"
                                                    wire:click="fillTextField({{ $message->id }}, '{{ $field }}')">
                                                    {{ $label }}에 채우기
                                                </button>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div class="aic-empty" x-show="! busy">
                            <p class="aic-empty-title">무엇을 도와드릴까요?</p>
                            <p class="aic-empty-desc">팝업·게시글·배너·페이지 초안을 만들거나, 관리 화면 위치를 물어보세요. AI는 저장하지 않고 초안만 제안합니다.</p>
                            <div class="aic-chips" role="group" aria-label="추천 질문">
                                @foreach ($suggestions as $index => $suggestion)
                                    <button type="button" class="aic-chip" wire:click="useSuggestion({{ $index }})">{{ $suggestion }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforelse

                    {{-- 전송 직후 내 말풍선을 바로 보여 준다(서버 응답 전). 낭독 중복을 피하려고 aria-hidden. --}}
                    <div class="aic-row aic-row--user" x-show="busy && pending" x-cloak aria-hidden="true">
                        <div class="aic-bubble aic-bubble--user"><p class="aic-plain" x-text="pending"></p></div>
                    </div>
                    <div class="aic-typing" x-show="busy" x-cloak aria-hidden="true">
                        <span class="aic-dot"></span><span class="aic-dot"></span><span class="aic-dot"></span>
                        <span>AI가 작성 중입니다…</span>
                    </div>
                    <div class="aic-sr-only" role="status" aria-live="polite" x-text="busy ? 'AI가 답변을 작성하고 있습니다.' : ''"></div>
                </div>

                @if ($notice)
                    <div class="aic-notice" role="alert">
                        <span>{{ $notice }}</span>
                        <button type="button" wire:click="dismissNotice">닫기</button>
                    </div>
                @endif

                <form class="aic-form" x-on:submit.prevent="sendNow()">
                    <div class="aic-opts">
                        <label class="aic-field">
                            <span>AI 제공자</span>
                            <select class="aic-select" wire:model.live="selectedProvider">
                                @foreach ($providers as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <fieldset class="aic-seg">
                            <legend class="aic-sr-only">요청 종류</legend>
                            <label><input type="radio" wire:model.live="mode" value="chat"><span>텍스트</span></label>
                            <label><input type="radio" wire:model.live="mode" value="image"><span>이미지</span></label>
                        </fieldset>
                    </div>

                    <div class="aic-compose">
                        <label class="aic-sr-only" for="aic-input">{{ $mode === 'image' ? '만들 이미지 설명' : 'AI에게 보낼 메시지' }}</label>
                        <textarea id="aic-input" class="aic-textarea" x-ref="box" wire:model="input" rows="2" maxlength="4000"
                            placeholder="{{ $mode === 'image' ? '만들 이미지를 설명해주세요' : '메시지를 입력하세요' }}"
                            aria-describedby="aic-hint aic-usage"
                            @disabled($blocked)
                            x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); sendNow(); }"
                        ></textarea>
                        <button type="submit" class="aic-send" x-bind:disabled="busy || {{ $blocked ? 'true' : 'false' }}"
                            @disabled($blocked)>전송</button>
                    </div>

                    <p id="aic-hint" class="aic-hint">Enter 로 전송, Shift+Enter 로 줄바꿈. 대화 내용은 외부 AI 제공자로 전송되니 개인정보는 입력하지 마세요.</p>

                    @if ($blocked)
                        <p class="aic-limit" role="status">
                            @if ($usage['blocked_by'] === 'site')
                                오늘 사이트 전체 {{ $mode === 'image' ? '이미지' : '질문' }} 한도를 모두 썼습니다. 내일 0시에 초기화됩니다.
                            @else
                                오늘 내 {{ $mode === 'image' ? '이미지' : '질문' }} 한도를 모두 썼습니다. 내일 0시에 초기화됩니다.
                            @endif
                        </p>
                    @endif

                    <p id="aic-usage" class="aic-usage">
                        오늘 남은 질문 <strong>{{ $chatUsage['remaining'] }}회</strong> · 이미지 <strong>{{ $imageUsage['remaining'] }}장</strong>
                        <span class="aic-usage-sub">
                            우리 사이트의 일일 한도 기준 · 내 {{ $mode === 'image' ? '이미지' : '질문' }} {{ $usage['admin_used'] }}/{{ $usage['admin_limit'] }}
                            · 사이트 전체 {{ $usage['site_used'] }}/{{ $usage['site_limit'] }}
                        </span>
                    </p>
                </form>
            </section>
        @else
            <button type="button" class="aic-fab" wire:click="toggle" aria-label="AI 비서 열기" aria-expanded="false" aria-controls="aic-panel"
                @if ($focusToggle) x-init="$nextTick(() => $el.focus())" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="28" height="28" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8M8 14h5m6.75 -1.75c0 4.28 -3.806 7.75 -8.5 7.75a9.4 9.4 0 0 1 -2.61 -.364L4 21l1.395 -3.72C4.512 16.176 4 14.933 4 13.75c0 -4.28 3.806 -7.75 8.5 -7.75s8.5 3.47 8.5 7.75Z" />
                </svg>
            </button>
        @endif
    @endif
</div>
