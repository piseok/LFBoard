@php
    $breadcrumbTrail = app(\App\Services\MenuService::class)->getBreadcrumbTrail(request()->path(), auth()->user()?->level ?? \App\Models\User::LEVEL_GUEST);
@endphp
@if (! empty($breadcrumbTrail))
    <nav class="breadcrumb" aria-label="{{ __('현재 위치') }}">
        <ol>
            <li><a href="{{ front_route('home') }}">{{ __('홈') }}</a></li>
            @foreach ($breadcrumbTrail as $i => $crumb)
                <li>
                    @if ($i === count($breadcrumbTrail) - 1)
                        <span aria-current="page">{{ $crumb['title'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
