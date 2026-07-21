@extends('layouts.app')

@section('content')
    <div style="max-width: 420px; margin: 0 auto;">
        <h1 class="page-title">{{ __('휴면 계정 안내') }}</h1>

        <p>{{ __('장기간 로그인 기록이 없어 계정이 휴면 상태로 전환되었습니다.') }}</p>
        <p>{{ __('비밀번호 확인이 완료된 상태이니, 아래 버튼을 눌러 휴면 상태를 해제해 주세요.') }}</p>

        <form method="POST" action="{{ route('dormant.reactivate') }}" style="margin-top: 1.5rem;">
            @csrf

            @if ($requiresSms)
                <p style="color: var(--color-gray-600, #6b7280);">{{ __('SMS 인증이 추가로 필요합니다. 먼저 인증번호를 받아주세요.') }}</p>

                <div class="form-actions" style="margin-bottom: 1rem;">
                    <button type="submit" form="dormant-send-code-form" class="btn">{{ __('인증번호 받기') }}</button>
                </div>

                <div class="form-group">
                    <label for="sms_code">{{ __('인증번호') }}</label>
                    <input id="sms_code" type="text" name="sms_code" required autocomplete="one-time-code">
                    @error('sms_code')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('휴면 해제하고 계속하기') }}</button>
                <a href="{{ front_route('login') }}" class="btn">{{ __('취소') }}</a>
            </div>
        </form>

        @if ($requiresSms)
            <form id="dormant-send-code-form" method="POST" action="{{ route('dormant.send-sms-code') }}">
                @csrf
            </form>
        @endif
    </div>
@endsection
