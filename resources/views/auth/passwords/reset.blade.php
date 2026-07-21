@extends('layouts.app')

@section('content')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('새 비밀번호 설정') }}</h1>

        <form method="POST" action="{{ front_route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">{{ __('이메일') }}</label>
                <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus autocomplete="email">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="password">{{ __('새 비밀번호') }}</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="password-confirm">{{ __('새 비밀번호 확인') }}</label>
                <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('비밀번호 재설정') }}</button>
            </div>
        </form>
    </div>
@endsection
