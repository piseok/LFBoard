@extends('layouts.subpage')

@section('subcontent')
    <article>
        <h1 class="page-title">{{ $page->title }}</h1>

        <div class="post-content">{!! $content !!}</div>
    </article>
@endsection
