{{-- partials/home/banner-tabs.blade.php의 .main-cases-card 안쪽 콘텐츠 — 링크 유무에 따라
     <a>/<div> 두 군데에서 똑같이 쓰여서 별도 partial로 분리했다. --}}
@if ($banner->content_type === 'html')
    {!! $banner->html_content !!}
@elseif ($banner->image_path)
    <img src="{{ url($banner->image_path) }}" alt="{{ $banner->alt_text ?: $banner->title }}">
@else
    <div class="main-cases-card-noimage">{{ __('No Image') }}</div>
@endif
