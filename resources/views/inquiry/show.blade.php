@extends('layouts.subpage')

@section('subcontent')
    <article>
        <header class="post-header">
            <h1 class="post-title">{{ $inquiry->title }}</h1>
            <div class="post-meta">
                <span>{{ $inquiry->name }}</span>
                <time datetime="{{ $inquiry->created_at->toIso8601String() }}">{{ local_datetime($inquiry->created_at)->format('Y-m-d H:i') }}</time>
                <span class="badge {{ $inquiry->status === 'done' ? 'badge-notice' : 'badge-secret' }}">
                    {{ __(['pending' => '대기', 'processing' => '처리중', 'done' => '완료'][$inquiry->status] ?? $inquiry->status) }}
                </span>
            </div>
        </header>

        <div class="post-content">{{ $inquiry->content }}</div>

        @if ($inquiry->file_path)
            <div class="post-files">
                <a href="{{ url($inquiry->file_path) }}" target="_blank" rel="noopener">{{ __('첨부파일 다운로드') }}</a>
            </div>
        @endif

        @if ($inquiry->status === 'done' && $inquiry->admin_reply)
            <div class="post-files" style="margin-top:24px;">
                <strong>{{ __('답변') }}</strong>
                <div class="post-content">{!! $inquiry->admin_reply !!}</div>
                @if ($inquiry->replied_at)
                    <p class="post-meta">{{ __(':datetime 답변', ['datetime' => local_datetime($inquiry->replied_at)->format('Y-m-d H:i')]) }}</p>
                @endif
            </div>
        @endif

        <div class="form-actions">
            <a href="{{ front_route('inquiry.index') }}" class="btn">{{ __('목록') }}</a>
        </div>
    </article>
@endsection
