@extends('layouts.app')

@php
    $policyRoutes = ['terms' => 'policy.terms', 'privacy' => 'policy.privacy', 'marketing' => 'policy.marketing'];
@endphp

@section('content')
    <div style="max-width: 480px; margin: 0 auto;">
        <h1 class="page-title">{{ __('약관 재동의') }}</h1>

        <p class="post-meta">{{ __('약관이 변경되어 다시 동의해 주셔야 서비스를 계속 이용하실 수 있습니다.') }}</p>

        <form method="POST" action="{{ front_route('policy-consent.store') }}">
            @csrf

            <div class="form-group">
                @foreach ($policies as $policy)
                    <div>
                        <label>
                            <input type="checkbox" name="policy_{{ $policy->type }}" value="1" style="width:auto;" @checked(old('policy_'.$policy->type)) required>
                            @if (isset($policyRoutes[$policy->type]) && Route::has(\App\Models\Language::routeNamePrefix().$policyRoutes[$policy->type]))
                                <a href="{{ front_route($policyRoutes[$policy->type]) }}" target="_blank" rel="noopener">{{ $policy->title }}</a>
                            @else
                                {{ $policy->title }}
                            @endif
                            {{ __('(필수)') }}
                        </label>
                    </div>
                    @error('policy_'.$policy->type)<p class="field-error">{{ $message }}</p>@enderror
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('동의하고 계속하기') }}</button>
            </div>
        </form>
    </div>
@endsection
