{{--
    AI 비서 사용 내역(감독 화면)의 대화 보기. 감독하는 슈퍼관리자/일반 최고관리자는 남의 대화를 읽기만 한다 -
    AI 답변은 위젯과 같은 안전한 마크다운 렌더러(SafeMarkdown)로 그리고, 행동 카드는 버튼 없는 읽기 전용으로만 보인다
    (카드의 소유자는 제안을 받은 관리자 본인이며, 남이 그 카드로 작성 화면을 열 수 없다).
--}}
<div class="space-y-3">
    @forelse ($messages as $message)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs font-semibold text-gray-500 mb-1">
                {{ $message->role === 'user' ? '관리자' : 'AI' }}
                <span class="font-normal">· {{ $message->created_at?->format('Y-m-d H:i') }}</span>
            </p>
            @if ($message->image_path)
                <img src="{{ url($message->image_path) }}" alt="생성된 이미지" class="rounded max-w-full mb-2">
            @endif
            @if ($message->content)
                @if ($message->role === 'assistant')
                    <div class="aic-md text-sm">{{ \App\Support\SafeMarkdown::chatHtml($message->content) }}</div>
                @else
                    <p class="text-sm whitespace-pre-wrap">{{ $message->content }}</p>
                @endif
            @endif

            @foreach ($message->proposals ?? [] as $proposal)
                <div class="mt-2">
                    <x-ai.proposal-card :proposal="$proposal" :interactive="false" />
                </div>
            @endforeach
        </div>
    @empty
        <p class="text-sm text-gray-400">메시지가 없습니다.</p>
    @endforelse
</div>
