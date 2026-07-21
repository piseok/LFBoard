@extends('layouts.subpage')

@section('subcontent')
    <article>
        <h1 class="page-title">{{ $policy->title }} {{ __('변경 예고') }}</h1>
        <p class="post-meta">
            {{ __('시행 예정일') }}: {{ $policy->effective_at->format('Y-m-d') }}
            @if ($policy->pending_version)
                ({{ __('변경 버전') }}: {{ $policy->pending_version }})
            @endif
        </p>
        <p class="post-meta">{{ __('시행일 전까지는 아래 "현재 시행 중" 내용이 그대로 적용됩니다. 시행일부터 "변경될 내용"이 적용되며, 재동의가 필요합니다.') }}</p>

        <h2>{{ __('현재 시행 중') }}</h2>
        <div class="post-content">{!! $policy->renderedContent() !!}</div>

        <h2>{{ __('변경될 내용') }}</h2>
        <div class="post-content">{!! $policy->renderedPendingContent() !!}</div>
    </article>
@endsection
