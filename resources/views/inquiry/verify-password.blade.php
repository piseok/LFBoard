@extends('layouts.app')

@section('content')
    <div class="container verfiy-pw-form">
        <x-sub-header :title="__('비밀번호 확인')" :description="__('상담 신청 시 입력한 비밀번호를 입력해 주세요.')" />
        
        <form method="POST" action="{{ front_route('inquiry.verify', $id) }}">
            @csrf
            <div class="form-group">
                <label for="author_password">{{ __('비밀번호') }}</label>
                <input type="password" id="author_password" name="author_password" maxlength="20" required autofocus>
                @error('author_password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width: 100%;">{{ __('확인') }}</button>
            </div>
        </form>
    </div>
@endsection
