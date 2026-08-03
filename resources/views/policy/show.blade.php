@extends('layouts.subpage')

@section('subcontent')
    <article>
        <x-sub-header :title="$policy->title" :description="$policy->version ? __('버전').': '.$policy->version : null" />
        <div class="post-content">{!! $policy->renderedContent() !!}</div>
    </article>
@endsection
