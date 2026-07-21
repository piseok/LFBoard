<div>
    <p class="text-sm text-gray-500 mb-2"><strong>제목:</strong> {{ $subject }}</p>
    <div style="font-family: sans-serif; background: #f4f4f4; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden;">
            <div style="background: #1a1a2e; color: #fff; padding: 24px; text-align: center;">
                {{ config('app.name') }}
            </div>
            <div style="padding: 32px; color: #333; line-height: 1.7;">
                {!! $body !!}
            </div>
            <div style="background: #f4f4f4; padding: 16px; text-align: center; font-size: 12px; color: #999;">
                본 메일은 발신 전용입니다.
            </div>
        </div>
    </div>
</div>
