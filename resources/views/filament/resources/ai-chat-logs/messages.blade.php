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
                <p class="text-sm whitespace-pre-wrap">{{ $message->content }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400">메시지가 없습니다.</p>
    @endforelse
</div>
