# LFboard — 개발자 인수인계 문서

Laravel 12 + Filament 4 기반 CMS(콘텐츠 관리 시스템). 크론(스케줄러)과 SSH를 쓸 수 없는
일반적인 국내 공유호스팅(카페24 등)에서 그대로 운영 가능하도록 설계되었습니다.

> 이 문서는 **개발자용 코드 가이드**입니다. 제품 소개는 [소개서.md](소개서.md),
> 관리자 화면 조작법은 [사용가이드.md](사용가이드.md)를 참고하세요.

---

## 1. 기술 스택

| 구성 요소 | 내용 |
|---|---|
| 백엔드 | Laravel 12, PHP 8.3+ |
| 관리자 패널 | Filament 4 (`/admin`, 경로는 `ADMIN_PATH` 환경변수로 변경 가능) |
| DB | MySQL 5.7+ 또는 MariaDB 10.2+ (설치 마법사가 접속 시 자동 감지해 `DB_CONNECTION`을 알맞게 설정 — 3번 항목 참고) |
| 프론트엔드 | Blade 템플릿 + 순수 JS(빌드 도구 없이 `public/js`, `public/css` 직접 서빙) |
| 로컬 개발 환경 | Docker(Laravel Sail 기반, `docker-compose.yml`) — 로컬 PHP 버전이 8.3 미만이면 반드시 이 방식 사용 |

---

## 2. 폴더 구조

```
app/
├── Console/Commands/     하나뿐인 커맨드: geoip:import (아래 8-3 참고)
├── Filament/
│   ├── Resources/        관리자 CRUD 화면(게시판, 페이지, 배너, 회원 등)
│   ├── Pages/            단일 커스텀 관리자 화면(사이트 설정, 마케팅 메일, 시스템 업데이트 등)
│   ├── Concerns/         Filament 리소스/페이지가 공통으로 쓰는 트레이트
│   └── Widgets/          대시보드 위젯
├── Http/
│   ├── Controllers/      프론트(방문자) 화면 컨트롤러
│   ├── Controllers/Auth/ 로그인/회원가입/소셜로그인/휴면계정 관련 컨트롤러
│   ├── Middleware/       미들웨어(9번 항목 참고)
│   └── Requests/         폼 검증(FormRequest)
├── Models/                Eloquent 모델
├── Services/              핵심 비즈니스 로직 — 대부분의 실제 동작이 여기 있음(6번 항목 참고)
└── Mail/                  Mailable 클래스(TemplateMail 하나로 모든 발송 메일을 통일 처리)

resources/views/
├── layouts/app.blade.php  프론트 공통 레이아웃
├── partials/              공용 조각(팝업, 배너, 헤더/푸터, 캡차 등)
├── board/skins/default/   게시판 스킨(레이아웃) — 새 스킨을 만들면 게시판 관리에서 선택 가능
├── auth/, mypage/, inquiry/, policy/  프론트 화면들
└── filament/pages/        Filament 커스텀 페이지의 blade 뷰

database/
├── migrations/            DB 스키마(신규 마이그레이션만 추가, 기존 파일 수정 금지)
├── seeders/                초기 데이터(관리자 계정, 이메일 템플릿, 금칙어, 메뉴 등)
└── geoip/                 로그인 국가감지용 정적 IP 대역 데이터(8-3 항목 필독)

public/
├── index.php               라라벨 진입점 — 서버의 문서 루트(DocumentRoot)는 반드시 이 public/ 폴더를 가리켜야 함
├── install.php              최초 설치 마법사(3번 항목 참고, 설치 완료 후에는 접근이 자동으로 막힘)
└── uploads/                 회원/관리자가 업로드한 파일이 저장되는 실제 위치(4번 항목 필독)
```

---

## 3. 설치 방법

1. 이 zip 압축을 해제해 서버(공유호스팅 등)의 웹 루트 상위에 업로드하고, **웹서버 DocumentRoot는 반드시 `public/` 폴더**를 가리키도록 설정합니다. `.env` 파일은 미리 만들 필요가 없습니다 — 설치 마법사가 전부 생성합니다.
2. 브라우저로 `https://내도메인/install.php`에 접속하면 5단계 설치 마법사가 실행됩니다: 환경 체크 → DB 연결 설정 → 사이트 기본 설정 → 설치 실행(마이그레이션+시더 자동 실행) → 완료.
   > ⚠️ DB 연결 설정 단계에서 호스트/포트/DB명/계정 정보만 입력하면 됩니다. `.env` 파일과 `APP_KEY`는 이 단계에서 자동 생성되므로 직접 만들거나 수정할 필요가 없습니다. 서버가 MySQL인지 MariaDB인지, 몇 버전인지도 연결 테스트 시 자동으로 감지해 `DB_CONNECTION` 값에 알맞게 반영합니다(지원 최소 사양: MySQL 5.7 / MariaDB 10.2 이상 — JSON 컬럼을 쓰기 때문입니다. 미만이면 설치가 막히고 안내 메시지가 뜹니다).
3. 설치가 완료되면 `install.php`는 자동으로 재접근이 차단됩니다(`RequireInstallation` 미들웨어가 `.env`의 설치 완료 플래그를 확인). 필요 없어지면 파일 자체를 지워도 됩니다.
4. 관리자 로그인은 `/admin` (또는 설치 시 지정한 경로)이며, 설치 3단계에서 입력한 관리자 계정으로 로그인합니다.

---

## 4. 업로드 파일은 `storage:link` 없이 동작합니다

`config/filesystems.php`의 `uploads` 디스크가 `storage/app`이 아니라 **`public/uploads`를 직접 루트로 사용**하도록 되어 있습니다. 그래서:
- `php artisan storage:link` 실행이 **필요 없습니다**(공유호스팅은 심볼릭 링크 생성이 막혀 있는 경우가 많아 이렇게 설계됨).
- 배포 시 `public/uploads/` 폴더에 쓰기 권한(755 또는 775)만 있으면 됩니다.

---

## 5. 크론이 없는 환경을 위한 설계 원칙 (매우 중요)

이 프로젝트는 **크론(스케줄러)을 전혀 쓰지 않습니다.** "주기적으로 처리해야 할 작업"은 전부
**관리자가 관리자 패널에 접속할 때마다 확인 후 필요하면 1회 실행**하는 방식으로 대체되어 있습니다
(하루에 한 번만 실행되도록 캐시 플래그로 중복 실행을 막음). 관련 미들웨어:

| 미들웨어 | 하는 일 |
|---|---|
| `RecordAdminAccess` | 관리자 접속 시각/IP/경로를 감사로그(`admin_audit_logs`, `action='access'`)에 기록 |
| `PruneAdminAuditLogs` | 사이트 설정에 지정한 보관 기간이 지난 감사로그(접속 기록 포함)를 하루 1번 정리 |
| `ProcessDormantAccounts` | 휴면 전환 대상/강제탈퇴 대상/예고 메일 발송 대상을 하루 1번 확인·처리 |
| `ApplyScheduledPolicyChanges` | 예약된 약관/정책 변경 중 시행일이 지난 건을 하루 1번 확인해 실제 내용에 반영 |
| `PruneAiChatHistory` | 사이트 설정에 지정한 보관 기간이 지난 AI 비서 대화(및 생성 이미지)를 하루 1번 완전 삭제 |
| `PruneInquiries` | 사이트 설정에 지정한 보유기간이 지난 1:1 문의(첨부파일 포함)를 하루 1번 완전 삭제(파기) |
| `SyncVendorNotices` | 사이트 설정에 관리업체 공지사항 API가 켜져 있으면 1시간에 1번 폴링해 `vendor_notices`에 반영(캐시 플래그 단위만 시간(hour)으로, 나머지는 동일한 패턴) |

새로운 "주기적 처리"가 필요한 기능을 추가할 때는 **절대 크론을 전제로 설계하지 말고**, 위
미들웨어들과 같은 패턴(관리자 패널 `authMiddleware`에 등록 → 캐시 플래그로 실행 주기 제한)을 따르세요.
등록 위치: `app/Providers/Filament/AdminPanelProvider.php`.

배포 서버에 SSH가 없다는 전제도 있어, 배포 후 마이그레이션 반영은 관리자 패널의
**"시스템 설정 > 시스템 업데이트"** 화면(`app/Filament/Pages/SystemUpdate.php`)에서 버튼 클릭으로
`Artisan::call()`을 같은 PHP 프로세스 안에서 실행합니다(FTP로 새 파일만 올리고 이 버튼을 누르는 방식).

---

## 6. 핵심 서비스 클래스 (`app/Services/`)

실제 비즈니스 로직은 대부분 여기 모여 있고, 컨트롤러/Filament는 이 서비스들을 호출만 합니다.

| 서비스 | 역할 |
|---|---|
| `SiteSettingService` | **가장 중요.** 모든 관리자 설정값을 `site_settings` 테이블에서 key-value로 읽고 씀. 새 설정을 추가하려면 `SiteSettings.php`(Filament 페이지)에 폼 필드 추가 + `keyGroupMap()`에 그룹 등록만 하면 됨 |
| `EmailService` | 이메일 발송 통합 — `email_templates` 테이블의 타입별 템플릿을 변수 치환해 발송. 새 메일 타입 추가 시 `EmailTemplateSeeder`에 정의 추가 + `email_{타입}_enabled` 설정 키 하나만 있으면 기존 인프라 그대로 재사용 가능 |
| `SmsService` / `KakaoAlimTalkService` | 문자/알림톡 발송(알리고/coolsms/Twilio 등, 전부 공급사 키 필요) |
| `CaptchaService` / `IdentityVerificationService` | 스팸 방지 / 본인인증(각각 공급사 키 필요, 자체 수식 캡차만 키 불필요) |
| `GeoIpService` / `LoginCountryAlertService` | 로그인 국가 변경 감지·알림(8-3 항목 필독, 키 불필요하지만 정적 데이터 수동 갱신 필요) |
| `DormantAccountService` | 휴면계정 전환/강제탈퇴 처리 로직 |
| `HtmlSanitizerService` | 리치에디터/메일 본문 HTML 화이트리스트 필터링(XSS 방지) |
| `UploadService` | 파일 업로드/삭제/복사(`duplicate()`) 공용 처리 — 레코드를 복제(`replicate()`)할 때 첨부 이미지도 새 경로로 함께 복사해 원본과 파일을 공유하지 않게 함(배너/게시판/팝업/페이지의 "복제" 액션이 사용) |
| `AiChatService` | AI 비서(관리자 채팅 위젯) 오케스트레이션 — `app/Services/Ai/`의 `AiProviderContract`를 구현하는 `OpenAiProvider`/`GeminiProvider` 중 사이트 설정에 키가 있는 제공자만 노출. 새 제공자를 추가하려면 이 인터페이스만 구현하면 됨 |

---

## 7. Filament(관리자 패널) 사용 안내

- **Resource** (`app/Filament/Resources/{모델명}/`): 하나의 Eloquent 모델에 대한 CRUD 화면 세트를 자동 생성. `{모델명}Resource.php`에 `form()`(입력 폼 필드 정의)과 `table()`(목록 화면 컬럼/필터/액션 정의)만 정의하면 생성/수정/목록/삭제 화면이 전부 만들어집니다. 실제 화면 로직은 `Pages/` 하위(`ListXxx`, `CreateXxx`, `EditXxx`)에서 오버라이드 가능.
- **Page** (`app/Filament/Pages/`): 특정 모델의 CRUD가 아닌 단일 커스텀 화면(사이트 설정, 마케팅 메일 발송, 시스템 업데이트 등). `form()`으로 폼을 정의하고 `save()`/버튼 액션을 직접 구현.
- **권한 체계**: `admin_role`이 `super`(슈퍼관리자, 모든 화면 접근)와 `manager`(일반관리자, `admin_permissions` 배열에 등록된 화면만 접근)로 나뉨. 리소스에 `HasPermissionCheck` 트레이트 + `protected static string $permissionKey`를 지정하면 자동으로 권한 검사가 적용됩니다. 시스템 설정류(메뉴/약관/금칙어/사이트 설정 등 위험도 높은 화면)는 `RequiresSuperAdmin` 트레이트로 아예 슈퍼관리자만 접근 가능하게 막혀 있습니다.
- **공통 리치에디터 설정**: `app/Filament/Concerns/HasRichEditorDefaults.php`의 `richEditor()` 헬퍼 하나를 8개 리소스(페이지/게시글/약관/팝업/문의/유지보수리포트/이메일템플릿/마케팅메일)가 공유합니다. 툴바 버튼이나 파일첨부 설정을 전체적으로 바꾸려면 이 파일 하나만 고치면 됩니다.

---

## 8. 관리자가 코드 없이 바꿀 수 있는 것 vs 개발자가 코드를 고쳐야 하는 것

### 8-1. 관리자 패널(`/admin`)에서 코드 수정 없이 바로 바꿀 수 있는 것
- 사이트명/로고/파비콘/SEO/스크립트 삽입 등 전반적인 사이트 설정("사이트 설정" 화면의 각 탭)
- 이메일 발송 on/off + 각 메일 제목/본문("이메일 템플릿" 화면)
- SMTP, SMS/알림톡 공급사, 소셜로그인, 본인인증, 캡차 등 외부 연동 키(전부 "사이트 설정" 화면)
- 게시판/페이지/메뉴/배너/팝업/약관 콘텐츠 자체(각 리소스 화면)
- 휴면계정/강제탈퇴 임계값, 로그인 국가변경 알림 등 대부분의 정책성 숫자값

### 8-2. 개발자가 코드를 고쳐야 하는 것(관리자 화면에 없음)
- 새 게시판 스킨(레이아웃) 추가 — `resources/views/board/skins/`에 새 폴더 추가 후 게시판 관리에서 선택 가능해짐
- 새 이메일 타입 추가 — `EmailTemplateSeeder`에 정의 추가(코드) 후 관리자 화면에서 활성화(설정)
- 새로운 "주기적 처리" 기능 — 크론 없는 환경 패턴(5번 항목)을 따라 직접 구현 필요
- 관리자 화면 자체의 필드/화면 구성 변경

### 8-3. 반드시 알아야 할 유지보수 항목: GeoIP 데이터 수동 갱신
로그인 국가변경 감지 기능(`LoginCountryAlertService`)은 외부 API를 호출하지 않고
`database/geoip/dbip-country-ipv4.csv`(35만여 건의 국가별 IP 대역, db-ip.com "IP to Country Lite",
CC BY 4.0 라이선스)를 `ip_country_ranges` 테이블에 담아 로컬 조회합니다. **크론이 없어 자동 갱신이
안 되므로**, IP 대역 정확도를 유지하려면 관리자가 가끔(예: 1년에 한두 번) 아래 순서로 데이터를
새로 받아 반영해야 합니다.
```
php artisan geoip:import
```
(기본적으로 `database/geoip/dbip-country-ipv4.csv`를 읽음 — 최신 CSV로 교체 후 실행)
CSV 최신본은 `https://github.com/sapics/ip-location-db`(dbip-country 폴더)에서 무료로 받을 수 있습니다(가입/키 불필요).

---

## 9. 미들웨어 요약 (`app/Http/Middleware/`)

| 미들웨어 | 역할 |
|---|---|
| `RequireInstallation` | 설치 완료 전에는 모든 요청을 `install.php`로 유도 |
| `ConfigureSessionSecurity` | 세션 쿠키 Secure 옵션을 사이트 설정값에 따라 매 요청 반영 |
| `SiteIpBlocklist` | 사이트 설정에 등록된 IP/대역의 방문자 접근 차단(관리자 제외) |
| `AdminIpWhitelist` | 관리자 패널 접속을 허용 IP로 제한(슈퍼관리자는 예외) |
| `AdminDebugMode` | 사이트 설정에서 켠 경우에만 관리자에게 상세 에러 페이지 노출 |
| `SecurityHeaders` | 보안 헤더 일괄 적용 |
| `RecordAdminAccess`, `PruneAdminAuditLogs`, `ProcessDormantAccounts`, `ApplyScheduledPolicyChanges`, `PruneAiChatHistory`, `PruneInquiries`, `SyncVendorNotices` | 크론 대체 배치 처리(5번 항목 참고) |
| `CheckUserLevel` (`auth.level`) | 게시판 등에서 회원 레벨 기준 접근 제어 |
| `RecordVisit` (`record.visit`) | 방문자 통계 기록 |

---

## 10. 키/가입이 필요해 검증이 제한적인 기능

아래 기능들은 코드는 완성되어 있으나, 실제 공급사 계정/키가 없으면 최종 동작까지는 검증되지
않았습니다(관리자 화면에서 설정 후 반드시 실제 발송/인증 테스트 권장):

- 소셜 로그인(Google/카카오/네이버)
- SMS/카카오 알림톡(알리고/coolsms/Twilio)
- reCAPTCHA v3 / hCaptcha / Cloudflare Turnstile(자체 수식 캡차는 키 불필요)
- 본인인증(KG이니시스/NICE)

---

## 11. 로컬 개발 환경(Docker)

로컬 PHP가 8.3 미만이면 `docker-compose.yml`(Laravel Sail 기반, PHP 8.3 + MySQL 8.4 + Mailpit)로
개발합니다. 모든 `artisan`/`composer` 명령은 반드시 아래처럼 컨테이너 안에서 실행하세요.
```
docker compose up -d
docker compose exec laravel.test php artisan migrate
```
접속 주소는 `http://127.0.0.1:8090` (docker-compose.yml에 설정된 포트), 메일 확인은 Mailpit
`http://127.0.0.1:8025`.

> **로컬 개발용 관리자 계정**: `db:seed`(AdminUserSeeder)가 만드는 기본 계정은
> `admin@admin.com` / `admin1234`입니다. **이 값은 로컬 개발 전용**이며, 실제 배포는
> `install.php` 설치 마법사에서 직접 관리자 계정을 만들므로 이 기본값이 노출되지 않습니다.

---

## 12. 새 기능 추가하기 — 실전 가이드

Filament은 `app/Filament/Resources/`, `app/Filament/Pages/` 아래에 파일만 만들어두면
**자동으로 인식**합니다(`AdminPanelProvider`의 `discoverResources()`/`discoverPages()`). 어디에도
수동으로 등록할 필요가 없습니다 — 이 점이 "새 기능 추가"를 단순하게 만들어주는 핵심입니다.

### 12-1. 기본 순서 (관리자 화면이 있는 기능이라면 공통)

1. **마이그레이션**(`database/migrations/`, 새 파일만 추가 — 기존 파일 수정 금지)
2. **모델**(`app/Models/`) — 비즈니스 규칙(예: 상태 계산)은 컨트롤러/Resource가 아니라 모델
   메서드로 넣는 게 이 프로젝트의 관례입니다(아래 12-2의 `recruitmentStatus()` 참고).
3. **Filament Resource**(`app/Filament/Resources/{기능명}/{기능명}Resource.php` +
   `Pages/{List,Create,Edit}{기능명}.php`) — 파일만 만들면 자동 인식.
4. **권한 등록**(아래 12-1-a) — 이걸 빼먹으면 일반관리자에게 권한을 줄 방법이 없어집니다.
5. 언어별로 다른 콘텐츠가 필요하면 `locale` 컬럼 + `HasLocaleScope` 트레이트(12-4 참고).
6. 방문자(프론트)도 접근해야 하는 기능이면 컨트롤러 + 라우트 + 뷰(12-3 참고).

#### 12-1-a. 권한 부여하기 (가장 많이 빠뜨리는 단계)

이 프로젝트의 관리자 권한은 2단계입니다: `admin_role`이 `super`(슈퍼관리자, 전체 접근)와
`manager`(일반관리자, 아래 체크리스트로 화면 단위 허용/차단)로 나뉩니다. **새 Resource를
만들 때마다 반드시 아래 2곳을 함께 고쳐야** 일반관리자에게 그 화면 권한을 줄 수 있습니다.

1. 새 Resource 클래스에 트레이트 + 권한 키 선언:
   ```php
   use App\Filament\Concerns\HasPermissionCheck;

   class ReservationResource extends Resource
   {
       use HasPermissionCheck;

       protected static string $permissionKey = 'reservations'; // ← 임의의 고유 키
   }
   ```
2. `app/Filament/Resources/Users/UserResource.php`의 `CheckboxList::make('admin_permissions')`
   `->options([...])` 배열에 같은 키를 추가:
   ```php
   'reservations' => '예약관리',
   ```
   이 두 곳이 짝이 안 맞으면(트레이트만 달고 옵션에 안 넣거나, 반대로 옵션에만 넣고 트레이트를
   안 달면) 각각 "일반관리자에게 권한을 줄 수 없음" 또는 "체크해도 실제로는 아무 화면도 안 막힘"
   문제가 생깁니다.
   > ⚠️ **`admin_permissions`의 실제 저장 형식**: Filament의 `CheckboxList`는 선택된 값을
   > `['boards', 'posts']`처럼 **단순 배열**로 저장합니다(`{boards: true, posts: true}` 같은
   > 연관배열이 아님). `HasPermissionCheck::canAccess()`도 `in_array($key, $permissions, true)`로
   > 이 형식을 그대로 확인합니다 — 실제로 관리자 화면(회원 만들기 폼)에서 체크박스를 켜서
   > 저장한 값이 아니라 tinker 등으로 연관배열을 직접 넣어 테스트하면, 겉보기엔 맞는 것 같아도
   > 실제 폼 저장 형식과 달라 검증을 통과하지 못하는 걸 놓칠 수 있습니다(2026-07-05에 실제로
   > 이 불일치 때문에 매니저 권한이 화면상 체크되어 있어도 전부 403이 나는 버그가 있었음 —
   > 반드시 실제 폼으로 저장한 계정으로 로그인해서 확인하세요).
3. 위험도가 높아 **슈퍼관리자만** 써야 하는 화면(메뉴/약관/사이트 설정류)은 `HasPermissionCheck`
   대신 `App\Filament\Concerns\RequiresSuperAdmin` 트레이트를 붙이면 됩니다(체크박스 자체가 필요
   없어짐 — `LanguageResource`/`PolicyResource` 참고).
4. `navigationGroup`은 기존 4개(`회원 관리`/`콘텐츠 관리`/`마케팅`/`시스템 설정`) 중 성격에 맞는
   것을 그대로 재사용하세요(새 그룹을 만들려면 `AdminPanelProvider`의 `navigationGroups()`에도
   추가해야 함).
5. **대시보드 위젯을 새로 추가한다면 권한 체크와 스코프를 반드시 위젯 안에 따로 넣어야
   합니다.** `HasPermissionCheck`/`HasLocaleScope`는 Resource의 `getEloquentQuery()`에만
   걸리는 트레이트라 `app/Filament/Widgets/`의 위젯에는 자동 적용되지 않습니다 — 위젯은
   `public static function canView(): bool`을 직접 구현해 `auth()->user()?->hasAdminPermission('키')`
   로 권한 없으면 위젯 자체를 숨기고, 쿼리도 `Post::query()->visibleTo($user)`/
   `Inquiry::query()->visibleTo($user)`처럼 직접 스코프해야 합니다(두 모델 다 담당
   게시판·담당 언어 기준으로 걸러주는 로컬 스코프가 이미 있음 — Resource가 쓰는 것과
   동일한 기준). 이걸 빼먹으면 화면(Resource)은 권한대로 잘 막혀 있는데 대시보드에는 다른
   팀 게시판 글 제목이나 담당 아닌 상담 내용이 그대로 보이는 정보노출이 생깁니다
   (2026-07-05에 실제로 이 문제가 있었음).

#### 12-1-b. 복잡한 Resource — 폴더 안에 폴더(RelationManagers)

지금까지는 `{기능명}Resource.php` + `Pages/{List,Create,Edit}{기능명}.php` 정도로 끝나는
단순한 경우만 다뤘습니다. 그런데 "게시판 하나에 카테고리 여러 개"처럼 **한 화면 안에서
연결된 하위 모델까지 함께 관리**해야 하는 경우가 있습니다 — 이럴 땐 Resource 폴더 밑에
`RelationManagers/` 폴더를 하나 더 만듭니다.

```
app/Filament/Resources/Boards/
├── BoardResource.php
├── Pages/
│   ├── ListBoards.php
│   ├── CreateBoard.php
│   └── EditBoard.php
└── RelationManagers/
    └── CategoriesRelationManager.php   ← 게시판 수정 화면 안에 "카테고리" 탭으로 나타남
```

```php
// app/Filament/Resources/Boards/RelationManagers/CategoriesRelationManager.php
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories'; // Board 모델의 relation 메서드명

    public function form(Schema $schema): Schema { /* 카테고리 자체의 입력 폼 */ }
    public function table(Table $table): Table { /* 카테고리 목록/추가/삭제 */ }
}
```

> ⚠️ **`RelationManagers/`는 자동 인식되지 않습니다.** 이 문서 맨 위에서 말한 "파일만 만들면
> 자동 인식"은 `Resources/`, `Pages/` 최상위에만 해당하고, RelationManager는 부모
> Resource에서 직접 등록해줘야 화면에 나타납니다.
> ```php
> // BoardResource.php
> public static function getRelations(): array
> {
>     return [
>         CategoriesRelationManager::class,
>     ];
> }
> ```
> 모델 쪽에도 당연히 `categories(): HasMany` 같은 관계 메서드가 있어야 합니다. 이 패턴은
> 중첩도 가능합니다 — 예를 들어 "강의 안에 챕터, 챕터 안에 영상" 같은 3단계 구조도 챕터
> RelationManager 안에서 다시 자식 Repeater/관계를 다루는 식으로 얼마든지 확장할 수
> 있습니다(다만 Filament의 RelationManager 자체는 한 단계만 지원하므로, 그 다음 단계부터는
> Repeater나 별도 화면으로 처리하는 게 일반적입니다).

#### 12-1-c. `form()`/`table()`만으로 안 되는 화면 — `resources/views/filament/`까지 직접 만들기

지금까지의 `Page`는 `form()`이나 `table()`만 정의하면 Filament가 화면을 알아서 그려줬습니다.
하지만 "SQL 직접 실행 도구"처럼 **폼 + 실행 결과 + 에러 메시지를 한 화면에 같이 보여줘야
하는 경우**처럼, Filament의 기본 레이아웃만으로는 부족한 화면은 `resources/views/filament/`
밑에 **직접 Blade 뷰를 만들어서** `$view` 프로퍼티로 연결합니다.

```
app/Filament/Pages/DatabaseQueryTool.php   ← $view = 'filament.pages.database-query-tool';
resources/views/filament/pages/database-query-tool.blade.php
```

```php
// app/Filament/Pages/DatabaseQueryTool.php
class DatabaseQueryTool extends Page
{
    protected string $view = 'filament.pages.database-query-tool';

    public ?string $resultHtml = null;   // ← Livewire 공개 프로퍼티. 뷰에서 바로 씀
    public ?string $errorMessage = null;

    public function form(Schema $schema): Schema { /* SQL 입력창 등 */ }

    public function run(): void { /* SQL 실행 후 $resultHtml/$errorMessage 채움 */ }
}
```

```blade
{{-- resources/views/filament/pages/database-query-tool.blade.php --}}
<x-filament-panels::page>
    <form wire:submit="run">
        {{ $this->form }}
        <x-filament::button type="submit" wire:confirm="실행하시겠습니까?">실행</x-filament::button>
    </form>

    @if ($errorMessage)
        <pre>{{ $errorMessage }}</pre>
    @endif
    @if ($resultHtml)
        <div>{!! $resultHtml !!}</div>
    @endif
</x-filament-panels::page>
```

`<x-filament-panels::page>`로 감싸기만 하면 Filament 레이아웃(사이드바/헤더 등)은 그대로
유지되고, 안쪽은 완전히 자유롭게 구성할 수 있습니다. `$this->form`은 위 폼 정의를 그 자리에
그대로 렌더링하라는 뜻이고, `resultHtml`/`errorMessage`처럼 Page 클래스의 **public
프로퍼티는 뷰에서 바로 `{{ }}`로 접근**할 수 있습니다(일반 Livewire 컴포넌트와 동일).
이 패턴을 쓰는 다른 예: `MediaLibrary`(미디어 라이브러리 그리드), `SystemUpdate`(배포 후
버튼 클릭으로 마이그레이션 실행), `ErrorLogViewer`(에러 로그 목록).

### 12-2. 실제 사례로 보는 패턴 — 게시판에 필드 추가하기 (모집용 게시판)

"채용공고처럼 모집기간이 있는 게시판"은 이미 구현되어 있습니다(`boards` 테이블 확장 방식 —
별도 게시판 타입을 만들지 않고 **기존 게시판에 선택적 필드만 추가**하는 패턴). 필드를 하나 더
추가해야 할 때 그대로 따라할 수 있는 실제 코드입니다.

1. **마이그레이션** (`database/migrations/2025_01_06_000001_add_recruitment_period_to_boards_table.php`):
   ```php
   Schema::table('boards', function (Blueprint $table) {
       // 둘 다 비어있으면 상시 모집(기간 제한 없음)
       $table->timestamp('recruitment_start_at')->nullable()->after('identity_verification_consent_text');
       $table->timestamp('recruitment_end_at')->nullable()->after('recruitment_start_at');
   });
   ```
2. **모델 메서드** (`app/Models/Board.php`) — "상태 계산"은 컨트롤러가 아니라 모델에:
   ```php
   public function recruitmentStatus(): ?string
   {
       if (! $this->recruitment_start_at && ! $this->recruitment_end_at) {
           return null; // 상시 게시판 — 배지 자체를 안 보여줌
       }
       $now = now();
       if ($this->recruitment_start_at && $now->lt($this->recruitment_start_at)) return '예정';
       if ($this->recruitment_end_at && $now->gt($this->recruitment_end_at)) return '마감';
       return '기간중';
   }
   ```
3. **관리자 폼 필드 2개 추가** (`BoardResource::form()`의 "기본 설정" 탭 안에):
   ```php
   DateTimePicker::make('recruitment_start_at')->label('모집 시작일시'),
   DateTimePicker::make('recruitment_end_at')->label('모집 종료일시'),
   ```
   목록 화면 컬럼에도 상태 배지 추가(`BoardResource::table()`):
   ```php
   TextColumn::make('recruitment_status')->label('모집 상태')
       ->state(fn (Board $record) => $record->recruitmentStatus())
       ->badge()
       ->color(fn (?string $state) => match ($state) {
           '예정' => 'gray', '기간중' => 'success', '마감' => 'danger', default => null,
       })
       ->placeholder('-'),
   ```
4. **프론트 표시용 공용 파셜** (`resources/views/partials/recruitment-status.blade.php`) 만들어
   게시판 스킨(`board/skins/default/list.blade.php`, `gallery.blade.php`)에 `@include`.
5. **의도적으로 하지 않은 것**: 이 필드가 있어도 글쓰기 자체를 막지는 않습니다(정보 표시 전용).
   "기간 지나면 글쓰기 차단"처럼 실제 접근 제어가 필요하면 `BoardFrontController::denyIfCannotWrite()`
   같은 기존 권한 체크 메서드에 조건을 추가하는 식으로 확장하면 됩니다.

이 패턴(모델에 nullable 필드 추가 → 모델 메서드로 상태/파생값 계산 → Resource 폼/테이블에 필드
추가 → 필요하면 프론트 파셜)은 이 프로젝트에서 "기존 리소스에 선택적 기능을 얹을 때" 반복적으로
쓰인 방식입니다(예: 게시판의 본인인증 옵션, 배너/팝업의 노출기간도 동일한 사고방식).

### 12-3. 처음부터 새 기능 만들기 — "예약" 기능을 예로

아래는 실제로 구현되어 있지 않은 **가상의 예시**입니다. "회원/비회원이 프론트에서 예약을
신청하고, 관리자가 목록을 보고 확정/거절 처리하는" 기능을 이 프로젝트 관례대로 만든다면
이런 순서가 됩니다.

**1) 마이그레이션** — 게시판/문의(`inquiries`)와 비슷하게, 회원/비회원 둘 다 받을 수 있게 설계:
```php
// database/migrations/2025_01_12_000001_create_reservations_table.php
Schema::create('reservations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // 비회원이면 null
    $table->string('locale', 5)->default('ko'); // 다국어 콘텐츠라면(12-4 참고)
    $table->string('name', 50);
    $table->string('phone', 20);
    $table->string('email')->nullable();
    $table->dateTime('reserved_at');   // 예약 희망 일시
    $table->string('status')->default('pending'); // pending/confirmed/rejected
    $table->text('memo')->nullable();  // 관리자 메모
    $table->timestamps();
});
```

**2) 모델** (`app/Models/Reservation.php`) — 이 프로젝트의 `Inquiry` 모델과 거의 동일한 뼈대:
```php
class Reservation extends Model
{
    protected $fillable = ['user_id', 'locale', 'name', 'phone', 'email', 'reserved_at', 'status', 'memo'];

    protected function casts(): array
    {
        return ['reserved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**3) 관리자 화면** (`app/Filament/Resources/Reservations/ReservationResource.php`) — `PopupResource`처럼
단순한 리소스라면 이 정도로 충분합니다:
```php
class ReservationResource extends Resource
{
    use HasPermissionCheck; // + 다국어 콘텐츠라면 HasLocaleScope도

    protected static string $permissionKey = 'reservations';
    protected static ?string $model = Reservation::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static ?string $navigationLabel = '예약 관리';
    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('이름')->required(),
            TextInput::make('phone')->label('연락처')->required(),
            DateTimePicker::make('reserved_at')->label('예약 일시')->required(),
            Select::make('status')->label('상태')
                ->options(['pending' => '대기', 'confirmed' => '확정', 'rejected' => '거절'])
                ->native(false)->required(),
            Textarea::make('memo')->label('관리자 메모')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('이름'),
            TextColumn::make('reserved_at')->label('예약 일시')->dateTime('Y-m-d H:i'),
            TextColumn::make('status')->label('상태')->badge(),
        ])->recordActions([EditAction::make()])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
```
그리고 12-1-a대로 `UserResource`의 권한 옵션 배열에 `'reservations' => '예약관리'` 한 줄 추가.

**4) 프론트(방문자) 화면이 필요하면** — `routes/web.php`의 `$frontRoutes` 클로저(다국어
접두사가 자동으로 붙는 구간) 안에 추가:
```php
Route::get('/reservation', [ReservationController::class, 'create'])->name('reservation.create');
Route::post('/reservation', [ReservationController::class, 'store'])->name('reservation.store');
```
컨트롤러는 `InquiryController`(비회원도 받는 신청 폼)를 그대로 참고하면 되고, 뷰에서 링크를
만들 때는 반드시 `route()`가 아니라 **`front_route()`**를 쓰세요(언어 접두사가 자동으로 붙는
전역 헬퍼 — `app/helpers.php`). `route()`를 직접 쓰면 다국어 사이트에서 항상 기본 언어로
튕기는 버그가 납니다 — 실제로 이 프로젝트 초기에 프론트 화면 60여 곳에서 이 실수가 났던 적이
있어(라우트가 `board.index`/`ja.board.index`처럼 언어별로 다른 이름으로 등록되기 때문), 새
화면을 추가할 때마다 꼭 `front_route()`를 쓰는지 확인하세요.

**5) 메뉴에 연결하기** — 관리자 패널 "메뉴 관리"(시스템 설정 그룹, 슈퍼관리자 전용) 화면에서
"생성" 버튼을 누르고 아래 필드를 채웁니다:

| 필드 | 입력값(예) | 비고 |
|---|---|---|
| 메뉴명 | 예약하기 | 이 언어에서 메뉴에 표시될 텍스트 |
| 언어 | 한국어 | **언어별로 메뉴 행을 따로 만들어야 합니다 — 아래 주의사항 참고** |
| 상위 메뉴 | (원하는 그룹, 선택) | 최대 3단계 뎁스 |
| 타입 | URL | `board`/`page`/`url`/`none`(그룹텍스트) 4종뿐 — 새 타입을 늘릴 필요 없음(아래 설명) |
| URL | `/reservation` | ko(기본 언어)는 접두사 없이 그대로 |
| 열기 방식 | 현재 창 | |
| 최소 접근 레벨 / 레벨 미달 시 처리 | 필요에 따라 | |

**⚠️ 다국어 사이트에서 반드시 주의할 점**: `Menu`는 `locale` 컬럼을 가진 완전히 별도의
레코드라서, **언어마다 메뉴 행을 따로 만들어야** 그 언어의 메뉴 트리에 나타납니다(하나만 만들면
그 언어에서만 보임 — 실제로 이 프로젝트의 더미 데이터도 "1:1 문의" 메뉴가 한국어에만 있고
영어/일본어에는 없는 상태입니다. 대신 퀵메뉴/하단 상담폼은 `front_route()`로 만들어서 언어별로
자동으로 나타나므로 실제로 접근 자체가 막히지는 않습니다). 그리고 **`type=board`/`type=page`와
`type=url`은 언어 접두사가 처리되는 방식이 다릅니다**(`MenuService::resolveUrl()` 참고):
- `board`/`page` 타입은 게시판/페이지를 **드롭다운으로 선택**하면 되고, 실제 URL은 매번
  현재 언어에 맞는 라우트(`{prefix}board.index`/`{prefix}page.show`)로 **자동 생성**됩니다.
- `url` 타입은 입력한 문자열을 **그대로** 출력합니다(자동 변환 없음). 그래서 영어 메뉴 행을
  만들 때는 URL 칸에 `/reservation`이 아니라 **`/en/reservation`**처럼 언어 접두사를 직접
  입력해야 합니다(일본어는 `/ja/reservation`). 접두사를 빠뜨리면 영어 메뉴를 눌렀는데 한국어
  화면으로 넘어가는 것처럼 보이는 흔한 실수가 됩니다.

**6) (선택) 확정 알림 메일** — 새 메일 타입이 필요하면 `EmailTemplateSeeder::definitions()`에
타입 하나 추가 + `SiteSettings`의 이메일 탭에 `email_reservation_confirmed_enabled` 같은 토글
하나 추가하면, 기존 `EmailService::send('reservation_confirmed', ...)` 인프라를 그대로 재사용할
수 있습니다(6번 항목 참고). 한국을 제외한 모든 언어권이 받는 범용 메일이라면 영어 버전만
`localizedDefinitions()`에 추가 — 새 언어가 늘어도 그 영어 버전으로 자동 폴백됩니다.

### 12-4. 다국어(언어별로 다른 콘텐츠)가 필요한 기능이라면

`locale` 컬럼(varchar 5, 기본값 `'ko'`) + `App\Filament\Concerns\HasLocaleScope` 트레이트만
추가하면 "담당 언어"가 지정된 일반관리자에게 자동으로 그 언어 콘텐츠만 보이게 스코프됩니다
(쿼리 하나 오버라이드하는 트레이트라 리소스마다 따로 구현할 필요 없음 — `HasLocaleScope.php`
참고). 슬러그처럼 사람이 직접 입력하는 고유값이 있다면 `(locale, slug)` 복합 unique를 쓰세요
(단독 `slug` unique면 같은 슬러그를 언어별로 재사용할 수 없습니다 — `boards`/`pages` 참고).
> ⚠️ **프론트 컨트롤러에서 slug로 레코드를 찾는 모든 코드는 반드시 `where('locale',
> app()->getLocale())`도 같이 걸어야 합니다.** `(locale, slug)` 복합 unique를 쓰는 테이블은
> 같은 slug가 언어마다 여러 행 존재할 수 있어서, locale 조건 없이 `where('slug', $slug)
> ->firstOrFail()`만 쓰면 Eloquent가 아무 언어의 행이나 먼저 찾은 걸 반환합니다 — 그 행의 id로
> 하위 리소스(게시글 등)를 다시 찾으면 실제로는 다른 게시판을 찾은 거라 404가 남
> (`BoardFrontController::resolveBoard()`/`FrontController::page()`는 이미 이렇게 되어 있고,
> `CommentController::store()`가 이 조건을 빠뜨려서 2026-07-05에 실제로 en/ko가 같은 slug를
> 쓰는 게시판에서 댓글 작성이 404 나는 버그가 있었습니다).
번역이 없을 때 어떻게 대체할지는 콘텐츠 성격에 따라 다르게 결정했습니다:
- 법적으로 꼭 있어야 하는 콘텐츠(약관 등) → 없으면 **기본 언어(한국어)로 폴백**
- 시스템이 자동 발송하는 이메일 → 없으면 **영어로 폴백**(한국어만 예외)
- 그 외 일반 콘텐츠(게시판/배너/팝업 등) → 폴백 없이 **그 언어에 없으면 안 보임**

새 기능이 위 세 성격 중 어디에 가까운지 먼저 정하고 나서 폴백 로직을 넣으세요.

---

## 13. 재사용 가능한 슬라이드 컴포넌트 (`<x-slider>`)

배너/게시판 등 어디서든 쓸 수 있는 반응형 캐러셀입니다. [Swiper](https://swiperjs.com) 14를
`public/js/vendor/swiper`, `public/css/vendor/swiper`에 벤더링해 빌드 도구 없이 그대로
서빙하고(빌드 파이프라인이 없는 이 프론트엔드 관례를 그대로 따름), 그 위에 이 프로젝트
전용 래퍼/스타일(`resources/views/components/slider.blade.php`, `public/js/slider-init.js`,
`public/css/slider.css`)을 얹은 구조입니다. Swiper 본체와 이 프로젝트의 스타일은 서로 다른
파일로 분리되어 있어 Swiper를 다른 버전으로 교체해도 우리 쪽 CSS/JS는 건드릴 필요가
없습니다.

```blade
{{-- 이미지 목록을 그대로 넘기면 자동으로 슬라이드 생성(배너 등) --}}
<x-slider :items="$banners" />

{{-- 화살표/인디케이터 없이 자동재생만(공간이 좁은 곳) --}}
<x-slider :items="$footerBanners" :arrows="false" pagination="none" />

{{-- 직접 마크업을 넣는 슬롯 모드 + 한 화면에 여러 장(게시판 카드 등) --}}
<x-slider :slides-per-view="[3, 2, 1]" pagination="numbers">
    @foreach ($posts as $post)
        <div class="swiper-slide">...커스텀 카드...</div>
    @endforeach
</x-slider>
```

- `slidesPerView`는 `[데스크톱, 태블릿, 모바일]` 배열이고, 몇 장이 보이든 다음/이전 버튼은
  항상 한 칸씩만 이동합니다(`slidesPerGroup`을 코드에서 항상 1로 고정).
- `pagination`은 `'dots' | 'numbers' | 'none'` 중 선택. 슬라이드가 1개뿐이면 화살표/
  인디케이터가 자동으로 숨겨집니다.
- 이전/다음 버튼 아이콘은 `prevIcon`/`nextIcon` 슬롯으로 사용처마다 다르게 바꿀 수 있습니다.
- 접근성: 키보드 좌우/Home/End 탐색, ARIA 라이브 리전으로 슬라이드 변경 안내, 자동재생은
  포커스/hover 시 자동 일시정지 + 항상 노출되는 일시정지 버튼(WCAG 2.2.2 대응),
  `prefers-reduced-motion`이면 자동재생 자체가 꺼집니다.
- 게시판 최신글 위젯에 넣고 싶으면 기존 `<x-latest-posts>`의 스킨 폴더 컨벤션을 그대로 써서
  `<x-latest-posts board="notice" skin="slider" />`처럼 쓰면 됩니다(`resources/views/partials/latest-posts/slider/index.blade.php` 참고).
