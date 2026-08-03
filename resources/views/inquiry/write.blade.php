@extends('layouts.subpage')

@section('subcontent')
    <x-sub-header :title="__('1:1 상담 작성')" />

    <form method="POST" action="{{ front_route('inquiry.store') }}" enctype="multipart/form-data" style="max-width: 560px;">
        @csrf
        <input type="hidden" name="type" value="{{ old('type', $defaultType) }}">

        <div class="form-group">
            <label for="name">{{ __('이름') }}</label>
            <input type="text" id="name" name="name" maxlength="50" required value="{{ old('name', auth()->user()->name ?? '') }}">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">{{ __('이메일') }}</label>
                <input type="email" id="email" name="email" maxlength="255" value="{{ old('email', auth()->user()->email ?? '') }}">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label for="phone">{{ __('연락처') }}</label>
                <input type="tel" id="phone" name="phone" maxlength="20" value="{{ old('phone') }}">
            </div>
        </div>

        @if (! empty($categories))
            <div class="form-group">
                <label for="category">{{ __('문의 카테고리') }}</label>
                <select id="category" name="category">
                    <option value="">{{ __('선택 안 함') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @guest
            <div class="form-group">
                <label for="author_password">{{ __('비밀번호') }}</label>
                <input type="password" id="author_password" name="author_password" maxlength="20" required>
                <p class="hint">{{ __('접수 내역 조회 시 필요합니다.') }}</p>
                @error('author_password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endguest

        <div class="form-group">
            <label for="title">{{ __('제목') }}</label>
            <input type="text" id="title" name="title" maxlength="255" required value="{{ old('title') }}">
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="content">{{ __('내용') }}</label>
            <textarea id="content" name="content" rows="8" required>{{ old('content') }}</textarea>
            @error('content')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="file">{{ __('첨부파일') }}</label>
            <input type="file" id="file" name="file">
            @error('file')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        @guest
            @php
                $inquiryCaptchaSettings = app(\App\Services\SiteSettingService::class);
                $inquiryCaptchaEnabled = $inquiryCaptchaSettings->get('captcha_apply_inquiry') === '1'
                    && ! empty($inquiryCaptchaSettings->get('captcha_provider'));
            @endphp
            @if ($inquiryCaptchaEnabled)
                @include('partials.auth.captcha')
            @endif
        @endguest

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('상담 신청') }}</button>
            <a href="{{ front_route('inquiry.index') }}" class="btn">{{ __('취소') }}</a>
        </div>
    </form>
@endsection
