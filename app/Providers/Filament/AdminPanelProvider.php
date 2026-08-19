<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LatestInquiriesWidget;
use App\Filament\Widgets\LatestPostsWidget;
use App\Filament\Widgets\MonthlyStatsWidget;
use App\Filament\Widgets\ServerStorageWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Http\Middleware\AdminDebugMode;
use App\Http\Middleware\AdminIpWhitelist;
use App\Http\Middleware\ApplyScheduledPolicyChanges;
use App\Http\Middleware\ProcessDormantAccounts;
use App\Http\Middleware\PruneAdminAuditLogs;
use App\Http\Middleware\PruneAiChatHistory;
use App\Http\Middleware\PruneInquiries;
use App\Http\Middleware\RecordAdminAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SyncVendorNotices;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('app.admin_path', 'admin'))
            ->brandName(config('app.name'))
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->navigationGroups([
                '콘텐츠 관리',
                '회원 관리',
                '통계',
                '마케팅',
                '운영 관리',
                '시스템 설정',
            ])
            ->profile(isSimple: false)
            // 사용자 메뉴(우측 상단 프로필 드롭다운)에 실제 서비스 홈으로 바로가는 링크 추가 —
            // 새 창으로 열어서 관리자 작업 세션은 그대로 유지된다.
            ->userMenuItems([
                MenuItem::make()
                    ->label('사이트 바로가기')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn () => url('/'), shouldOpenInNewTab: true)
                    ->sort(-2),
            ])
            // 관리자 전체 강제 아님 — 각자 프로필 화면("계정 설정")에서 원하면 켤 수 있는 선택 사항
            // (기본값 $isRequired=false 그대로 사용). QR/복구코드 등은 전부 Filament 코어 기능이라
            // 이 프로젝트가 직접 구현한 코드는 없음(App\Models\User의 컨트랙트 구현 메서드만 있음).
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                StatsOverviewWidget::class,
                LatestPostsWidget::class,
                LatestInquiriesWidget::class,
                MonthlyStatsWidget::class,
                ServerStorageWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AdminDebugMode::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AdminIpWhitelist::class,
                RecordAdminAccess::class,
                ProcessDormantAccounts::class,
                ApplyScheduledPolicyChanges::class,
                PruneAdminAuditLogs::class,
                PruneAiChatHistory::class,
                PruneInquiries::class,
                SyncVendorNotices::class,
            ])
            // 관리자 패널 전역에 항상 떠 있는 AI 비서 위젯(퀵메뉴). 로그인 전(예: 로그인 화면)에는
            // 아예 렌더링하지 않고, 로그인 후에는 위젯 자체의 mount()가 권한/설정 여부에 따라
            // 내용을 비워 보이지 않게 처리한다.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => auth()->check() ? Blade::render('<livewire:ai-chat-widget />') : '',
            )
            // Filament 코어 CSS 위에 얹는 소규모 보정(토글 필드 세로 중앙 정렬 등) — 빌드 도구 없이
            // public/css/admin-custom.css 하나만 <head>에 추가한다.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="'.e(asset('css/admin-custom.css')).'">',
            );
    }
}
