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
            style="width: {{ $popup->width }}px; max-width: 90vw;"
            role="dialog"
            aria-modal="false"
            aria-label="{{ $popup->title }}"
        >
            <button type="button" class="btn btn-sm popup-close-btn" aria-label="{{ __(':title 닫기', ['title' => $popup->title]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" fill="none">
                <path d="M7.28065 0.209117C7.41461 0.343072 7.48986 0.524754 7.48986 0.714194C7.48986 0.903634 7.41461 1.08532 7.28065 1.21927L4.75527 3.74465L7.28065 6.27003C7.41461 6.40399 7.48986 6.58567 7.48986 6.77511C7.48986 6.96455 7.41461 7.14623 7.28065 7.28018C7.1467 7.41414 6.96501 7.48939 6.77557 7.48939C6.58613 7.48939 6.40445 7.41414 6.2705 7.28019L3.74512 4.7548L1.21974 7.28019C1.08578 7.41414 0.9041 7.48939 0.71466 7.48939C0.525219 7.48939 0.343538 7.41414 0.209584 7.28018C0.0756292 7.14623 0.000374145 6.96455 0.000374482 6.77511C0.000374145 6.58567 0.0756292 6.40399 0.209583 6.27003L2.73496 3.74465L0.209584 1.21927C0.075629 1.08532 0.000374124 0.903634 0.000374113 0.714194C0.000374097 0.524754 0.0756289 0.343072 0.209584 0.209117C0.343538 0.075163 0.525219 -9.17522e-05 0.71466 -9.17364e-05C0.9041 -9.17259e-05 1.08578 0.0751631 1.21974 0.209118L3.74512 2.7345L6.2705 0.209118C6.40445 0.0751631 6.58613 -9.17259e-05 6.77557 -9.17364e-05C6.96502 -9.17522e-05 7.1467 0.075163 7.28065 0.209117Z" fill="currentColor"/>
                </svg>
            </button>
            
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
