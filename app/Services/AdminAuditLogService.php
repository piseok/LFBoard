<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Banner;
use App\Models\BannedWord;
use App\Models\Board;
use App\Models\EmailTemplate;
use App\Models\Inquiry;
use App\Models\Language;
use App\Models\MaintenanceReport;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Policy;
use App\Models\Popup;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// 관리자 패널에서 생성/수정/삭제한 콘텐츠의 변경 이력을 남긴다. 리소스마다 따로 훅을 심지 않고,
// 여기 등록된 모델 전체에 대해 전역 Eloquent 이벤트(created/updated/deleted)를 한 번만 건다
// (AppServiceProvider::boot()에서 register() 호출). "관리자가 의도적으로 편집했는가"는
// "지금 인증된 사용자가 관리자 레벨인가"로 판단한다 — 이 값이 없으면(비로그인/일반회원 요청,
// 시더/커맨드라인 실행 등) 로그를 남기지 않는다.
class AdminAuditLogService
{
    private const AUDITED_MODELS = [
        Board::class, Page::class, Menu::class, Banner::class, Popup::class,
        Inquiry::class, Policy::class, EmailTemplate::class, Post::class,
        User::class, BannedWord::class, MaintenanceReport::class, Language::class,
    ];

    // 이 필드만 바뀐 경우는 회원 로그인/휴면계정 처리 등 배치성 자동 갱신이지, 관리자가 그 회원을
    // 의도적으로 편집한 게 아니다(예: ProcessDormantAccounts 미들웨어는 관리자 접속을 계기로
    // 실행되지만 실제로 값을 바꾸는 건 시스템이지 그 관리자가 폼을 제출한 게 아님) — User에만 해당.
    private const SYSTEM_ONLY_FIELDS = [
        User::class => ['last_login_at', 'dormant_at', 'dormant_notice_sent_at', 'withdrawal_notice_sent_at', 'remember_token'],
    ];

    // 값 자체를 감사로그에 평문으로 남기면 안 되는 필드 — 바뀌었다는 사실은 남기되 값은 가린다.
    private const MASKED_FIELDS = ['password', 'remember_token', 'author_password', 'two_factor_secret', 'two_factor_recovery_codes', 'ci', 'di'];

    private const LABEL_FIELDS = [
        Board::class => 'name', Page::class => 'title', Menu::class => 'title',
        Banner::class => 'title', Popup::class => 'title', Inquiry::class => 'title',
        Policy::class => 'title', EmailTemplate::class => 'name', Post::class => 'title',
        User::class => 'name', BannedWord::class => 'word', MaintenanceReport::class => 'title',
        Language::class => 'name',
    ];

    public function __construct(
        private readonly SiteSettingService $siteSettings,
    ) {}

    // 감사로그는 모델이 바뀔 때마다 계속 쌓이기만 해서 시간이 지날수록 테이블이 무거워진다.
    // "시스템 설정 > 보안"의 보관 기간(일) 설정을 기준으로 그보다 오래된 로그를 지운다.
    // 0 이하(또는 미설정)면 영구 보관으로 간주해 아무것도 지우지 않는다.
    public function pruneExpired(): void
    {
        $days = (int) $this->siteSettings->get('admin_audit_log_retention_days', '365');

        if ($days <= 0) {
            return;
        }

        AdminAuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }

    // 관리자 접속 기록(RecordAdminAccess 미들웨어)도 같은 감사로그 테이블에 action='access'로
    // 남긴다 — 접속 로그와 활동 로그를 따로 관리할 이유가 없어 하나로 합쳤다(보관기간/자동정리도
    // pruneExpired() 하나로 통일). auditable_type/auditable_id는 NOT NULL이라 "그 관리자 본인"을
    // 대상으로 채운다(자기참조).
    public function recordAccess(User $admin, string $ip, string $url): void
    {
        AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'access',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            // 관리자 이름 대신 방문한 경로를 남겨야 "누가"(admin_name 컬럼과 중복 없이) 아니라
            // "어디에" 접속했는지 목록에서 바로 보인다.
            'auditable_label' => parse_url($url, PHP_URL_PATH) ?: $url,
            'ip' => $ip,
            'url' => $url,
        ]);
    }

    // 데이터베이스 조회 도구(DatabaseQueryTool, 최고관리자 전용)에서 실행한 모든 SQL을 남긴다.
    // 최고관리자가 직접 책임지고 쓰는 기능이라 실행 자체를 막지는 않되, "누가 언제 무슨 쿼리를
    // 실행해서 몇 건이 바뀌었는지/실패했는지" 전부 기록해 사고 발생 시 추적할 수 있게 한다.
    public function recordQuery(User $admin, string $sql, bool $success, ?string $errorMessage = null, ?int $affectedRows = null): void
    {
        AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'query',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'auditable_label' => Str::limit($sql, 100),
            'changes' => [
                'sql' => $sql,
                'success' => $success,
                'affected_rows' => $affectedRows,
                'error' => $errorMessage,
            ],
        ]);
    }

    // AI 비서(채팅/이미지 생성) 사용 내역도 같은 감사로그 테이블에 action='ai_chat'으로 남긴다.
    public function recordAiUsage(User $admin, string $provider, string $type, bool $success): void
    {
        AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'ai_chat',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'auditable_label' => "{$provider} ({$type})",
            'changes' => [
                'provider' => $provider,
                'type' => $type,
                'success' => $success,
            ],
        ]);
    }

    public function register(): void
    {
        foreach (self::AUDITED_MODELS as $modelClass) {
            $modelClass::created(fn (Model $model) => $this->record('created', $model));
            $modelClass::updated(fn (Model $model) => $this->record('updated', $model));
            $modelClass::deleted(fn (Model $model) => $this->record('deleted', $model));

            // forceDeleted 이벤트는 SoftDeletes를 쓰는 모델에만 존재한다(Page/Inquiry/Post/User) —
            // 안 쓰는 모델(Board 등)에 그대로 걸면 "undefined method" 에러가 난다.
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $modelClass::forceDeleted(fn (Model $model) => $this->record('deleted', $model));
            }
        }
    }

    private function record(string $action, Model $model): void
    {
        $admin = auth()->user();

        if (! $admin || $admin->level !== User::LEVEL_ADMIN) {
            return;
        }

        // 감사로그 자신은 이 목록에 없어 재귀 걱정은 없지만, 로그를 남기는 도중 실패해도
        // 원래 하려던 작업(콘텐츠 저장/삭제)은 절대 막히면 안 된다.
        try {
            [$before, $changes] = match ($action) {
                'created' => [null, $this->mask($model->getAttributes())],
                'deleted' => [$this->mask($model->getAttributes()), null],
                default => $this->resolveUpdateDiff($model),
            };

            if ($action === 'updated' && $changes === null) {
                return;
            }

            AdminAuditLog::create([
                'admin_user_id' => $admin->id,
                'admin_name' => $admin->name,
                'action' => $action,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'auditable_label' => $this->label($model),
                'before' => $before,
                'changes' => $changes,
            ]);
        } catch (\Throwable) {
            // 조용히 무시 — 감사로그는 부가 기능이라 실패해도 본 작업에 영향을 주면 안 된다.
        }
    }

    /**
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function resolveUpdateDiff(Model $model): array
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        foreach (self::SYSTEM_ONLY_FIELDS[get_class($model)] ?? [] as $field) {
            unset($changes[$field]);
        }

        if (empty($changes)) {
            return [null, null];
        }

        $before = array_intersect_key($model->getOriginal(), $changes);

        return [$this->mask($before), $this->mask($changes)];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function mask(array $attributes): array
    {
        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = '[변경됨, 값 비공개]';
            }
        }

        return $attributes;
    }

    private function label(Model $model): ?string
    {
        $field = self::LABEL_FIELDS[get_class($model)] ?? null;

        return $field ? (string) ($model->{$field} ?? '') : null;
    }
}
