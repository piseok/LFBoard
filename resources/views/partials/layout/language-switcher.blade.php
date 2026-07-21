@php
    $localeLinks = app(\App\Services\LocaleSwitchService::class)->links(request());
@endphp
@if (count($localeLinks) > 1)
    <div class="language-switcher" role="group" aria-label="언어 선택">
        @foreach ($localeLinks as $link)
            <a
                href="{{ $link['url'] }}"
                class="language-switcher-item{{ $link['is_current'] ? ' is-active' : '' }}"
                @if ($link['is_current']) aria-current="true" @endif
            >{{ $link['name'] }}</a>
        @endforeach
    </div>
@endif
