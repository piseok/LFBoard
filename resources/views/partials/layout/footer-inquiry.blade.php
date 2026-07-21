@php
    $siteSettings = app(\App\Services\SiteSettingService::class);
    $categories = $siteSettings->getInquiryCategories();
@endphp
<div class="container">
    <section class="footer-inquiry-form" aria-labelledby="footer-inquiry-heading">
        <h2 id="footer-inquiry-heading" style="font-size:1.1rem;margin:0 0 12px;">{{ __('빠른 상담 신청') }}</h2>

        <form method="POST" action="{{ front_route('inquiry.store') }}">
            @csrf
            <input type="hidden" name="type" value="footer">

            <div class="form-group">
                <label for="footer-inquiry-name">{{ __('이름') }} <span aria-hidden="true">*</span></label>
                <input type="text" id="footer-inquiry-name" name="name" required maxlength="50" value="{{ old('name') }}">
            </div>

            <div class="form-group">
                <label for="footer-inquiry-phone">{{ __('연락처') }}</label>
                <input type="tel" id="footer-inquiry-phone" name="phone" maxlength="20" value="{{ old('phone') }}">
            </div>

            @if ($categories)
                <div class="form-group">
                    <label for="footer-inquiry-category">{{ __('문의 카테고리') }}</label>
                    <select id="footer-inquiry-category" name="category">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="form-group">
                <label for="footer-inquiry-title">{{ __('제목') }} <span aria-hidden="true">*</span></label>
                <input type="text" id="footer-inquiry-title" name="title" required maxlength="255" value="{{ old('title') }}">
            </div>

            <div class="form-group">
                <label for="footer-inquiry-content">{{ __('내용') }} <span aria-hidden="true">*</span></label>
                <textarea id="footer-inquiry-content" name="content" required rows="4">{{ old('content') }}</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ __('상담 신청') }}</button>
            </div>
        </form>
    </section>
</div>
