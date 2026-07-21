@extends('layouts.app')

@section('content')
    <div style="max-width: 480px; margin: 0 auto; text-align: center;">
        <h1 class="page-title">{{ __('수신 거부') }}</h1>

        @if ($success)
            <p>{{ __('마케팅 정보 수신이 정상적으로 거부 처리되었습니다.') }}</p>
        @else
            <p>{{ __('유효하지 않은 수신 거부 링크입니다.') }}</p>
        @endif

        <a href="{{ front_route('home') }}" class="btn">{{ __('홈으로') }}</a>
    </div>
@endsection
