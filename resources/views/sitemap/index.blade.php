@extends('layouts.subpage')

@section('subcontent')
    <h1 class="page-title">{{ __('사이트맵') }}</h1>

    <nav class="sitemap-tree" aria-label="{{ __('사이트맵') }}">
        <ul>
            @include('sitemap.tree', ['items' => $tree])
        </ul>
    </nav>
@endsection
