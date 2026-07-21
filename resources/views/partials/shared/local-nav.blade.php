@php
    $localNavBranch = app(\App\Services\MenuService::class)->getActiveBranch(request()->path(), auth()->user()?->level ?? \App\Models\User::LEVEL_GUEST);
    $localNavTop = $localNavBranch['top'];
    $localNavActiveIds = $localNavBranch['activeIds'];
@endphp
@if ($localNavTop && ! empty($localNavTop['children']))
    <nav class="local-nav" aria-label="{{ __(':title 하위 메뉴', ['title' => $localNavTop['title']]) }}">
        <h2 class="local-nav-title">{{ $localNavTop['title'] }}</h2>
        <ul>
            @foreach ($localNavTop['children'] as $item)
                @php
                    $isActive = in_array($item['id'], $localNavActiveIds, true);
                    $hasChildren = ! empty($item['children']);
                @endphp
                <li class="{{ $isActive ? 'is-active' : '' }}">
                    <div class="local-nav-item">
                        <a href="{{ $item['url'] }}" target="{{ $item['target'] }}"
                           class="{{ $item['locked'] ? 'is-locked' : '' }}"
                           @if ($isActive) aria-current="page" @endif
                           @if ($item['locked']) title="{{ __('로그인 또는 등급 상향 후 이용 가능합니다') }}" @endif
                        >{{ $item['title'] }}@if ($item['locked'])<svg class="menu-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@endif</a>
                        @if ($hasChildren)
                            <button type="button" class="submenu-toggle"
                                    aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                                    aria-controls="lnb-{{ $item['id'] }}">
                                <span class="sr-only">{{ __(':title 하위 메뉴 펼치기', ['title' => $item['title']]) }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        @endif
                    </div>
                    @if ($hasChildren)
                        <ul id="lnb-{{ $item['id'] }}" class="local-nav-sub {{ $isActive ? '' : 'is-hidden' }}">
                            @foreach ($item['children'] as $sub)
                                @php $subActive = in_array($sub['id'], $localNavActiveIds, true); @endphp
                                <li class="{{ $subActive ? 'is-active' : '' }}">
                                    <a href="{{ $sub['url'] }}" target="{{ $sub['target'] }}"
                                       class="{{ $sub['locked'] ? 'is-locked' : '' }}"
                                       @if ($subActive) aria-current="page" @endif
                                       @if ($sub['locked']) title="{{ __('로그인 또는 등급 상향 후 이용 가능합니다') }}" @endif
                                    >{{ $sub['title'] }}@if ($sub['locked'])<svg class="menu-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@endif</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
