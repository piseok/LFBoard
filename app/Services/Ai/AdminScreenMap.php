<?php

namespace App\Services\Ai;

use App\Filament\Pages\DatabaseQueryTool;
use App\Filament\Pages\ErrorLogViewer;
use App\Filament\Pages\MarketingMail;
use App\Filament\Pages\MediaLibrary;
use App\Filament\Pages\SiteSettings;
use App\Filament\Pages\SystemUpdate;
use App\Filament\Pages\VisitStats;
use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use App\Filament\Resources\AiChatLogs\AiChatLogResource;
use App\Filament\Resources\BannedWords\BannedWordResource;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Boards\BoardResource;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Filament\Resources\Languages\LanguageResource;
use App\Filament\Resources\MaintenanceReports\MaintenanceReportResource;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Policies\PolicyResource;
use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\VendorNotices\VendorNoticeResource;
use App\Models\User;
use Closure;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;

// AI 비서가 아는 "관리자 화면 지도" - 시스템 지시문과 open_admin_page 도구의 유일한 출처다.
// 화면 키(영문)·이름·"할 수 있는 일" 한 줄을 코드 한 곳에 모아 두고, 도구에 노출되는 모든 키가 설명을
// 갖고 있는지는 시험(AiScreenMapTest)으로 고정한다. 현재 관리자가 접근할 수 없는 화면은 지시문에도
// 도구 선택지에도 넣지 않는다. URL 은 여기서 만들되 모델에게는 키만 준다(관리자 경로 config('app.admin_path')
// 가 외부 제공자로 나가지 않게).
final class AdminScreenMap
{
    /**
     * @var array<string, array{label: string, summary: string, class: class-string, create: bool}>
     */
    public const SCREENS = [
        'dashboard' => ['label' => '대시보드', 'class' => Dashboard::class, 'create' => false,
            'summary' => '방문·게시글·문의 현황 요약과 최근 글/문의를 한눈에 봅니다.'],
        'boards' => ['label' => '게시판 관리', 'class' => BoardResource::class, 'create' => true,
            'summary' => '게시판을 만들고 스킨·레이아웃·읽기/쓰기 권한·에디터 사용·커스텀 필드를 설정합니다.'],
        'posts' => ['label' => '게시글 관리', 'class' => PostResource::class, 'create' => true,
            'summary' => '게시판 글을 작성·수정·삭제하고 공지·비밀글·임시저장·첨부파일을 관리합니다.'],
        'pages' => ['label' => '콘텐츠 페이지', 'class' => PageResource::class, 'create' => true,
            'summary' => '회사소개 같은 고정 페이지를 에디터 또는 HTML 파일로 만들고 SEO 정보와 배너 연동을 설정합니다.'],
        'popups' => ['label' => '팝업 관리', 'class' => PopupResource::class, 'create' => true,
            'summary' => '이미지 또는 HTML 레이어 팝업의 노출 위치·크기·기간을 설정합니다.'],
        'banners' => ['label' => '배너 관리', 'class' => BannerResource::class, 'create' => true,
            'summary' => '그룹 키별 배너(이미지/HTML)와 링크, 노출 기간을 관리합니다. 메인 슬라이드 등에 쓰입니다.'],
        'inquiries' => ['label' => '1:1 상담', 'class' => InquiryResource::class, 'create' => false,
            'summary' => '방문자가 보낸 상담/문의를 확인하고 답변합니다(답변하면 메일이 발송됩니다).'],
        'media' => ['label' => '미디어 라이브러리', 'class' => MediaLibrary::class, 'create' => false,
            'summary' => '업로드한 이미지를 모아 보고 다시 사용합니다.'],
        'menus' => ['label' => '메뉴 관리', 'class' => MenuResource::class, 'create' => false,
            'summary' => '사이트 상단/모바일 메뉴를 3단계 트리로 구성합니다.'],
        'users' => ['label' => '회원 관리', 'class' => UserResource::class, 'create' => false,
            'summary' => '회원과 관리자 계정을 관리하고 관리자별 접근 권한을 부여합니다.'],
        'email_templates' => ['label' => '이메일 템플릿', 'class' => EmailTemplateResource::class, 'create' => false,
            'summary' => '가입 안내·문의 답변 같은 시스템 메일의 문구를 편집합니다.'],
        'marketing_mail' => ['label' => '마케팅 메일 발송', 'class' => MarketingMail::class, 'create' => false,
            'summary' => '수신 동의한 회원에게 메일을 보냅니다.'],
        'visit_stats' => ['label' => '방문자 통계', 'class' => VisitStats::class, 'create' => false,
            'summary' => '일별 방문자 수와 경로별 방문 통계를 봅니다.'],
        'policies' => ['label' => '약관/방침', 'class' => PolicyResource::class, 'create' => false,
            'summary' => '이용약관·개인정보처리방침을 관리하고 시행일을 예약합니다.'],
        'banned_words' => ['label' => '금지어 관리', 'class' => BannedWordResource::class, 'create' => false,
            'summary' => '게시글·댓글에 쓸 수 없는 단어를 관리합니다.'],
        'languages' => ['label' => '언어 관리', 'class' => LanguageResource::class, 'create' => false,
            'summary' => '사이트 콘텐츠 언어와 언어별 시간대를 관리합니다.'],
        'site_settings' => ['label' => '사이트 설정', 'class' => SiteSettings::class, 'create' => false,
            'summary' => '사이트명·테마·메일·보안·소셜 로그인·AI 연동 등 전체 설정을 바꿉니다.'],
        'admin_audit_logs' => ['label' => '관리자 활동 로그', 'class' => AdminAuditLogResource::class, 'create' => false,
            'summary' => '관리자가 언제 무엇을 만들고 고치고 삭제했는지 기록을 봅니다.'],
        'ai_chat_logs' => ['label' => 'AI 비서 사용 내역', 'class' => AiChatLogResource::class, 'create' => false,
            'summary' => '전체 관리자의 AI 대화와 오늘 사용량을 감독합니다.'],
        'maintenance_reports' => ['label' => '유지보수 리포트', 'class' => MaintenanceReportResource::class, 'create' => false,
            'summary' => '유지보수 업체의 작업 보고서를 봅니다.'],
        'vendor_notices' => ['label' => '관리업체 공지사항', 'class' => VendorNoticeResource::class, 'create' => false,
            'summary' => '관리업체가 보낸 공지사항을 봅니다.'],
        'error_log' => ['label' => '에러 로그', 'class' => ErrorLogViewer::class, 'create' => false,
            'summary' => '서버 오류 로그를 확인합니다.'],
        'db_query' => ['label' => '데이터베이스 조회', 'class' => DatabaseQueryTool::class, 'create' => false,
            'summary' => '최고관리자 전용 SQL 실행 도구입니다(실행 즉시 반영되며 되돌릴 수 없습니다).'],
        'system_update' => ['label' => '시스템 업데이트', 'class' => SystemUpdate::class, 'create' => false,
            'summary' => '시스템 업데이트 상태를 확인하고 적용합니다.'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::SCREENS);
    }

    public static function label(string $key): string
    {
        return self::SCREENS[$key]['label'] ?? $key;
    }

    /**
     * 현재 관리자가 열 수 있는 화면만(Filament 의 canAccess 판정 그대로 - 권한 체크박스·최고관리자/client 구분 포함).
     *
     * @return array<string, array{label: string, summary: string, class: class-string, create: bool}>
     */
    public static function accessibleFor(User $admin): array
    {
        return self::actingAs($admin, function () {
            return array_filter(self::SCREENS, fn (array $screen) => (bool) $screen['class']::canAccess());
        });
    }

    // 이 화면에서 새 항목 작성 화면을 열 수 있는가(접근 가능 + create 페이지가 있고 canCreate).
    public static function canCreate(User $admin, string $key): bool
    {
        $screen = self::SCREENS[$key] ?? null;

        if ($screen === null || ! $screen['create']) {
            return false;
        }

        return self::actingAs($admin, function () use ($screen) {
            $class = $screen['class'];

            return $class::canAccess() && (! is_subclass_of($class, Resource::class) || $class::canCreate());
        });
    }

    // 모델에게는 키만 주고, URL 은 서버가 만든다. target: list(목록) | create(작성 화면).
    public static function url(string $key, string $target = 'list', array $parameters = []): ?string
    {
        $screen = self::SCREENS[$key] ?? null;

        if ($screen === null) {
            return null;
        }

        $class = $screen['class'];

        if (is_subclass_of($class, Resource::class)) {
            return $class::getUrl($target === 'create' ? 'create' : 'index', $parameters, isAbsolute: false, panel: 'admin');
        }

        return $class::getUrl($parameters, isAbsolute: false, panel: 'admin');
    }

    // Filament 의 canAccess()/canCreate() 는 auth()->user() 를 보므로, 다른 관리자 기준으로 판정해야 할 때
    // 잠깐 그 사용자로 바꿨다가 되돌린다(위젯은 항상 로그인한 본인 기준이라 평소엔 바꿀 일이 없다).
    private static function actingAs(User $admin, Closure $callback): mixed
    {
        $guard = auth()->guard();
        $previous = $guard->user();

        if ($previous !== null && $previous->is($admin)) {
            return $callback();
        }

        $guard->setUser($admin);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                $guard->setUser($previous);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }
}
