<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Kakao\KakaoExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Naver\NaverExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.custom');
        Paginator::defaultSimpleView('vendor.pagination.custom');

        // 구글은 Socialite에 기본 내장되어 있지만, 카카오/네이버는 커뮤니티 드라이버 패키지가
        // 필요해 각 패키지가 제공하는 리스너로 등록해야 Socialite::driver('kakao'|'naver')를 쓸 수 있다.
        Event::listen(SocialiteWasCalled::class, KakaoExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, NaverExtendSocialite::class);

        $this->app->make(AdminAuditLogService::class)->register();

        // 관리자 패널(Filament) 로그인은 프론트 LoginController를 거치지 않고 자체 로그인 화면을
        // 쓰기 때문에, 프론트 로그인에서만 last_login_at을 갱신하던 기존 코드로는 관리자 계정의
        // "최근 로그인"이 영영 갱신되지 않았다. Login 이벤트는 가드와 무관하게 로그인 성공 시
        // 항상 발생하므로 여기서 관리자만 잡아 한 번에 처리한다(일반회원은 LoginController/
        // SocialLoginController가 휴면계정 예외 처리까지 포함해 이미 올바르게 갱신하고 있어 건드리지 않음).
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && $event->user->level === User::LEVEL_ADMIN) {
                $event->user->forceFill(['last_login_at' => now()])->save();
            }
        });
    }
}
