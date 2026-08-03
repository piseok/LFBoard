@extends('layouts.subpage')

@section('subcontent')
    <article>
        <x-sub-header :title="$page->title" />

        <div class="post-content">{!! $content !!}</div>
    </article>
@endsection
