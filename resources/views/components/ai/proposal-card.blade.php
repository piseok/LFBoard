{{--
    AI 비서 "행동 카드(제안)" - 위젯(대화 중, 버튼 있음)과 감독 화면(AiChatLogResource 보기, 읽기 전용)이 같이 쓴다.
    모델이 도구 호출로 제안한 내용을 서버가 검증해 둔 값(payload)만 보여 준다. 버튼은 "저장"이 아니라 해당 작성 화면을
    여는 링크(또는 이미지를 만들고 여는 위젯 액션)이며, 저장은 항상 작성 화면에서 사람이 한다.
    $interactive=false 이면(감독 화면, 남의 대화) 버튼 없이 내용과 상태만 보여 준다.
--}}
@props(['proposal', 'interactive' => false, 'remainingImages' => 0])

@php
    $service = app(\App\Services\Ai\AiProposalService::class);
    $p = $proposal->payload ?? [];
    $kind = $proposal->kind;
    $kindLabel = \App\Services\Ai\AiProposalService::kindLabel($kind);
    $expired = $proposal->isExpired();
    $statusLabel = $expired ? '만료됨' : match ($proposal->status) {
        \App\Models\AiChatProposal::STATUS_USED => '저장됨',
        \App\Models\AiChatProposal::STATUS_IMAGE_READY => '이미지 준비됨',
        default => '제안됨',
    };
    $statusClass = $proposal->status === \App\Models\AiChatProposal::STATUS_USED ? 'aic-badge--ok' : ($expired ? 'aic-badge--warn' : '');
    $openable = $proposal->isOpenable();
    $url = $openable ? $service->openUrl($proposal) : null;
    $period = function (array $p): ?string {
        $from = ! empty($p['started_at']) ? substr($p['started_at'], 0, 10) : null;
        $to = ! empty($p['ended_at']) ? substr($p['ended_at'], 0, 10) : null;

        return ($from || $to) ? (($from ?? '미정').' ~ '.($to ?? '미정')) : null;
    };
    $bodyPreview = \Illuminate\Support\Str::limit(\App\Support\AiText::singleLine(\App\Support\SafeMarkdown::plainText($p['body'] ?? ''), 300), 120);
@endphp

<section class="aic-card" aria-label="{{ $kindLabel }} 카드">
    <div class="aic-card__head">
        <span class="aic-card__kind">{{ $kindLabel }}</span>
        <span class="aic-badge {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    @if ($kind === \App\Models\AiChatProposal::KIND_OPEN_PAGE)
        <p class="aic-card__title">{{ $p['label'] ?? '' }}{{ ($p['target'] ?? 'list') === 'create' ? ' - 새로 만들기' : '' }}</p>
    @else
        <p class="aic-card__title">{{ $p['title'] ?? '' }}</p>
        @if ($bodyPreview !== '')
            <p class="aic-card__preview">{{ $bodyPreview }}</p>
        @endif
        <dl class="aic-card__meta">
            @if ($kind === \App\Models\AiChatProposal::KIND_POST)
                <dt>게시판</dt>
                <dd>{{ $p['board_name'] ?? '작성 화면에서 선택' }}</dd>
                <dt>상태</dt>
                <dd>임시저장으로 채워짐</dd>
            @endif
            @if ($kind === \App\Models\AiChatProposal::KIND_PAGE && ! empty($p['slug']))
                <dt>주소</dt>
                <dd>{{ $p['slug'] }}</dd>
            @endif
            @if ($kind === \App\Models\AiChatProposal::KIND_BANNER && ! empty($p['group_key']))
                <dt>그룹 키</dt>
                <dd>{{ $p['group_key'] }}</dd>
            @endif
            @if (in_array($kind, [\App\Models\AiChatProposal::KIND_POPUP, \App\Models\AiChatProposal::KIND_BANNER], true))
                <dt>노출 기간</dt>
                <dd>{{ $period($p) ?? '작성 화면에서 지정' }}</dd>
            @endif
            @if (in_array($kind, [\App\Models\AiChatProposal::KIND_POPUP, \App\Models\AiChatProposal::KIND_BANNER, \App\Models\AiChatProposal::KIND_PAGE], true))
                <dt>이미지</dt>
                <dd>
                    @if ($proposal->image_path)
                        <a class="aic-link" href="{{ url($proposal->image_path) }}" target="_blank" rel="noopener noreferrer">만들어 둔 이미지 보기</a>
                    @elseif (! empty($p['image_prompt']))
                        만들 예정 - {{ \Illuminate\Support\Str::limit($p['image_prompt'], 60) }}
                    @else
                        없음
                    @endif
                </dd>
            @endif
        </dl>
    @endif

    @if ($interactive && $openable && $url)
        <div class="aic-card__actions">
            @if ($kind === \App\Models\AiChatProposal::KIND_OPEN_PAGE)
                <a class="aic-btn aic-btn--primary" href="{{ $url }}">{{ $p['label'] ?? '' }} 열기</a>
            @elseif ($proposal->needsImage())
                <button type="button" class="aic-btn aic-btn--primary"
                    wire:click="openProposalWithImage({{ $proposal->id }})"
                    wire:confirm="이미지 1장을 만드는 데 비용이 듭니다(오늘 남은 이미지 {{ $remainingImages }}장 중 1장 사용). 만들고 작성 화면을 열까요?"
                    wire:loading.attr="disabled" wire:target="openProposalWithImage"
                    @disabled($remainingImages < 1)>
                    이미지 만들고 작성 화면 열기
                </button>
                <a class="aic-btn" href="{{ $url }}">이미지 없이 열기</a>
            @else
                <a class="aic-btn aic-btn--primary" href="{{ $url }}">작성 화면 열기</a>
            @endif
        </div>
        @if ($kind !== \App\Models\AiChatProposal::KIND_OPEN_PAGE)
            @if ($proposal->needsImage())
                <p class="aic-card__note" wire:loading.remove wire:target="openProposalWithImage">
                    {{ $remainingImages < 1 ? '오늘 이미지 한도를 모두 썼습니다. 이미지 없이 열 수 있습니다.' : "이미지 1장 생성은 비용이 듭니다 · 오늘 남은 이미지 {$remainingImages}장" }}
                </p>
                <p class="aic-card__note" wire:loading wire:target="openProposalWithImage">이미지를 만드는 중입니다. 잠시만 기다려 주세요…</p>
            @endif
            <p class="aic-card__note">저장은 되지 않습니다. 작성 화면에서 내용을 확인하고 직접 저장하세요.</p>
        @endif
    @elseif (! $interactive)
        <p class="aic-card__note">읽기 전용(감독 화면) · 버튼은 제안을 받은 본인에게만 보입니다.</p>
    @elseif ($expired)
        <p class="aic-card__note">1시간이 지나 만료되었습니다. AI 비서에게 다시 요청해주세요.</p>
    @endif
</section>
