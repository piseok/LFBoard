<?php

use App\Http\Controllers\Auth\DormantAccountController;
use App\Http\Controllers\Auth\FindIdController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BoardFrontController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\PasswordReminderController;
use App\Http\Controllers\PolicyConsentController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Models\Language;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// 설치 전(마이그레이션 실행 전 등)에는 languages 테이블이 없을 수 있어 방어적으로 처리.
// 아래 여러 라우트 그룹(인증/프론트)이 공통으로 쓰므로 파일 앞쪽에서 한 번만 계산한다.
$additionalLocales = [];
try {
    if (Schema::hasTable('languages')) {
        $additionalLocales = Language::query()->where('is_active', true)->where('is_default', false)->pluck('code')->all();
    }
} catch (Throwable) {
    $additionalLocales = [];
}

// 이메일 인증 활성 여부와 무관하게 라우트 자체는 항상 등록해 둔다.
// 실제 발송 여부는 User::sendEmailVerificationNotification()에서 site_settings로 제어한다.
// 다국어 지원: 로그인/회원가입 등도 언어 접두사가 없으면 소셜 로그인처럼 "로그인 후 항상 기본
// 언어로 돌아가는" 문제가 생기므로, 프론트 라우트와 동일하게 기본 언어+활성 언어별로 중복 등록한다.
$authExtraRoutes = function () {
    Auth::routes(['verify' => true]);
    Route::get('/find-id', [FindIdController::class, 'create'])->name('find-id');
    Route::post('/find-id', [FindIdController::class, 'store'])->middleware('throttle:5,1')->name('find-id.submit');
};

Route::middleware('locale')->group($authExtraRoutes);

foreach ($additionalLocales as $localeCode) {
    Route::prefix($localeCode)->name("{$localeCode}.")->middleware('locale')
        ->group($authExtraRoutes);
}

// 소셜 로그인 — 지원 공급사가 아니거나 키가 설정되지 않은 경우 컨트롤러 내부에서 404 처리.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
});

// 휴면계정 로그인 시 안내/해제 — 로그인 시도에서 이미 비밀번호(또는 소셜 인증)로 1차 확인이 끝난
// 상태에서만 세션에 대상 회원 id가 심어지므로, 여기서는 별도 auth 미들웨어 없이 세션값으로만 판별한다.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/dormant/notice', [DormantAccountController::class, 'notice'])->name('dormant.notice');
    Route::post('/dormant/send-sms-code', [DormantAccountController::class, 'sendSmsCode'])->name('dormant.send-sms-code');
    Route::post('/dormant/reactivate', [DormantAccountController::class, 'reactivate'])->name('dormant.reactivate');
});

// 본인인증 — 게시판 글쓰기 등에서 로그인 회원을 대상으로 진행(비회원은 우선 로그인부터).
Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::get('/identity-verification/start', [IdentityVerificationController::class, 'start'])->name('identity-verification.start');
    Route::match(['get', 'post'], '/identity-verification/callback', [IdentityVerificationController::class, 'callback'])->name('identity-verification.callback');
});

// 방문자 통계는 관리자 패널(/admin)을 제외한 프론트 전체에 적용한다.
// 다국어 지원: 아래 정의를 기본 언어(접두사 없음)와 활성화된 추가 언어(예: /ja/...) 양쪽에
// "같은 라우트 정의 재사용"으로 두 번 등록한다(컨트롤러 코드 중복 없음, 언어 감지는 DetectLocale 미들웨어).
// 접두사가 붙는 언어 그룹은 라우트 이름도 접두사(예: ja.home)를 붙여 이름 충돌을 피한다.
$frontRoutes = function () {
    Route::get('/', [FrontController::class, 'index'])->name('home');
    Route::get('/page/{slug}', [FrontController::class, 'page'])->name('page.show');
    Route::get('/sitemap', [FrontController::class, 'sitemap'])->name('sitemap');

    Route::get('/board/{slug}', [BoardFrontController::class, 'index'])->name('board.index');
    // 쓰기 권한은 게시판별 min_write_level/allow_anonymous 설정에 따라 컨트롤러 내부에서 동적으로 판단한다
    // (게시판마다 다르므로 고정된 auth.level 미들웨어를 걸 수 없음).
    Route::get('/board/{slug}/write', [BoardFrontController::class, 'create'])->name('board.create');
    Route::post('/board/{slug}/write', [BoardFrontController::class, 'store'])->name('board.store');
    Route::post('/board/{slug}/upload-image', [BoardFrontController::class, 'uploadImage'])->middleware('throttle:20,1')->name('board.upload-image');
    Route::get('/board/{slug}/{id}', [BoardFrontController::class, 'show'])->whereNumber('id')->name('board.show');
    Route::get('/board/{slug}/{id}/edit', [BoardFrontController::class, 'edit'])->whereNumber('id')->name('board.edit');
    Route::put('/board/{slug}/{id}', [BoardFrontController::class, 'update'])->whereNumber('id')->name('board.update');
    Route::delete('/board/{slug}/{id}', [BoardFrontController::class, 'destroy'])->whereNumber('id')->name('board.destroy');
    // 비회원 비밀번호 확인/검증 엔드포인트는 무차별 대입(brute-force) 시도를 막기 위해 요청 빈도를 제한한다.
    Route::post('/board/{slug}/{id}/verify', [BoardFrontController::class, 'verifyPassword'])->whereNumber('id')->middleware('throttle:10,1')->name('board.verify');

    Route::post('/board/{slug}/{id}/comment', [CommentController::class, 'store'])->name('comment.store');
    Route::delete('/comment/{id}', [CommentController::class, 'destroy'])->middleware('throttle:10,1')->name('comment.destroy');

    Route::get('/inquiry', [InquiryController::class, 'index'])->name('inquiry.index');
    Route::get('/inquiry/write', [InquiryController::class, 'create'])->name('inquiry.create');
    Route::post('/inquiry', [InquiryController::class, 'store'])->name('inquiry.store');
    Route::get('/inquiry/{id}', [InquiryController::class, 'show'])->whereNumber('id')->name('inquiry.show');
    Route::post('/inquiry/{id}/verify', [InquiryController::class, 'verifyPassword'])->whereNumber('id')->middleware('throttle:10,1')->name('inquiry.verify');

    Route::get('/mypage', [MyPageController::class, 'index'])->middleware('auth')->name('mypage');
    Route::get('/mypage/edit', [MyPageController::class, 'edit'])->middleware('auth')->name('mypage.edit');
    Route::put('/mypage', [MyPageController::class, 'update'])->middleware(['auth', 'throttle:10,1'])->name('mypage.update');
    Route::delete('/mypage', [MyPageController::class, 'destroy'])->middleware(['auth', 'throttle:5,1'])->name('mypage.destroy');
    Route::get('/mypage/password', [MyPageController::class, 'editPassword'])->middleware('auth')->name('mypage.password.edit');
    Route::put('/mypage/password', [MyPageController::class, 'updatePassword'])->middleware(['auth', 'throttle:5,1'])->name('mypage.password.update');

    Route::get('/banner/click/{id}', [BannerController::class, 'click'])->name('banner.click');

    Route::get('/terms', [PolicyController::class, 'show'])->defaults('type', 'terms')->name('policy.terms');
    Route::get('/privacy', [PolicyController::class, 'show'])->defaults('type', 'privacy')->name('policy.privacy');
    Route::get('/marketing-policy', [PolicyController::class, 'show'])->defaults('type', 'marketing')->name('policy.marketing');
    Route::get('/email-collection-notice', [PolicyController::class, 'show'])->defaults('type', 'email_notice')->name('policy.email-notice');
    Route::get('/policy/{type}/change-notice', [PolicyController::class, 'changeNotice'])->name('policy.change-notice');

    Route::get('/media/download/{id}', [MediaController::class, 'download'])->name('media.download');
    Route::get('/unsubscribe/{token}', [MarketingController::class, 'unsubscribe'])->name('marketing.unsubscribe');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');

    Route::middleware('auth')->group(function () {
        Route::get('/policy-consent', [PolicyConsentController::class, 'show'])->name('policy-consent.show');
        Route::post('/policy-consent', [PolicyConsentController::class, 'store'])->name('policy-consent.store');
        Route::get('/password-reminder', [PasswordReminderController::class, 'show'])->name('password-reminder.show');
        Route::post('/password-reminder/dismiss', [PasswordReminderController::class, 'dismiss'])->name('password-reminder.dismiss');
    });
};

Route::middleware(['record.visit', 'locale', 'policy.consent', 'password.reminder'])->group($frontRoutes);

foreach ($additionalLocales as $localeCode) {
    Route::prefix($localeCode)->name("{$localeCode}.")->middleware(['record.visit', 'locale', 'policy.consent', 'password.reminder'])->group($frontRoutes);
}

// SEO 라우트는 방문 기록 대상에서 제외. sitemap.xml은 언어별 URL을 섞지 않기 위해
// "인덱스(전체 언어 목록) + 언어별 개별 사이트맵" 구조로 분리한다(19번, front_route()류의
// 언어 접두사 URL 그룹 등록 대신 언어를 파일명 세그먼트로 명시 — 크롤러 입장에서 더 명확함).
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('seo.sitemap');
Route::get('/sitemap-{locale}.xml', [SeoController::class, 'sitemap'])->where('locale', '[a-z]{2,10}')->name('seo.sitemap.locale');
