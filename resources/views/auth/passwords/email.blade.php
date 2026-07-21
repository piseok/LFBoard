@extends('layouts.app')

@section('content')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('비밀번호 재설정') }}</h1>
        <p class="post-meta">{{ __('가입 시 등록한 이메일로 재설정 링크를 보내드립니다.') }}</p>

        <form method="POST" action="{{ front_route('password.email') }}">
            @csrf

            <div class="form-group">
                <label for="email">{{ __('이메일') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('재설정 링크 보내기') }}</button>
            </div>
        </form>
    </div>
@endsection
