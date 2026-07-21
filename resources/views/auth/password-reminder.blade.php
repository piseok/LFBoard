@extends('layouts.app')

@section('content')
    <div style="max-width: 480px; margin: 0 auto;">
        <h1 class="page-title">{{ __('비밀번호 변경 안내') }}</h1>

        <p class="post-meta">{{ __('비밀번호를 변경하신 지 오래되었습니다. 안전한 계정 관리를 위해 지금 변경하시는 것을 권장합니다.') }}</p>

        <div class="form-actions">
            <a href="{{ front_route('mypage.password.edit') }}" class="btn btn-primary">{{ __('지금 변경하기') }}</a>
            <form method="POST" action="{{ front_route('password-reminder.dismiss') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn">{{ __('나중에 하기') }}</button>
            </form>
        </div>
    </div>
@endsection
