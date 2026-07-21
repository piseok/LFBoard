@extends('layouts.app')

@section('content')
    <h1 class="page-title">{{ __('비밀번호 변경') }}</h1>

    <div style="max-width: 420px;">
        <form method="POST" action="{{ front_route('mypage.password.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="current_password">{{ __('현재 비밀번호') }}</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="password">{{ __('새 비밀번호') }}</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">{{ __('새 비밀번호 확인') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('변경하기') }}</button>
                <a href="{{ front_route('mypage') }}" class="btn">{{ __('취소') }}</a>
            </div>
        </form>
    </div>
@endsection
