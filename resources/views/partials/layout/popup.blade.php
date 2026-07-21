@php
    $popups = app(\App\Services\PopupService::class)->getActive();
@endphp
@if ($popups->isNotEmpty())
    @foreach ($popups as $popup)
        <div
            class="site-popup"
            id="site-popup-{{ $popup->id }}"
            data-popup-id="{{ $popup->id }}"
            data-position="{{ $popup->position }}"
            style="width: {{ $popup->width }}px; max-width: 90vw; display:none;"
            role="dialog"
            aria-modal="false"
            aria-label="{{ $popup->title }}"
        >
            <div class="site-popup-header">
                <button type="button" class="btn btn-sm popup-close-btn" aria-label="{{ __(':title 닫기', ['title' => $popup->title]) }}">✕</button>
            </div>
            <div class="site-popup-body" style="max-height: {{ $popup->height }}px;">
                @if ($popup->content_type === 'image' && $popup->image_path)
                    <img src="{{ url($popup->image_path) }}" alt="{{ $popup->title }}">
                @else
                    {!! $popup->html_content !!}
                @endif
            </div>
            <div class="site-popup-footer">
                <label>
                    <input type="checkbox" class="popup-hide-today">
                    {{ __('오늘 하루 보지 않기') }}
                </label>
            </div>
        </div>
    @endforeach
@endif
