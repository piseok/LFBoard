<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Services\EmailService;
use App\Services\SiteSettingService;
use App\Services\UploadService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class SiteSettings extends Page
{
    use RequiresSuperAdmin;

    protected string $view = 'filament.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = '사이트 설정';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = '사이트 설정';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(SiteSettingService::class)->all();

        // inquiry_categories/site_name/footer_copyright는 언어별로 다른 값을 가질 수 있어
        // {"ko":"...","en":"..."} 형태의 JSON 객체로 저장한다(다른 단순 설정과 다름). 언어별 값을
        // 각각의 가상 필드({key}__{code})로 펼쳐서 폼에 채우고, save()에서 다시 하나로 합친다.
        $activeLocales = Language::query()->where('is_active', true)->pluck('code');

        $categoriesByLocale = json_decode($settings['inquiry_categories'] ?? '{}', true) ?: [];
        unset($settings['inquiry_categories']);
        foreach ($activeLocales as $code) {
            $settings["inquiry_categories__{$code}"] = $categoriesByLocale[$code] ?? [];
        }

        foreach (['site_name', 'footer_copyright', 'cookie_consent_message', 'google_analytics', 'head_scripts', 'body_scripts'] as $localizedKey) {
            $decoded = json_decode($settings[$localizedKey] ?? '', true);
            $byLocale = is_array($decoded) ? $decoded : [Language::defaultCode() => $settings[$localizedKey] ?? ''];
            unset($settings[$localizedKey]);
            foreach ($activeLocales as $code) {
                $settings["{$localizedKey}__{$code}"] = $byLocale[$code] ?? '';
            }
        }

        $settings['admin_ip_whitelist'] = json_decode($settings['admin_ip_whitelist'] ?? '[]', true) ?: [];
        $settings['site_ip_blocklist'] = json_decode($settings['site_ip_blocklist'] ?? '[]', true) ?: [];
        $settings['cookie_consent_locales'] = json_decode($settings['cookie_consent_locales'] ?? '[]', true) ?: [];
        $settings['cookie_consent_countries'] = json_decode($settings['cookie_consent_countries'] ?? '[]', true) ?: [];
        $settings['family_sites'] = json_decode($settings['family_sites'] ?? '[]', true) ?: [];

        // 한 번도 저장된 적 없으면(설치 직후 등) site_settings 테이블에 값 자체가 없어 Select의
        // ->default('auto')가 적용되지 않고 빈 값으로 채워진다 — 여기서 명시적으로 기본값을 보정한다.
        $settings['session_secure_cookie_mode'] = ($settings['session_secure_cookie_mode'] ?? '') ?: 'auto';

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->generalTab(),
                        $this->securityTab(),
                        $this->seoTab(),
                        $this->scriptTab(),
                        $this->cookieConsentTab(),
                        $this->signupTab(),
                        $this->captchaTab(),
                        $this->socialTab(),
                        $this->mailTab(),
                        $this->smsTab(),
                        $this->identityTab(),
                        $this->aiTab(),
                        $this->dormantAccountTab(),
                        $this->loginCountryAlertTab(),
                        $this->inquiryTab(),
                        $this->maintenanceTab(),
                        $this->vendorNoticeTab(),
                    ]),
            ])
            ->statePath('data');
    }

    private function generalTab(): Tab
    {
        $languages = Language::query()->where('is_active', true)->orderBy('sort_order')->get();

        $siteNameFields = $languages->map(fn (Language $language) => TextInput::make("site_name__{$language->code}")
            ->label("사이트명 ({$language->name})")
            ->required($language->code === Language::defaultCode())
            ->maxLength(100)
            ->helperText($language->code === Language::defaultCode() ? null : '비워두면 기본 언어(한국어) 사이트명으로 대체됩니다.'))
            ->all();

        $footerCopyrightFields = $languages->map(fn (Language $language) => TextInput::make("footer_copyright__{$language->code}")
            ->label("푸터 저작권 문구 ({$language->name})")
            ->helperText('비워두면 "© 연도 사이트명" 형식으로 자동 표시됩니다.'))
            ->all();

        return Tab::make('기본 정보')
            ->schema([
                ...$siteNameFields,
                Textarea::make('site_description')->label('사이트 설명')->rows(2),
                FileUpload::make('site_logo')->label('헤더 로고')->disk('uploads')->image()
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                    ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
                FileUpload::make('footer_logo')->label('푸터 로고')->disk('uploads')->image()
                    ->helperText('비워두면 헤더 로고와 같은 이미지가 푸터에도 표시됩니다.')
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                    ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
                FileUpload::make('site_favicon')->label('파비콘')->disk('uploads')->image()
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                    ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
                TextInput::make('admin_email')->label('관리자 이메일')->email()->required(),
                TextInput::make('admin_phone')->label('관리자 휴대폰 번호')->tel()
                    ->helperText('문자/알림톡 발송 기능이 켜져 있으면 1:1 상담 접수 시 이 번호로 알림을 보냅니다.'),
                ...$footerCopyrightFields,
                Textarea::make('footer_address')->label('푸터 주소/사업자 정보')->rows(3),
                TextInput::make('footer_phone')->label('푸터 전화번호')->tel(),
                Repeater::make('family_sites')
                    ->label('패밀리사이트')
                    ->schema([
                        TextInput::make('name')->label('사이트명')->required(),
                        TextInput::make('url')->label('URL')->required()->url(),
                    ])
                    ->columns(2)
                    ->addActionLabel('패밀리사이트 추가')
                    ->helperText('비워두면 푸터에 "Family Site" 메뉴 자체가 표시되지 않습니다.')
                    ->columnSpanFull(),
            ]);
    }

    private function securityTab(): Tab
    {
        return Tab::make('보안')
            ->schema([
                Select::make('session_secure_cookie_mode')->label('세션 쿠키 보안(Secure) 설정')
                    ->options([
                        'auto' => '자동 감지 (권장) — 접속이 HTTPS일 때만 secure 적용',
                        'always' => '항상 사용 — HTTPS 배포 확정 시',
                        'never' => '사용 안 함 — 로컬 HTTP 개발 시',
                    ])
                    ->helperText(
                        '설치 시점의 접속 프로토콜로 .env에 고정되는 값과 별개로, 매 요청마다 이 설정을 반영합니다. '.
                        'HTTP로 개발하다 나중에 HTTPS로 전환해도 "자동 감지"를 두면 별도 조치가 필요 없습니다. '.
                        '리버스 프록시 등으로 자동 감지가 부정확한 경우에만 강제 옵션을 사용하세요.'
                    )
                    ->native(false)->default('auto')->required(),
                $this->boolToggle('debug_mode_enabled', '관리자 디버그 모드')
                    ->helperText(
                        'ON 시 관리자로 로그인한 상태에서만 오류 발생 페이지에 상세 에러(스택트레이스 포함)가 표시됩니다. '.
                        '일반 방문자에게는 항상 일반 오류 페이지만 보이므로 안전합니다. '.
                        '호스팅사에서 서버 로그를 직접 확인하기 어려울 때 켜서 원인을 파악한 뒤 다시 꺼두세요. '.
                        '"시스템 설정 > 에러 로그"에서도 최근 오류 내역을 확인할 수 있습니다.'
                    ),
                $this->boolToggle('admin_ip_whitelist_enabled', '관리자 접속 IP 제한 사용')
                    ->helperText(
                        'ON 시 아래 목록에 등록된 IP/대역에서만 관리자 패널 접속이 가능합니다(로그인 화면 자체는 막지 않고, 로그인 이후 페이지 접근을 제한합니다). '.
                        '최고관리자는 이 제한과 무관하게 항상 접속 가능하므로, 실수로 스스로를 차단할 걱정 없이 설정할 수 있습니다.'
                    ),
                TagsInput::make('admin_ip_whitelist')
                    ->label('허용 IP 목록')
                    ->placeholder('IP 입력 후 Enter (예: 123.45.67.89 또는 123.45.67.0/24)')
                    ->helperText('일반관리자 계정이 접속할 수 있는 IP를 하나씩 추가하세요. "123.45.67.0/24"처럼 대역(CIDR) 표기도 지원합니다.')
                    ->columnSpanFull(),
                $this->boolToggle('site_ip_blocklist_enabled', '사이트 접속 IP 차단 사용')
                    ->helperText(
                        'ON 시 아래 목록에 등록된 IP/대역에서는 사이트(관리자 패널 제외) 접속이 전면 차단됩니다. '.
                        '특정 국가를 막고 싶다면 해당 국가의 공개 IP 대역을 조사해 CIDR로 등록하세요 '.
                        '(이 프로젝트는 국가 자동 판별 기능은 없습니다 — GeoIP 데이터베이스는 라이선스 발급과 주기적 갱신이 필요해 '.
                        '크론이 없는 공유호스팅 환경 및 "키 불필요" 원칙과 맞지 않아 제외했습니다). '.
                        '관리자로 로그인한 계정은 이 차단과 무관하게 항상 사이트 접속이 가능합니다.'
                    ),
                TagsInput::make('site_ip_blocklist')
                    ->label('차단 IP 목록')
                    ->placeholder('IP 입력 후 Enter (예: 123.45.67.89 또는 123.45.67.0/24)')
                    ->helperText('접속을 막을 IP를 하나씩 추가하세요. "123.45.67.0/24"처럼 대역(CIDR) 표기도 지원합니다.')
                    ->columnSpanFull(),
                $this->boolToggle('password_change_reminder_enabled', '비밀번호 변경 알림 사용')
                    ->helperText(
                        '법적 의무 사항은 아닙니다(2023년 9월 개인정보보호위원회가 비밀번호 정기 변경 의무를 '.
                        '공식 폐지했습니다 — 일반회원·관리자 모두 해당 없음). ON 시 일반회원이 아래 주기를 넘기면 '.
                        '로그인 후 변경 안내 화면으로 이동하며, "나중에 하기"를 누르면 그 로그인 세션 동안은 다시 '.
                        '뜨지 않습니다. OFF여도 마이페이지의 "마지막 변경일" 안내는 계속 표시됩니다.'
                    ),
                TextInput::make('password_change_reminder_months')->label('비밀번호 변경 권장 주기(개월)')
                    ->numeric()->minValue(1)->default(6)
                    ->helperText('위 알림이 켜져 있을 때만 사용되는 주기입니다. 꺼져 있어도 마이페이지 "마지막 변경일" 안내에는 계속 반영됩니다.'),
                TextInput::make('admin_audit_log_retention_days')->label('감사로그 보관 기간(일)')
                    ->numeric()->minValue(0)->default(365)
                    ->helperText('이 기간이 지난 감사로그는 관리자 접속 시 자동으로 정리됩니다(하루 최대 1회 실행). 0으로 두면 영구 보관되며 자동으로 지우지 않습니다.'),
            ]);
    }

    private function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->schema([
                TextInput::make('meta_title_separator')->label('메타 타이틀 구분자')->default(' | '),
                TextInput::make('site_keywords')->label('기본 키워드'),
                $this->boolToggle('sitemap_enabled', 'sitemap.xml 자동 생성'),
                Textarea::make('robots_txt')->label('robots.txt 내용')->rows(4),
                Placeholder::make('seo_preview_links')->label('생성 결과 확인')
                    ->content(new HtmlString(
                        '<a href="'.url('/sitemap.xml').'" target="_blank" rel="noopener" class="underline">sitemap.xml 새 창에서 보기</a>'.
                        ' &nbsp;|&nbsp; '.
                        '<a href="'.url('/robots.txt').'" target="_blank" rel="noopener" class="underline">robots.txt 새 창에서 보기</a>'
                    ))
                    ->helperText('저장한 설정이 실제로 어떻게 생성되는지 이 링크로 바로 확인할 수 있습니다. sitemap.xml은 "자동 생성"이 꺼져 있으면 404가 표시됩니다.'),
            ]);
    }

    private function scriptTab(): Tab
    {
        $languages = Language::query()->where('is_active', true)->orderBy('sort_order')->get();

        $fallbackHelp = fn (Language $language) => $language->code === Language::defaultCode()
            ? null
            : '비워두면 기본 언어(한국어) 설정으로 대체됩니다.';

        $fields = $languages->flatMap(fn (Language $language) => [
            TextInput::make("google_analytics__{$language->code}")
                ->label("Google Analytics 코드 ({$language->name})")
                ->helperText($fallbackHelp($language)),
            Textarea::make("head_scripts__{$language->code}")
                ->label("head 스크립트 ({$language->name}, </head> 직전 삽입)")
                ->rows(4)
                ->helperText($fallbackHelp($language)),
            Textarea::make("body_scripts__{$language->code}")
                ->label("body 스크립트 ({$language->name}, </body> 직전 삽입)")
                ->rows(4)
                ->helperText($fallbackHelp($language) ?? '네이버 웹마스터, 카카오 픽셀, 채널톡 등 외부 스크립트를 여기에 붙여넣으세요'),
        ])->all();

        return Tab::make('스크립트')
            ->schema([
                Text::make('언어마다 다른 분석 도구/픽셀을 붙이고 싶을 때만 각 언어 칸을 채우세요. 비워두면 기본 언어(한국어) 설정이 그대로 쓰입니다.')
                    ->color('gray'),
                ...$fields,
            ]);
    }

    private function cookieConsentTab(): Tab
    {
        $languages = Language::query()->where('is_active', true)->orderBy('sort_order')->get();

        $messageFields = $languages->map(fn (Language $language) => Textarea::make("cookie_consent_message__{$language->code}")
            ->label("배너 문구 ({$language->name})")
            ->rows(2)
            ->helperText($language->code === Language::defaultCode()
                ? '비워두면 기본 안내 문구가 표시됩니다.'
                : '비워두면 기본 언어(한국어) 문구로 대체됩니다.'))
            ->all();

        return Tab::make('쿠키 동의')
            ->schema([
                $this->boolToggle('cookie_consent_enabled', '쿠키 동의 배너 사용')
                    ->live()
                    ->helperText('켜면 방문자가 동의하기 전까지 Google Analytics 등 비필수 스크립트가 로드되지 않습니다(head/body 스크립트, SEO 등 필수 기능은 계속 동작).'),
                Select::make('cookie_consent_locales')
                    ->label('노출할 언어')
                    ->multiple()
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                    ->native(false)
                    ->helperText('비워두면 모든 언어에서 노출됩니다. 예: GDPR 대상인 영어 사이트에만 노출하고 싶으면 English만 선택.')
                    ->visible(fn (callable $get) => (bool) $get('cookie_consent_enabled')),
                TagsInput::make('cookie_consent_countries')
                    ->label('노출할 국가 코드(GeoIP)')
                    ->placeholder('예: DE, FR, GB')
                    ->helperText('비워두면 모든 국가에서 노출됩니다. 접속 IP 기준 국가를 감지해 판단하며(로그인 보안 알림과 동일한 GeoIP 데이터 사용), 사설 IP 등 판별 불가 시에는 안전하게 노출합니다.')
                    ->visible(fn (callable $get) => (bool) $get('cookie_consent_enabled')),
                ...collect($messageFields)->map(fn ($field) => $field->visible(fn (callable $get) => (bool) $get('cookie_consent_enabled')))->all(),
            ]);
    }

    private function signupTab(): Tab
    {
        $fieldOptions = ['required' => '필수', 'optional' => '선택', 'hidden' => '숨김'];

        return Tab::make('회원가입 설정')
            ->schema([
                Select::make('login_type')->label('로그인 방식')
                    ->options(['email' => '이메일', 'username' => '아이디'])
                    ->native(false)->default('email'),
                $this->boolToggle('signup_approval_required', '관리자 승인 후 활성화'),
                Select::make('signup_field_nickname')->label('닉네임')->options($fieldOptions)->native(false),
                Select::make('signup_field_phone')->label('전화번호')->options($fieldOptions)->native(false),
                Select::make('signup_field_gender')->label('성별')->options($fieldOptions)->native(false),
                Select::make('signup_field_birthdate')->label('생년월일')->options($fieldOptions)->native(false),
                Select::make('signup_field_homepage')->label('홈페이지')->options($fieldOptions)->native(false),
                Select::make('signup_field_address')->label('주소')->options($fieldOptions)->native(false),
            ])
            ->columns(2);
    }

    private function captchaTab(): Tab
    {
        return Tab::make('스팸방지 설정')
            ->schema([
                Select::make('captcha_provider')->label('공급사')
                    ->options([
                        '' => '없음 (비활성)',
                        'simple_math' => '자체 수식 인증 (무료, 가입/키 불필요)',
                        'recaptcha_v3' => 'Google reCAPTCHA v3',
                        'hcaptcha' => 'hCaptcha',
                        'turnstile' => 'Cloudflare Turnstile',
                    ])->native(false)->live()
                    ->helperText('"자체 수식 인증"은 외부 서비스 가입이나 키 발급 없이 바로 사용할 수 있는 간단한 수식(예: 3 + 5 = ?) 문제입니다. 나머지 3개는 각 공급사 사이트에서 Site Key/Secret Key를 별도로 발급받아야 동작합니다.'),
                TextInput::make('captcha_site_key')->label('Site Key')
                    ->visible(fn (callable $get) => in_array($get('captcha_provider'), ['recaptcha_v3', 'hcaptcha', 'turnstile'], true)),
                TextInput::make('captcha_secret_key')->label('Secret Key')->password()->revealable()
                    ->visible(fn (callable $get) => in_array($get('captcha_provider'), ['recaptcha_v3', 'hcaptcha', 'turnstile'], true)),
                $this->boolToggle('captcha_apply_signup', '회원가입에 적용'),
                $this->boolToggle('captcha_apply_login', '로그인에 적용'),
                $this->boolToggle('captcha_apply_inquiry', '비회원 상담에 적용'),
            ])
            ->columns(2);
    }

    private function socialTab(): Tab
    {
        return Tab::make('소셜 로그인')
            ->schema([
                Text::make('각 공급사 개발자 콘솔에서 발급받은 Client ID/Secret을 입력하고 활성화하면, 로그인/회원가입 화면에 해당 버튼이 자동으로 나타납니다. 콜백 URL은 "'.rtrim(config('app.url'), '/').'/auth/{google|kakao|naver}/callback"로 등록하세요.')
                    ->color('gray'),

                Section::make('Google')
                    ->schema([
                        $this->boolToggle('social_google_enabled', 'Google 활성화')->columnSpanFull(),
                        TextInput::make('social_google_client_id')->label('Google Client ID'),
                        TextInput::make('social_google_client_secret')->label('Google Client Secret')->password()->revealable(),
                    ])
                    ->columns(2),

                Section::make('카카오')
                    ->schema([
                        $this->boolToggle('social_kakao_enabled', '카카오 활성화')->columnSpanFull(),
                        TextInput::make('social_kakao_client_id')->label('카카오 REST API 키'),
                        TextInput::make('social_kakao_client_secret')->label('카카오 Client Secret')->password()->revealable()
                            ->helperText('카카오 개발자 콘솔에서 "Client Secret 사용"을 켠 경우에만 필요합니다.'),
                    ])
                    ->columns(2),

                Section::make('네이버')
                    ->schema([
                        $this->boolToggle('social_naver_enabled', '네이버 활성화')->columnSpanFull(),
                        TextInput::make('social_naver_client_id')->label('네이버 Client ID'),
                        TextInput::make('social_naver_client_secret')->label('네이버 Client Secret')->password()->revealable(),
                    ])
                    ->columns(2),
            ]);
    }

    private function mailTab(): Tab
    {
        return Tab::make('이메일 설정')
            ->schema([
                Section::make('SMTP 서버 설정')
                    ->description('입력하면 .env 설정 대신 이 정보로 메일을 발송합니다. 비워두면 서버 기본(.env) 설정을 사용합니다.')
                    ->schema([
                        TextInput::make('mail_host')->label('SMTP 호스트')->placeholder('예: smtp.gmail.com'),
                        TextInput::make('mail_port')->label('포트')->numeric()->placeholder('587'),
                        TextInput::make('mail_username')->label('SMTP 사용자명'),
                        TextInput::make('mail_password')->label('SMTP 비밀번호')->password()->revealable(),
                        Select::make('mail_encryption')->label('암호화 방식')
                            ->options(['' => '없음', 'tls' => 'TLS (권장, 587 포트)', 'ssl' => 'SSL (465 포트)'])
                            ->native(false),
                    ])
                    ->columns(2),
                Section::make('발신자 정보')
                    ->schema([
                        TextInput::make('mail_from_address')->label('발신자 이메일')->email(),
                        TextInput::make('mail_from_name')->label('발신자 이름'),
                    ])
                    ->columns(2),
                Section::make('메일 발송 여부')
                    ->schema([
                        $this->boolToggle('email_welcome_enabled', '회원가입 환영 메일'),
                        $this->boolToggle('email_verification_enabled', '이메일 인증')
                            ->helperText('ON 시 가입 후 인증 메일 발송 + 미인증 회원 접근 제한'),
                        $this->boolToggle('email_inquiry_received_admin_enabled', '상담 접수 알림 (관리자)'),
                        $this->boolToggle('email_inquiry_received_user_enabled', '상담 접수 확인 (문의자)'),
                        $this->boolToggle('email_inquiry_reply_enabled', '상담 답변 완료 (문의자)'),
                        $this->boolToggle('email_password_reset_enabled', '비밀번호 재설정'),
                        $this->boolToggle('email_marketing_broadcast_enabled', '마케팅 메일(대량 발송)')
                            ->helperText('마케팅 수신에 동의한 회원 대상 대량 발송(사이트 설정 > 마케팅 메일 화면) 사용 여부입니다.'),
                        $this->boolToggle('email_policy_change_notice_enabled', '약관/방침 변경 안내')
                            ->helperText('약관/방침 저장 화면에서 "변경 안내 메일 발송"을 체크했을 때 실제로 발송할지 여부입니다.'),
                    ])
                    ->columns(2),
            ]);
    }

    private function smsTab(): Tab
    {
        return Tab::make('문자/알림톡 설정')
            ->schema([
                Text::make('발신번호는 사전에 통신사/공급사에 등록된 번호여야 합니다. 카카오 알림톡은 발신 프로필 등록과 템플릿 사전 승인이 끝난 뒤에만 실제 발송됩니다(승인 전에는 실패 시 문자로 자동 대체 가능). Twilio는 해외 번호 발송도 가능합니다(알리고/coolsms는 국내 통신사 전용).')
                    ->color('gray'),
                Select::make('sms_provider')->label('SMS 공급사')
                    ->options(['aligo' => '알리고(aligo)', 'coolsms' => 'coolsms(Solapi)', 'twilio' => 'Twilio (국내/해외 혼용)'])
                    ->native(false)->live(),
                TextInput::make('sms_api_key')->label(fn (callable $get) => match ($get('sms_provider')) {
                    'coolsms' => 'API Key',
                    'twilio' => 'Account SID',
                    default => 'API Key(key)',
                }),
                TextInput::make('sms_api_secret')->label(fn (callable $get) => match ($get('sms_provider')) {
                    'coolsms' => 'API Secret',
                    'twilio' => 'Auth Token',
                    default => '사용자 ID(user_id)',
                })->password()->revealable(),
                TextInput::make('sms_from')->label('발신번호')->tel()
                    ->helperText(fn (callable $get) => $get('sms_provider') === 'twilio' ? 'Twilio에서 발급받은 번호를 국가코드 포함 형식으로 입력하세요(예: +15551234567).' : null),
                $this->boolToggle('sms_enabled', 'SMS 알림 사용')
                    ->helperText('회원가입/문의접수 등에서 SMS 발송을 시도할지 여부(공급사 미설정 시 무조건 발송 안 함)'),
                Select::make('kakao_provider')->label('카카오 알림톡 공급사')
                    ->options(['aligo' => '알리고(aligo)'])
                    ->native(false)
                    ->helperText('현재 알리고 계정으로만 지원(SMS와 같은 API Key/사용자ID를 공유합니다).'),
                TextInput::make('kakao_api_key')->label('알림톡 API Key'),
                TextInput::make('kakao_sender_key')->label('발신 프로필 키(senderkey)'),
            ])
            ->columns(2);
    }

    private function identityTab(): Tab
    {
        return Tab::make('본인인증 설정')
            ->schema([
                Text::make('공급사와 계약 후 발급받은 상점ID/키를 입력하면 활성화됩니다. 게시판별 적용 여부는 각 게시판 설정에서 개별 지정합니다.')
                    ->color('gray'),
                $this->boolToggle('identity_verification_enabled', '본인인증 활성화'),
                Select::make('identity_verification_provider')->label('공급사')
                    ->options(['inicis' => 'KG이니시스', 'nice' => 'NICE'])
                    ->native(false),
                TextInput::make('identity_verification_merchant_id')->label('상점 ID(MID)'),
                TextInput::make('identity_verification_api_key')->label('연동 키(Sign/API Key)')->password()->revealable(),
            ]);
    }

    private function aiTab(): Tab
    {
        return Tab::make('AI 연동')
            ->schema([
                Text::make('API 키를 입력한 제공자만 관리자 화면의 AI 비서 퀵메뉴에 나타납니다. 하나도 입력하지 않으면 AI 비서 기능 자체가 표시되지 않습니다(선택 기능). 사용 권한은 회원관리 > 관리자 권한에서 개별 부여합니다.')
                    ->color('gray'),
                TextInput::make('ai_openai_api_key')->label('OpenAI API 키')->password()->revealable(),
                TextInput::make('ai_gemini_api_key')->label('Google Gemini API 키')->password()->revealable(),
                TextInput::make('ai_chat_retention_days')->label('대화 기록 자동 삭제 기간(일)')->numeric()->default(90)
                    ->helperText('이 기간이 지난 대화(및 생성된 이미지)는 완전히 삭제됩니다. 0 이하로 두면 영구 보관됩니다.'),
            ])
            ->columns(2);
    }

    private function dormantAccountTab(): Tab
    {
        return Tab::make('휴면계정 관리')
            ->schema([
                $this->boolToggle('dormant_processing_enabled', '휴면계정/강제탈퇴 처리 사용')
                    ->helperText('OFF 시 아래 설정과 무관하게 휴면 전환·강제탈퇴·예고 발송을 전혀 진행하지 않습니다.'),
                TextInput::make('dormant_conversion_months')->label('휴면 전환 개월수')
                    ->numeric()->default(12)
                    ->helperText('최종 로그인일(last_login_at) 기준 이 개월수가 지나면 휴면 계정으로 전환됩니다.'),
                TextInput::make('forced_withdrawal_months')->label('강제탈퇴 개월수')
                    ->numeric()->default(24)
                    ->helperText('최종 로그인일 기준(휴면 전환일이 아닌 총 경과 개월수) 이 개월수가 지나면 자동 탈퇴(소프트 삭제+익명화) 처리됩니다. 휴면 전환 개월수보다 커야 합니다.'),
                TextInput::make('dormant_notice_days')->label('휴면 예고 일수')
                    ->numeric()->default(14)
                    ->helperText('휴면 전환 며칠 전에 미리 안내 메일/문자를 보낼지 설정합니다.'),
                TextInput::make('withdrawal_notice_days')->label('강제탈퇴 예고 일수')
                    ->numeric()->default(14)
                    ->helperText('강제탈퇴 며칠 전에 미리 안내 메일/문자를 보낼지 설정합니다.'),
                Text::make(
                    '이 서버는 크론(스케줄러) 없이 관리자가 접속할 때마다 처리 대상을 확인하는 방식이라, '.
                    '휴일 등으로 관리자가 정확한 예고일에 접속하지 않으면 그날 발송되지 못할 수 있습니다. '.
                    '이를 보완하기 위해 예고일 앞뒤 7일(±1주) 이내에 관리자가 접속하면 그때 발송됩니다(중복 발송 없음).'
                )->color('gray')->columnSpanFull(),
                $this->boolToggle('email_dormant_notice_enabled', '휴면 예고 메일 발송'),
                $this->boolToggle('email_withdrawal_notice_enabled', '강제탈퇴 예고 메일 발송'),
                $this->boolToggle('dormant_notice_sms_enabled', 'SMS로도 예고 발송')
                    ->helperText('문자/알림톡 설정에서 SMS 공급사가 구성되어 있어야 실제로 발송됩니다.'),
                $this->boolToggle('dormant_notice_kakao_enabled', '알림톡으로도 예고 발송')
                    ->helperText('문자/알림톡 설정에서 카카오 알림톡이 구성되어 있어야 실제로 발송됩니다.'),
                $this->boolToggle('dormant_reactivation_requires_sms', '휴면 해제 시 SMS 인증 추가 요구')
                    ->helperText(
                        '로그인 시도 시점에 비밀번호로 이미 1차 확인이 되었으므로 기본적으로는 버튼 클릭만으로 즉시 해제됩니다. '.
                        'ON으로 켜면 SMS 인증까지 추가로 요구합니다(SMS 공급사가 설정되어 있고 회원 전화번호가 등록된 경우에만 적용).'
                    ),
            ])
            ->columns(2);
    }

    private function loginCountryAlertTab(): Tab
    {
        return Tab::make('로그인 보안 알림')
            ->schema([
                $this->boolToggle('login_country_alert_enabled', '로그인 국가 변경 알림 사용')
                    ->helperText(
                        '회원 계정에서 처음 보는 국가로 로그인이 감지되면 안내 메일을 발송합니다. '.
                        '국가 판별은 외부 API 호출 없이 내장된 정적 IP 대역 데이터(db-ip.com IP to Country Lite)로 이루어지며, '.
                        'VPN 등을 사용하는 경우 실제 위치와 다르게 판별될 수 있습니다.'
                    ),
                TextInput::make('login_country_trust_days')->label('신뢰 전환 일수')
                    ->numeric()->default(7)
                    ->helperText('이 국가에서 알림을 보낸 뒤 며칠이 지나면 안내 메일에 "신뢰할 수 있는 위치"로 표시할지입니다(재발송 여부와 무관 — 같은 국가는 최초 1회만 발송됩니다).'),
                $this->boolToggle('email_login_country_changed_enabled', '이메일로 발송')
                    ->helperText('SMTP가 구성되어 있어야 실제로 발송됩니다.'),
                $this->boolToggle('login_country_alert_sms_enabled', 'SMS로도 발송')
                    ->helperText('문자/알림톡 설정에서 SMS 공급사가 구성되어 있어야 실제로 발송됩니다.'),
                $this->boolToggle('login_country_alert_kakao_enabled', '알림톡으로도 발송')
                    ->helperText('문자/알림톡 설정에서 카카오 알림톡이 구성되어 있어야 실제로 발송됩니다.'),
            ])
            ->columns(2);
    }

    private function inquiryTab(): Tab
    {
        $categoryFields = Language::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (Language $language) => TagsInput::make("inquiry_categories__{$language->code}")
                ->label("문의 카테고리 ({$language->name})")
                ->helperText($language->code === Language::defaultCode()
                    ? '비워두면 다른 언어에서 목록이 없을 때 이 기본 언어 목록으로 대체됩니다.'
                    : '비워두면 기본 언어(한국어) 목록으로 자동 대체됩니다.'))
            ->all();

        return Tab::make('상담 설정')
            ->schema([
                $this->boolToggle('show_footer_inquiry', '하단 상담폼 표시'),
                $this->boolToggle('show_quick_menu', '퀵메뉴 상담 아이콘 표시')
                    ->helperText('꺼도 퀵메뉴의 "맨 위로" 버튼은 항상 표시됩니다.'),
                ...$categoryFields,
            ]);
    }

    private function maintenanceTab(): Tab
    {
        return Tab::make('유지보수 리포트')
            ->schema([
                TextInput::make('maintenance_report_url')->label('전송 대상 URL')->url(),
                TextInput::make('maintenance_report_token')->label('인증 토큰')->password()->revealable(),
            ]);
    }

    private function vendorNoticeTab(): Tab
    {
        return Tab::make('관리업체 공지사항')
            ->schema([
                Text::make(
                    '"유지보수 리포트"(사이트 → 관리업체)와 반대 방향으로, 관리업체가 운영하는 중앙 공지사항 API를 '.
                    '주기적으로 조회해 "운영 관리 > 관리업체 공지사항" 화면에 표시합니다. API는 GET 요청에 아래 형식의 '.
                    'JSON으로 응답해야 합니다: {"notices":[{"id":"고유값","title":"제목","url":"바로가기 URL(선택)","published_at":"발행일시(선택)"}]}'
                )->color('gray')->columnSpanFull(),
                $this->boolToggle('vendor_notice_enabled', '관리업체 공지사항 수신 사용'),
                TextInput::make('vendor_notice_api_url')->label('조회 대상 URL')->url(),
                TextInput::make('vendor_notice_api_token')->label('인증 토큰')->password()->revealable()
                    ->helperText('설정하면 요청 시 Authorization: Bearer 헤더로 전달됩니다.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testMail')
                ->label('테스트 메일 발송')
                ->color('gray')
                ->schema([
                    TextInput::make('to')->label('수신 이메일')->email()->required(),
                    Select::make('template_type')->label('템플릿')
                        ->options(EmailTemplate::query()->pluck('name', 'type'))
                        ->required()->native(false),
                ])
                ->action(function (array $data): void {
                    $sent = app(EmailService::class)->send($data['template_type'], $data['to'], [
                        'user_name' => '테스트 수신자',
                        'user_email' => $data['to'],
                        'inquiry_name' => '테스트',
                        'inquiry_title' => '테스트 제목',
                        'inquiry_category' => '일반문의',
                        'reply_content' => '테스트 답변 내용입니다.',
                        'verification_url' => url('/'),
                        'reset_url' => url('/'),
                        'unsubscribe_url' => url('/'),
                        'content' => '테스트 본문입니다.',
                    ]);

                    Notification::make()
                        ->title($sent ? '테스트 메일을 발송했습니다.' : '메일 발송에 실패했습니다. 해당 항목의 발송 활성화 여부를 확인하세요.')
                        ->color($sent ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // forced_withdrawal_months가 dormant_conversion_months보다 작거나 같으면, 로그인 후
        // 경과 개월수를 "휴면 전환 예정"과 "강제탈퇴 예정" 양쪽에서 동시에 넘긴 상태가 되어버려
        // DormantAccountService가 같은 관리자 접속 주기 안에서 방금 휴면 전환된 회원을 예고 없이
        // 곧바로 익명화·탈퇴시켜 버릴 수 있다 — 되돌릴 수 없는 데이터 손실이라 저장 자체를 막는다.
        $dormantMonths = (int) ($data['dormant_conversion_months'] ?? 12);
        $withdrawalMonths = (int) ($data['forced_withdrawal_months'] ?? 24);

        if ($withdrawalMonths <= $dormantMonths) {
            Notification::make()
                ->title('저장하지 못했습니다')
                ->body('강제탈퇴 개월수는 휴면 전환 개월수보다 커야 합니다.')
                ->danger()
                ->send();

            return;
        }

        // 언어별 가상 필드(inquiry_categories__ko, site_name__ko, footer_copyright__ko 등)를 다시
        // 하나의 {"ko":"...","en":"..."} 객체로 합쳐서 실제 site_settings 키 하나로 저장한다.
        $localizedKeys = ['inquiry_categories', 'site_name', 'footer_copyright', 'cookie_consent_message', 'google_analytics', 'head_scripts', 'body_scripts'];
        $byLocale = array_fill_keys($localizedKeys, []);
        foreach ($data as $key => $value) {
            foreach ($localizedKeys as $localizedKey) {
                if (str_starts_with($key, "{$localizedKey}__")) {
                    $byLocale[$localizedKey][substr($key, strlen($localizedKey) + 2)] = $value;
                    unset($data[$key]);
                }
            }
        }
        foreach ($localizedKeys as $localizedKey) {
            $data[$localizedKey] = $byLocale[$localizedKey];
        }

        $groupMap = $this->keyGroupMap();

        foreach ($data as $key => $value) {
            if (in_array($key, [...$localizedKeys, 'admin_ip_whitelist', 'site_ip_blocklist', 'cookie_consent_locales', 'cookie_consent_countries', 'family_sites'], true)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                // FileUpload가 배열(다중/단일 파일 경로 목록)을 반환하는 경우 첫 번째 경로만 사용
                $value = $value[array_key_first($value)] ?? '';
            }

            app(SiteSettingService::class)->set($key, (string) ($value ?? ''), $groupMap[$key] ?? 'general');
        }

        Cache::flush();

        Notification::make()->title('저장되었습니다.')->success()->send();
    }

    // site_settings에는 '1'/'0' 문자열로 저장되므로, 로드 시 문자열→bool,
    // 저장 시 bool→문자열로 변환하는 Toggle 헬퍼.
    private function boolToggle(string $name, string $label): Toggle
    {
        return Toggle::make($name)
            ->label($label)
            ->afterStateHydrated(function (Toggle $component, $state): void {
                $component->state($state === '1' || $state === true);
            });
    }

    private function keyGroupMap(): array
    {
        return [
            'session_secure_cookie_mode' => 'security', 'debug_mode_enabled' => 'security',
            'admin_ip_whitelist_enabled' => 'security', 'admin_ip_whitelist' => 'security',
            'site_ip_blocklist_enabled' => 'security', 'site_ip_blocklist' => 'security',
            'password_change_reminder_months' => 'security', 'password_change_reminder_enabled' => 'security',
            'admin_audit_log_retention_days' => 'security',
            'mail_host' => 'mail', 'mail_port' => 'mail', 'mail_username' => 'mail',
            'mail_password' => 'mail', 'mail_encryption' => 'mail',
            'mail_from_address' => 'mail', 'mail_from_name' => 'mail',
            'email_welcome_enabled' => 'mail', 'email_verification_enabled' => 'mail',
            'email_inquiry_received_admin_enabled' => 'mail', 'email_inquiry_received_user_enabled' => 'mail',
            'email_inquiry_reply_enabled' => 'mail', 'email_password_reset_enabled' => 'mail',
            'email_marketing_broadcast_enabled' => 'mail', 'email_policy_change_notice_enabled' => 'mail',
            'login_type' => 'member', 'signup_approval_required' => 'member',
            'signup_field_nickname' => 'member', 'signup_field_phone' => 'member',
            'signup_field_gender' => 'member', 'signup_field_birthdate' => 'member',
            'signup_field_homepage' => 'member', 'signup_field_address' => 'member',
            'captcha_provider' => 'captcha', 'captcha_site_key' => 'captcha', 'captcha_secret_key' => 'captcha',
            'captcha_apply_signup' => 'captcha', 'captcha_apply_login' => 'captcha', 'captcha_apply_inquiry' => 'captcha',
            'social_google_enabled' => 'social', 'social_google_client_id' => 'social', 'social_google_client_secret' => 'social',
            'social_kakao_enabled' => 'social', 'social_kakao_client_id' => 'social', 'social_kakao_client_secret' => 'social',
            'social_naver_enabled' => 'social', 'social_naver_client_id' => 'social', 'social_naver_client_secret' => 'social',
            'sms_provider' => 'integration', 'sms_api_key' => 'integration', 'sms_api_secret' => 'integration',
            'sms_from' => 'integration', 'sms_enabled' => 'integration',
            'kakao_provider' => 'integration', 'kakao_api_key' => 'integration', 'kakao_sender_key' => 'integration',
            'identity_verification_enabled' => 'integration', 'identity_verification_provider' => 'integration',
            'identity_verification_merchant_id' => 'integration', 'identity_verification_api_key' => 'integration',
            'ai_openai_api_key' => 'ai', 'ai_gemini_api_key' => 'ai', 'ai_chat_retention_days' => 'ai',
            'dormant_processing_enabled' => 'dormant', 'dormant_conversion_months' => 'dormant',
            'forced_withdrawal_months' => 'dormant', 'dormant_notice_days' => 'dormant',
            'withdrawal_notice_days' => 'dormant', 'email_dormant_notice_enabled' => 'dormant',
            'email_withdrawal_notice_enabled' => 'dormant', 'dormant_notice_sms_enabled' => 'dormant',
            'dormant_notice_kakao_enabled' => 'dormant', 'dormant_reactivation_requires_sms' => 'dormant',
            'login_country_alert_enabled' => 'login_country', 'login_country_trust_days' => 'login_country',
            'email_login_country_changed_enabled' => 'login_country', 'login_country_alert_sms_enabled' => 'login_country',
            'login_country_alert_kakao_enabled' => 'login_country',
            'vendor_notice_enabled' => 'vendor_notice', 'vendor_notice_api_url' => 'vendor_notice',
            'vendor_notice_api_token' => 'vendor_notice',
        ];
    }
}
