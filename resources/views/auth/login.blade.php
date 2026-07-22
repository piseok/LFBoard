@extends('layouts.app')

@php
    $settings = app(\App\Services\SiteSettingService::class);
    $loginField = $settings->get('login_type', 'email') === 'username' ? 'username' : 'email';
    $captchaEnabled = $settings->get('captcha_apply_login') === '1' && ! empty($settings->get('captcha_provider'));
@endphp

@section('content')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('로그인') }}</h1>

        <form method="POST" action="{{ front_route('login') }}">
            @csrf

            <div class="form-group">
                <label for="login-field">{{ $loginField === 'username' ? __('아이디') : __('이메일') }}</label>
                <input id="login-field" type="{{ $loginField === 'username' ? 'text' : 'email' }}"
                       name="{{ $loginField }}" value="{{ old($loginField) }}" required autofocus
                       autocomplete="{{ $loginField === 'username' ? 'username' : 'email' }}">
                @error($loginField)<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="password">{{ __('비밀번호') }}</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" style="width:auto;" @checked(old('remember'))>
                    {{ __('로그인 상태 유지') }}
                </label>
            </div>

            @if ($captchaEnabled)
                @include('partials.auth.captcha')
            @endif

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('로그인') }}</button>
                @if (Route::has(\App\Models\Language::routeNamePrefix().'find-id'))
                    <a href="{{ front_route('find-id') }}" class="btn">{{ __('아이디 찾기') }}</a>
                @endif
                @if (Route::has(\App\Models\Language::routeNamePrefix().'password.request'))
                    <a href="{{ front_route('password.request') }}" class="btn">{{ __('비밀번호 찾기') }}</a>
                @endif
                @if (Route::has(\App\Models\Language::routeNamePrefix().'register'))
                    <a href="{{ front_route('register') }}" class="btn">{{ __('회원가입') }}</a>
                @endif
            </div>
        </form>

        @include('partials.auth.social-login')
    </div>
@endsection
