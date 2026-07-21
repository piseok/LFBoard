@extends('layouts.app')

@php
    $settings = app(\App\Services\SiteSettingService::class);
    $loginType = $settings->get('login_type', 'email');
    $fieldModes = collect(['nickname', 'phone', 'gender', 'birthdate', 'homepage', 'address'])
        ->mapWithKeys(fn ($field) => [$field => $settings->get("signup_field_{$field}", 'hidden')]);
    $fieldLabels = [
        'nickname' => __('닉네임'), 'phone' => __('전화번호'), 'gender' => __('성별'),
        'birthdate' => __('생년월일'), 'homepage' => __('홈페이지'), 'address' => __('주소'),
    ];
    $policyRoutes = ['terms' => 'policy.terms', 'privacy' => 'policy.privacy', 'marketing' => 'policy.marketing'];
    $policies = \App\Models\Policy::activeForLocale(app()->getLocale());
    $captchaEnabled = $settings->get('captcha_apply_signup') === '1' && ! empty($settings->get('captcha_provider'));
    $phoneCountryService = app(\App\Services\PhoneCountryService::class);
    $phoneCountries = $phoneCountryService->options();
    $defaultPhoneCountry = $phoneCountryService->defaultCode();
@endphp

@section('content')
    <div style="max-width: 480px; margin: 0 auto;">
        <h1 class="page-title">{{ __('회원가입') }}</h1>

        <form method="POST" action="{{ front_route('register') }}">
            @csrf

            <div class="form-group">
                <label for="name">{{ __('이름') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="50" autofocus autocomplete="name">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            @if ($loginType === 'username')
                <div class="form-group">
                    <label for="username">{{ __('아이디') }}</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="username">
                    @error('username')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="form-group">
                <label for="email">{{ __('이메일') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">{{ __('비밀번호') }}</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="password-confirm">{{ __('비밀번호 확인') }}</label>
                    <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            @foreach ($fieldModes as $field => $mode)
                @continue($mode === 'hidden')
                <div class="form-group">
                    <label for="{{ $field }}">{{ $fieldLabels[$field] }}@if ($mode === 'required')<span aria-hidden="true"> *</span>@endif</label>
                    @if ($field === 'gender')
                        <select id="gender" name="gender" @required($mode === 'required')>
                            <option value="">{{ __('선택 안 함') }}</option>
                            <option value="male" @selected(old('gender') === 'male')>{{ __('남성') }}</option>
                            <option value="female" @selected(old('gender') === 'female')>{{ __('여성') }}</option>
                        </select>
                    @elseif ($field === 'birthdate')
                        <input id="birthdate" type="date" name="birthdate" value="{{ old('birthdate') }}" @required($mode === 'required')>
                    @elseif ($field === 'phone')
                        <div class="form-row">
                            <select id="phone_country" name="phone_country" style="max-width: 200px;">
                                @foreach ($phoneCountries as $option)
                                    <option value="{{ $option['code'] }}" @selected(old('phone_country', $defaultPhoneCountry) === $option['code'])>
                                        {{ $option['name'] }} (+{{ $option['dial'] }})
                                    </option>
                                @endforeach
                            </select>
                            <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="255" placeholder="{{ __('숫자만 입력') }}" inputmode="tel" @required($mode === 'required')>
                        </div>
                        @error('phone_country')<p class="field-error">{{ $message }}</p>@enderror
                    @else
                        <input id="{{ $field }}" type="text" name="{{ $field }}" value="{{ old($field) }}" maxlength="255" @required($mode === 'required')>
                    @endif
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach

            @if ($policies->isNotEmpty())
                <div class="form-group">
                    <label>{{ __('약관 동의') }}</label>
                    @foreach ($policies as $policy)
                        <div>
                            <label>
                                <input type="checkbox" name="policy_{{ $policy->type }}" value="1" style="width:auto;" @checked(old('policy_'.$policy->type)) @required($policy->is_required)>
                                @if (isset($policyRoutes[$policy->type]) && Route::has(\App\Models\Language::routeNamePrefix().$policyRoutes[$policy->type]))
                                    <a href="{{ front_route($policyRoutes[$policy->type]) }}" target="_blank" rel="noopener">{{ $policy->title }}</a>
                                @else
                                    {{ $policy->title }}
                                @endif
                                {{ $policy->is_required ? __('(필수)') : __('(선택)') }}
                            </label>
                        </div>
                        @error('policy_'.$policy->type)<p class="field-error">{{ $message }}</p>@enderror
                    @endforeach
                </div>
            @endif

            @if ($captchaEnabled)
                @include('partials.auth.captcha')
            @endif

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('가입하기') }}</button>
                <a href="{{ front_route('login') }}" class="btn">{{ __('로그인으로') }}</a>
            </div>
        </form>

        @include('partials.auth.social-login')
    </div>
@endsection
