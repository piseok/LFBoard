{{-- 통합검색(14번) — 홈 화면 검색 섹션. ja/home/index.blade.php 등 홈 오버라이드 뷰에서도 재사용. --}}
<section aria-label="{{ __('통합검색') }}" style="margin: 24px 0;">
    <form method="GET" action="{{ front_route('search.index') }}" class="board-search-form">
        <label for="site-search-q" class="sr-only">{{ __('검색어') }}</label>
        <input type="text" id="site-search-q" name="q" placeholder="{{ __('검색어 입력') }}">
        <button type="submit" class="btn btn-primary">{{ __('검색') }}</button>
    </form>
</section>
