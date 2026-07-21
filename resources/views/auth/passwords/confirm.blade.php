@extends('layouts.app')

@section('content')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('비밀번호 확인') }}</h1>
        <p class="post-meta">{{ __('계속 진행하려면 비밀번호를 다시 입력해 주세요.') }}</p>

        <form method="POST" action="{{ front_route('password.confirm') }}">
            @csrf

            <div class="form-group">
                <label for="password">{{ __('비밀번호') }}</label>
                <input id="password" type="password" name="password" required autofocus autocomplete="current-password">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('확인') }}</button>
                @if (Route::has(\App\Models\Language::routeNamePrefix().'password.request'))
                    <a href="{{ front_route('password.request') }}" class="btn">{{ __('비밀번호를 잊으셨나요?') }}</a>
                @endif
            </div>
        </form>
    </div>
@endsection
