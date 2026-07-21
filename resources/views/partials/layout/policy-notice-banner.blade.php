@php
    $pendingPolicies = \App\Models\Policy::pendingForLocale(app()->getLocale());
@endphp
@if ($pendingPolicies->isNotEmpty())
    <div class="policy-notice-banner" role="note">
        @foreach ($pendingPolicies as $policy)
            <p class="policy-notice-message">
                {{ $policy->title }} {{ __('변경 예정 안내') }} ({{ $policy->effective_at->format('Y-m-d') }} {{ __('시행') }})
                — <a href="{{ front_route('policy.change-notice', ['type' => $policy->type]) }}">{{ __('변경 내용 보기') }}</a>
            </p>
        @endforeach
    </div>
@endif
