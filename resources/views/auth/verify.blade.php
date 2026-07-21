@extends('layouts.app')

@section('content')
    <div style="max-width: 480px; margin: 0 auto;">
        <h1 class="page-title">{{ __('이메일 인증') }}</h1>

        @if (session('resent'))
            <div class="alert alert-success" role="alert">{{ __('인증 메일을 새로 발송했습니다.') }}</div>
        @endif

        <p>{{ __('가입하신 이메일로 인증 링크를 보내드렸습니다. 이메일을 확인해 인증을 완료해 주세요.') }}</p>
        <p>
            {{ __('메일을 받지 못하셨나요?') }}
            <form method="POST" action="{{ front_route('verification.resend') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-link">{{ __('인증 메일 다시 받기') }}</button>
            </form>
        </p>
    </div>
@endsection
