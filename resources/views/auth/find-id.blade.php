@extends('layouts.subpage')

@section('subcontent')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('아이디 찾기') }}</h1>
        <p class="post-meta">{{ __('가입 시 등록한 이름과 이메일로 아이디 안내 메일을 보내드립니다.') }}</p>

        <form method="POST" action="{{ front_route('find-id.submit') }}">
            @csrf

            <div class="form-group">
                <label for="name">{{ __('이름') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="50" autofocus autocomplete="name">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label for="email">{{ __('이메일') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('아이디 찾기') }}</button>
                <a href="{{ front_route('login') }}" class="btn">{{ __('로그인으로') }}</a>
            </div>
        </form>
    </div>
@endsection
