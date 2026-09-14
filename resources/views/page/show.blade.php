@extends('layouts.subpage')

@section('subcontent')
    <article>
        <x-sub-header :title="$page->title" />

        @if ($banners->isNotEmpty())
            <x-slider :items="$banners" aria-label="{{ __('배너') }}" />
        @endif

        <div class="post-content">{!! $content !!}</div>
    </article>
@endsection
