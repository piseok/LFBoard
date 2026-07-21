@extends('layouts.app')

@php
    $fieldLabels = [
        'nickname' => __('닉네임'), 'phone' => __('전화번호'), 'gender' => __('성별'),
        'birthdate' => __('생년월일'), 'homepage' => __('홈페이지'), 'address' => __('주소'),
    ];
    $identityLockedFields = ['phone', 'gender', 'birthdate'];
    $genderLabels = ['male' => __('남성'), 'female' => __('여성')];
@endphp

@section('content')
    <div style="max-width: 480px; margin: 0 auto;">
        <h1 class="page-title">{{ __('회원정보 수정') }}</h1>

        @if ($identityLocked)
            <div class="form-group" style="background: var(--color-gray-50, #f9fafb); padding: 16px; border-radius: 8px;">
                <p style="margin: 0 0 8px; font-weight: 600;">{{ __('본인인증 정보') }}</p>
                <p style="margin: 0 0 12px; color: var(--color-gray-600, #6b7280); font-size: 0.9rem;">
                    {{ __('이름/전화번호/성별/생년월일은 본인인증을 통해 확인된 정보라 직접 수정할 수 없습니다. 정보가 바뀌었다면 본인인증을 다시 진행해 주세요.') }}
                </p>
                <table class="board-list">
                    <tbody>
                        <tr>
                            <th scope="row" style="text-align:left; width: 100px;">{{ __('이름') }}</th>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <th scope="row" style="text-align:left;">{{ __('전화번호') }}</th>
                            <td>{{ $user->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" style="text-align:left;">{{ __('성별') }}</th>
                            <td>{{ $genderLabels[$user->gender] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" style="text-align:left;">{{ __('생년월일') }}</th>
                            <td>{{ $user->birthdate?->format('Y-m-d') ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="form-actions" style="margin-top: 12px;">
                    <a href="{{ route('identity-verification.start') }}" class="btn">{{ __('본인인증 다시하기') }}</a>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ front_route('mypage.update') }}" style="margin-top: 20px;">
            @csrf
            @method('PUT')

            @unless ($identityLocked)
                <div class="form-group">
                    <label for="name">{{ __('이름') }}</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="50" autocomplete="name">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endunless

            @foreach ($fieldModes as $field => $mode)
                @continue($mode === 'hidden')
                @continue($identityLocked && in_array($field, $identityLockedFields, true))
                <div class="form-group">
                    <label for="{{ $field }}">{{ $fieldLabels[$field] }}@if ($mode === 'required')<span aria-hidden="true"> *</span>@endif</label>
                    @if ($field === 'gender')
                        <select id="gender" name="gender" @required($mode === 'required')>
                            <option value="">{{ __('선택 안 함') }}</option>
                            <option value="male" @selected(old('gender', $user->gender) === 'male')>{{ __('남성') }}</option>
                            <option value="female" @selected(old('gender', $user->gender) === 'female')>{{ __('여성') }}</option>
                        </select>
                    @elseif ($field === 'birthdate')
                        <input id="birthdate" type="date" name="birthdate" value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}" @required($mode === 'required')>
                    @else
                        <input id="{{ $field }}" type="text" name="{{ $field }}" value="{{ old($field, $user->{$field}) }}" maxlength="255" @required($mode === 'required')>
                    @endif
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('저장') }}</button>
                <a href="{{ front_route('mypage') }}" class="btn">{{ __('취소') }}</a>
            </div>
        </form>
    </div>
@endsection
