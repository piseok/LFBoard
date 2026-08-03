@extends('layouts.app')

@section('content')
    <x-sub-header :title="__('마이페이지')" />

    <div style="max-width: 480px;">
        <table class="board-list">
            <caption class="sr-only">{{ __('내 계정 정보') }}</caption>
            <tbody>
                <tr>
                    <th scope="row" style="text-align:left; width: 120px;">{{ __('이름') }}</th>
                    <td>{{ $user->name }}</td>
                </tr>
                @if ($user->nickname)
                    <tr>
                        <th scope="row" style="text-align:left;">{{ __('닉네임') }}</th>
                        <td>{{ $user->nickname }}</td>
                    </tr>
                @endif
                @if ($user->username)
                    <tr>
                        <th scope="row" style="text-align:left;">{{ __('아이디') }}</th>
                        <td>{{ $user->username }}</td>
                    </tr>
                @endif
                <tr>
                    <th scope="row" style="text-align:left;">{{ __('이메일') }}</th>
                    <td>{{ $user->email }}</td>
                </tr>
                @if ($user->phone)
                    <tr>
                        <th scope="row" style="text-align:left;">{{ __('전화번호') }}</th>
                        <td>{{ $user->phone }}</td>
                    </tr>
                @endif
                <tr>
                    <th scope="row" style="text-align:left;">{{ __('가입일') }}</th>
                    <td>{{ local_datetime($user->created_at)->format('Y-m-d') }}</td>
                </tr>
                <tr>
                    <th scope="row" style="text-align:left;">{{ __('마지막 비밀번호 변경') }}</th>
                    <td>
                        @if ($monthsSincePasswordChange === null)
                            {{ __('변경 기록 없음') }}
                        @else
                            {{ local_datetime($user->password_changed_at)->format('Y-m-d') }} {{ __(':months개월 전', ['months' => $monthsSincePasswordChange]) }}
                            @if ($passwordChangeOverdue)
                                <span class="badge badge-danger">{{ __('변경 권장') }}</span>
                            @endif
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="form-actions">
            <a href="{{ front_route('mypage.edit') }}" class="btn">{{ __('회원정보 수정') }}</a>
            <a href="{{ front_route('inquiry.index') }}" class="btn">{{ __('나의 1:1 상담 내역') }}</a>
            <a href="{{ front_route('mypage.password.edit') }}" class="btn">{{ __('비밀번호 변경') }}</a>
        </div>

        <details style="margin-top: 32px;">
            <summary class="btn-link btn-link-danger" style="cursor: pointer;">{{ __('회원 탈퇴') }}</summary>
            <form method="POST" action="{{ front_route('mypage.destroy') }}" style="margin-top: 12px;"
                  data-confirm="{{ __('정말로 탈퇴하시겠습니까? 작성하신 게시글/댓글은 남지만 탈퇴한 회원으로 표시되며, 이 작업은 되돌릴 수 없습니다.') }}">
                @csrf
                @method('DELETE')
                <div class="form-group">
                    <label for="withdraw-password">{{ __('현재 비밀번호 확인') }}</label>
                    <input id="withdraw-password" type="password" name="password" required autocomplete="current-password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-danger">{{ __('탈퇴하기') }}</button>
            </form>
        </details>
    </div>
@endsection
