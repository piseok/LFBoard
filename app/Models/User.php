<?php

namespace App\Models;

use App\Services\EmailService;
use App\Services\SiteSettingService;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    // level: 1=비회원, 2=일반회원, 3=정회원, 9=관리자. 3~8은 이후 등급을 추가할 여유 공간
    // (예: VIP회원 등) — 게시판/메뉴의 min_level 계열 필드가 전부 부등호(</<=) 비교라
    // 새 등급 값만 끼워넣으면 코드 변경 없이 그대로 동작한다.
    public const LEVEL_GUEST = 1;

    public const LEVEL_MEMBER = 2;

    public const LEVEL_VERIFIED = 3;

    public const LEVEL_ADMIN = 9;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'locale',
        'password',
        'level',
        'admin_role',
        'admin_permissions',
        'admin_locale_scope',
        'admin_board_scope',
        'phone',
        'nickname',
        'gender',
        'birthdate',
        'homepage',
        'address',
        'memo',
        'is_active',
        'marketing_agreed',
        'marketing_agreed_at',
        'unsubscribe_token',
        'last_login_at',
        'ci',
        'di',
        'phone_verified_at',
        'password_changed_at',
        'dormant_at',
        'dormant_notice_sent_at',
        'withdrawal_notice_sent_at',
        'vendor_notice_last_seen_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'level' => 'integer',
            'admin_permissions' => 'array',
            'admin_locale_scope' => 'array',
            'admin_board_scope' => 'array',
            'birthdate' => 'date',
            'is_active' => 'boolean',
            'marketing_agreed' => 'boolean',
            'marketing_agreed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'dormant_at' => 'datetime',
            'dormant_notice_sent_at' => 'datetime',
            'withdrawal_notice_sent_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->level === self::LEVEL_ADMIN;
    }

    // 일반회원/정회원 등 "관리자가 아닌 가입 회원"을 통칭한다. 등급이 늘어날 때마다
    // `=== LEVEL_MEMBER`로 하드코딩된 곳에서 새 등급이 조용히 빠지는 사고를 막기 위해,
    // 회원 여부를 판단해야 하는 곳(약관 재동의, 비밀번호 변경 주기 알림 등)은 반드시
    // 이 메서드를 쓴다 — 특정 등급 하나만 가리키고 싶을 때만 level을 직접 비교한다.
    public function isMember(): bool
    {
        return $this->level >= self::LEVEL_MEMBER && $this->level < self::LEVEL_ADMIN;
    }

    /**
     * "담당 언어" 권한 — null이면 전체 언어 접근 허용, 배열이면 그 언어 코드로만 콘텐츠를 제한한다.
     * 슈퍼관리자(super) 및 admin_role 미지정(레거시 전체관리자)은 이 필드값과 무관하게 항상 전체 접근.
     * `HasLocaleScope` 트레이트를 쓰는 Filament 리소스가 이 값으로 목록/폼 쿼리를 자동으로 스코프한다.
     *
     * @return array<int, string>|null
     */
    public function localeScope(): ?array
    {
        if ($this->admin_role === 'super' || is_null($this->admin_role)) {
            return null;
        }

        return $this->admin_locale_scope ?: null;
    }

    /**
     * "담당 게시판" 권한 — null이면 "게시판관리"/"게시글관리" 권한이 있는 한 전체 게시판 접근
     * 허용, 배열(게시판 id 목록)이면 그 게시판으로만 제한한다. localeScope()와 동일한 설계.
     *
     * @return array<int, int>|null
     */
    public function boardScope(): ?array
    {
        if ($this->admin_role === 'super' || is_null($this->admin_role)) {
            return null;
        }

        return $this->admin_board_scope ?: null;
    }

    // admin_permissions는 CheckboxList가 선택된 키만 담은 단순 배열(['boards','posts'])로 저장하므로
    // in_array로 확인한다(연관배열이 아님 — HasPermissionCheck::canAccess()와 동일한 판단 기준).
    public function hasAdminPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($key, $this->admin_permissions ?? [], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->admin_role === 'super' || is_null($this->admin_role);
    }

    // 일반 최고관리자 — 벤더(슈퍼관리자)가 클라이언트에게 전달하는 계정. 일반관리자(manager)
    // 계정 생성/권한 부여는 가능하지만 슈퍼관리자·다른 일반최고관리자 권한은 부여할 수 없다
    // (UserResource::allowedAdminRoleOptions() 참고). 시스템 설정(진짜 위험 기능)은 접근 불가하고,
    // 별도로 분리된 "운영 관리" 그룹(약관/방침, 관리자 활동로그, AI 대화로그, 유지보수 리포트)만 접근 가능.
    public function isClientAdmin(): bool
    {
        return $this->admin_role === 'client';
    }

    // Filament 4 내장 "앱 인증"(TOTP) 2FA — 관리자 개인 선택 사항(강제 아님, `AdminPanelProvider`의
    // `multiFactorAuthentication()` 참고). 시크릿/복구코드는 폼으로 직접 받는 값이 아니라 이
    // 메서드들을 통해서만 기록되므로(관리자 프로필 화면의 설정 마법사가 호출) 일부러 $fillable에
    // 넣지 않았다 — 혹시라도 다른 코드가 무심코 mass-assignment로 덮어쓰는 사고를 막기 위함.
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->forceFill(['two_factor_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->two_factor_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => $codes])->save();
    }

    public function policyConsents(): HasMany
    {
        return $this->hasMany(PolicyConsent::class);
    }

    // 나중에 "언제 어떤 버전에 동의했는지" 분쟁이 생겨도 확인 가능하도록 매번 새 행을 남긴다
    // (덮어쓰지 않음 — admin_audit_logs와 같은 append-only 철학).
    public function recordPolicyConsent(string $type, string $locale, ?string $version): void
    {
        $this->policyConsents()->create([
            'type' => $type,
            'locale' => $locale,
            'version' => $version,
            'agreed_at' => now(),
        ]);
    }

    /**
     * 필수 약관(선택 약관인 마케팅 제외) 중, 이 회원의 최신 동의 버전이 지금 활성화된 버전과
     * 다르거나 동의 기록 자체가 없는 타입 목록을 반환한다. 비어있으면 재동의가 필요 없다는 뜻.
     *
     * @return array<int, string>
     */
    public function outdatedRequiredPolicyTypes(?string $locale = null): array
    {
        $locale ??= $this->locale ?: Language::defaultCode();

        $requiredPolicies = Policy::activeForLocale($locale)->where('is_required', true);

        if ($requiredPolicies->isEmpty()) {
            return [];
        }

        $latestConsents = $this->policyConsents()
            ->whereIn('type', $requiredPolicies->pluck('type'))
            ->orderByDesc('agreed_at')
            ->get()
            ->unique('type')
            ->keyBy('type');

        $outdated = [];

        foreach ($requiredPolicies as $policy) {
            $consent = $latestConsents->get($policy->type);

            if (! $consent || $consent->version !== $policy->version) {
                $outdated[] = $policy->type;
            }
        }

        return $outdated;
    }

    public function isDormant(): bool
    {
        return $this->dormant_at !== null;
    }

    // 자진 탈퇴(MyPageController)와 휴면계정 강제탈퇴(DormantAccountService)가 동일한 방식으로
    // 처리해야 해서 공통 로직을 모델에 둔다 — 소프트 삭제 + 개인정보 익명화(하드 삭제 아님).
    public function anonymizeAndWithdraw(): void
    {
        $this->socialAccounts()->delete();

        $this->forceFill([
            'name' => '탈퇴한 회원',
            'username' => null,
            'email' => 'deleted_'.$this->id.'_'.Str::random(16).'@deleted.local',
            'password' => Hash::make(Str::random(32)),
            'nickname' => null,
            'phone' => null,
            'gender' => null,
            'birthdate' => null,
            'homepage' => null,
            'address' => null,
            'memo' => null,
            'is_active' => false,
            'ci' => null,
            'di' => null,
            'phone_verified_at' => null,
        ])->save();

        $this->delete();
    }

    // ci/di는 본인인증 완료 시 공급사로부터 발급받아 저장되는 값이라, 존재 여부로 인증 완료를 판별한다.
    public function isIdentityVerified(): bool
    {
        return filled($this->ci) && filled($this->di);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    // email_verification_enabled 설정이 꺼져 있으면 인증 메일을 보내지 않고
    // 즉시 인증 완료 처리한다. 켜져 있으면 EmailService로 서명된 인증 링크를 발송한다.
    public function sendEmailVerificationNotification(): void
    {
        $settings = app(SiteSettingService::class);

        if ($settings->get('email_verification_enabled', '0') !== '1') {
            if (! $this->hasVerifiedEmail()) {
                $this->forceFill(['email_verified_at' => now()])->save();
            }

            return;
        }

        // 인증 링크는 이 회원의 선호 언어 라우트로 만든다(지금 요청이 어느 언어 화면에서 왔는지가 아니라,
        // 메일을 받는 회원이 실제로 어떤 언어를 쓰는지가 기준이어야 함).
        $verificationUrl = URL::temporarySignedRoute(
            Language::routeNamePrefix($this->locale).'verification.verify',
            now()->addHours(24),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        app(EmailService::class)->send('email_verification', $this->email, [
            'user_name' => $this->name,
            'verification_url' => $verificationUrl,
        ], $this->locale);
    }

    // Laravel 기본 알림 대신 EmailService/email_templates 기반으로 재설정 메일을 발송한다.
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route(Language::routeNamePrefix($this->locale).'password.reset', ['token' => $token, 'email' => $this->email], false));

        app(EmailService::class)->send('password_reset', $this->email, [
            'user_name' => $this->name,
            'reset_url' => $resetUrl,
        ], $this->locale);
    }
}
