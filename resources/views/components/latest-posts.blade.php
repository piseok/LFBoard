{{--
    특정 게시판의 최신글을 어디서든(메인 페이지 등) 가져다 쓸 수 있는 재사용 컴포넌트.

    사용법: <x-latest-posts board="notice" :limit="5" />
           <x-latest-posts board="notice" :limit="5" skin="card" />

    스킨 추가하는 법: resources/views/partials/latest-posts/{이름}/index.blade.php 만들면
    skin="{이름}"으로 바로 쓸 수 있다(게시판 스킨과 동일한 폴더 방식 — BoardFrontController::resolveSkinView() 참고).
    스킨 뷰에는 $posts(Collection<Post>)와 $board(Board)가 그대로 전달된다.
--}}
@props(['board', 'limit' => 5, 'skin' => 'list'])

@php
    $latestPostsBoard = \App\Models\Board::query()
        ->where('slug', $board)
        ->where('locale', app()->getLocale())
        ->where('is_active', true)
        ->first();

    $posts = $latestPostsBoard
        ? \App\Models\Post::query()
            ->where('board_id', $latestPostsBoard->id)
            ->where('is_active', true)
            ->where('is_draft', false)
            ->where('is_secret', false)
            ->latest()
            ->limit($limit)
            ->get()
        : collect();

    $skinView = "partials.latest-posts.{$skin}.index";
    $skinView = \Illuminate\Support\Facades\View::exists($skinView) ? $skinView : 'partials.latest-posts.list.index';
@endphp

@if ($latestPostsBoard)
    @include($skinView, ['posts' => $posts, 'board' => $latestPostsBoard])
@endif
