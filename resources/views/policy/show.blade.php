@extends('layouts.subpage')

@section('subcontent')
    <article>
        <h1 class="page-title">{{ $policy->title }}</h1>
        @if ($policy->version)
            <p class="post-meta">{{ __('버전') }}: {{ $policy->version }}</p>
        @endif
        <div class="post-content">{!! $policy->renderedContent() !!}</div>
    </article>
@endsection
