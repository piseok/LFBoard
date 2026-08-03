@extends('layouts.subpage')

@section('subcontent')
    <x-sub-header :title="__('사이트맵')" />

    <nav class="sitemap-tree" aria-label="{{ __('사이트맵') }}">
        <ul>
            @include('sitemap.tree', ['items' => $tree])
        </ul>
    </nav>
@endsection
