# CMS Admin — 테이블 명세서 (공통)

> ⚠️ **이 문서는 마이그레이션으로 스키마가 바뀔 때마다 함께 업데이트해야 합니다.**
> 테이블/컬럼 추가·삭제·변경 시 이 문서와 `docs/erd.html`도 같이 고칠 것 (PROJECT.md > DB 규칙 참고).


공통 CMS 스키마 28개 테이블 — `dev`/LFboard 등 LMS 기능이 없는 브랜치 기준. (future-mobility 전용 LMS 테이블은 제외됨 — 해당 브랜치는 `docs/table-spec.md`에 전체 버전이 있음)

## 테이블 목록

| No | 테이블명 | 설명 | 컬럼 수 |
|---|---|---|---|
| 1 | `users` | 회원 및 관리자 계정 | 38 |
| 2 | `languages` | 다국어(로케일) 설정 | 9 |
| 3 | `ip_country_ranges` | IP 대역 → 국가코드 매핑(해외 로그인 탐지용) | 4 |
| 4 | `user_login_countries` | 회원별 로그인 국가 이력 | 7 |
| 5 | `social_accounts` | 소셜 로그인 연동 계정 | 8 |
| 6 | `policy_consents` | 회원별 약관 동의 이력 | 6 |
| 7 | `admin_audit_logs` | 관리자 활동 감사로그 — 생성/수정/삭제 등 관리자 조작 이력 | 12 |
| 8 | `site_settings` | 사이트 설정(key-value) | 6 |
| 9 | `email_templates` | 발송 이메일 템플릿 | 9 |
| 10 | `menus` | 메뉴(헤더/사이트맵, 최대 3단계 트리) | 16 |
| 11 | `boards` | 게시판 설정(스킨/권한/옵션) | 28 |
| 12 | `board_categories` | 게시판별 카테고리 | 6 |
| 13 | `posts` | 게시글 | 21 |
| 14 | `post_files` | 게시글 첨부파일 | 10 |
| 15 | `comments` | 게시글 댓글 | 13 |
| 16 | `policies` | 약관/정책(이용약관, 개인정보처리방침 등) | 18 |
| 17 | `popups` | 레이어 팝업 | 15 |
| 18 | `banners` | 배너(메인/푸터 등 위치별) | 18 |
| 19 | `banned_words` | 금칙어 | 5 |
| 20 | `inquiries` | 1:1 문의 | 19 |
| 21 | `media_files` | 미디어 라이브러리(이미지 보관/재사용) | 11 |
| 22 | `maintenance_reports` | 유지보수 업체 작업 보고서 | 10 |
| 23 | `marketing_mail_logs` | 마케팅 메일 발송 로그 | 9 |
| 24 | `vendor_notices` | 유지보수 업체 공지사항(외부 동기화) | 7 |
| 25 | `visit_logs` | 방문 로그(일별/IP/경로) | 7 |
| 26 | `ai_chat_conversations` | AI 챗봇 대화방(회원별) | 7 |
| 27 | `ai_chat_messages` | AI 챗봇 대화 메시지 | 6 |
| 28 | `pages` | 정적 페이지(회사소개 등) | 17 |

---

## 컬럼 명세

### `users` — 회원 및 관리자 계정

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `name` | varchar(255) | N |  |  |  | 이름 |
| `username` | varchar(50) | Y |  |  |  | 아이디(로그인용, 선택) |
| `email` | varchar(255) | N |  |  |  | 이메일(로그인 ID로도 사용) |
| `locale` | varchar(5) | N | ko |  |  | 선호 언어(로케일) |
| `email_verified_at` | timestamp | Y |  |  |  | 이메일 인증일시 |
| `password` | varchar(255) | N |  |  |  | 비밀번호(해시) |
| `password_changed_at` | timestamp | Y |  |  |  | 비밀번호 마지막 변경일시 |
| `level` | tinyint | N | 1 |  |  | 회원 등급(0=게스트, 관리자는 admin_role로 구분) |
| `admin_role` | varchar(255) | Y |  |  |  | 관리자 역할(super 등, 일반회원은 null) |
| `admin_permissions` | json | Y |  |  |  | 관리자 개별 권한 목록(JSON) |
| `vendor_notice_last_seen_id` | bigint unsigned | Y |  |  | `vendor_notices` | 마지막으로 확인한 유지보수 업체 공지 ID |
| `admin_locale_scope` | json | Y |  |  |  | 관리자가 관리 가능한 언어 범위(JSON) |
| `admin_board_scope` | json | Y |  |  |  | 관리자가 관리 가능한 게시판 범위(JSON) |
| `two_factor_secret` | text | Y |  |  |  | 2단계 인증(TOTP) 비밀키(암호화) |
| `two_factor_recovery_codes` | text | Y |  |  |  | 2단계 인증 복구코드(암호화, JSON) |
| `phone` | varchar(20) | Y |  |  |  | 연락처 |
| `nickname` | varchar(50) | Y |  |  |  | 닉네임 |
| `gender` | varchar(10) | Y |  |  |  | 성별 |
| `birthdate` | date | Y |  |  |  | 생년월일 |
| `homepage` | varchar(255) | Y |  |  |  | 홈페이지 URL |
| `address` | varchar(255) | Y |  |  |  | 주소 |
| `memo` | text | Y |  |  |  | 관리자 메모 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부(정지/탈퇴 시 false) |
| `marketing_agreed` | tinyint(1) | N | 0 |  |  | 마케팅 수신 동의 여부 |
| `marketing_agreed_at` | timestamp | Y |  |  |  | 마케팅 수신 동의일시 |
| `unsubscribe_token` | varchar(32) | Y |  |  |  | 메일 수신거부 링크용 토큰 |
| `last_login_at` | timestamp | Y |  |  |  | 마지막 로그인일시(휴면 판정 기준) |
| `dormant_at` | timestamp | Y |  |  |  | 휴면 전환일시 |
| `dormant_notice_sent_at` | timestamp | Y |  |  |  | 휴면 전환 사전 안내 발송일시 |
| `withdrawal_notice_sent_at` | timestamp | Y |  |  |  | 휴면 후 탈퇴(익명화) 사전 안내 발송일시 |
| `ci` | varchar(255) | Y |  |  |  | 본인확인기관 연계정보(CI, 암호화) |
| `di` | varchar(255) | Y |  |  |  | 본인확인기관 중복가입확인정보(DI, 암호화) |
| `phone_verified_at` | timestamp | Y |  |  |  | 휴대폰 본인인증 완료일시 |
| `remember_token` | varchar(100) | Y |  |  |  | 로그인 유지(remember me) 토큰 |
| `created_at` | timestamp | Y |  |  |  | 가입일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제(탈퇴)일시 |

### `languages` — 다국어(로케일) 설정

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `code` | varchar(5) | N |  |  |  | 언어코드(ko/en/ja 등) |
| `timezone` | varchar(64) | N | Asia/Seoul |  |  | 해당 언어 기본 표시 시간대 |
| `name` | varchar(255) | N |  |  |  | 언어명 |
| `is_default` | tinyint(1) | N | 0 |  |  | 기본 언어 여부 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `ip_country_ranges` — IP 대역 → 국가코드 매핑(해외 로그인 탐지용)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `ip_start` | int unsigned | N |  |  |  | IP 대역 시작(정수 변환값) |
| `ip_end` | int unsigned | N |  |  |  | IP 대역 끝(정수 변환값) |
| `country_code` | varchar(2) | N |  |  |  | 국가코드(ISO 2자리) |

### `user_login_countries` — 회원별 로그인 국가 이력

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 회원 |
| `country_code` | varchar(2) | N |  |  |  | 접속 국가코드(ISO 2자리) |
| `country_name` | varchar(100) | Y |  |  |  | 접속 국가명 |
| `first_seen_at` | timestamp | N |  |  |  | 해당 국가에서 처음 접속한 일시 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `social_accounts` — 소셜 로그인 연동 계정

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 연결된 회원 |
| `provider` | varchar(255) | N |  |  |  | 소셜 제공자(kakao/naver 등) |
| `provider_id` | varchar(255) | N |  |  |  | 제공자측 고유 ID |
| `access_token` | text | Y |  |  |  | 액세스 토큰 |
| `refresh_token` | text | Y |  |  |  | 리프레시 토큰 |
| `created_at` | timestamp | Y |  |  |  | 연동일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `policy_consents` — 회원별 약관 동의 이력

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 동의한 회원 |
| `type` | varchar(20) | N |  |  |  | 동의한 약관 종류 |
| `locale` | varchar(5) | N |  |  |  | 동의 시점 언어(로케일) |
| `version` | varchar(50) | Y |  |  |  | 동의한 약관 버전 |
| `agreed_at` | timestamp | N |  |  |  | 동의일시 |

### `admin_audit_logs` — 관리자 활동 감사로그 — 생성/수정/삭제 등 관리자 조작 이력

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `admin_user_id` | bigint unsigned | Y |  |  | `users` | 행위자 관리자 user_id(탈퇴 시 null) |
| `admin_name` | varchar(50) | Y |  |  |  | 행위 당시 관리자 이름 스냅샷(탈퇴 후에도 누가 했는지 남기기 위함) |
| `action` | varchar(20) | N |  |  |  | 수행한 동작(created/updated/deleted 등) |
| `auditable_type` | varchar(255) | N |  |  |  | 대상 모델 클래스명(다형 관계) |
| `auditable_id` | bigint unsigned | N |  |  |  | 대상 레코드 ID |
| `auditable_label` | varchar(255) | Y |  |  |  | 대상을 사람이 알아볼 수 있는 라벨(제목 등) |
| `ip` | varchar(45) | Y |  |  |  | 요청 IP |
| `url` | varchar(2048) | Y |  |  |  | 요청 URL |
| `before` | json | Y |  |  |  | 변경 전 값(JSON) |
| `changes` | json | Y |  |  |  | 변경 후 값(JSON) |
| `created_at` | timestamp | Y |  |  |  | 기록 일시 |

### `site_settings` — 사이트 설정(key-value)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `key` | varchar(255) | N |  |  |  | 설정 키 |
| `value` | longtext | Y |  |  |  | 설정 값 |
| `group` | varchar(255) | N | general |  |  | 설정 그룹(탭 구분) |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `email_templates` — 발송 이메일 템플릿

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `type` | varchar(255) | N |  |  |  | 템플릿 종류(발송 트리거) |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `name` | varchar(255) | N |  |  |  | 템플릿명 |
| `subject` | varchar(255) | N |  |  |  | 메일 제목 |
| `body` | longtext | N |  |  |  | 메일 본문(HTML) |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `menus` — 메뉴(헤더/사이트맵, 최대 3단계 트리)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `parent_id` | bigint unsigned | Y |  |  | `menus` | 상위 메뉴(최상위면 null) |
| `title` | varchar(255) | N |  |  |  | 메뉴명 |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `slug` | varchar(255) | Y |  |  |  | 식별용 슬러그 |
| `type` | varchar(255) | N | url |  |  | 연결 대상 유형(board/page/url/none) |
| `target_id` | bigint unsigned | Y |  |  |  | 연결 대상 ID(게시판/페이지 등) |
| `url` | varchar(255) | Y |  |  |  | 직접 URL(type=url인 경우) |
| `target` | varchar(255) | N | _self |  |  | 링크 열림 방식(_self/_blank) |
| `min_level` | tinyint | N | 1 |  |  | 노출 최소 회원등급 |
| `access_mode` | varchar(10) | N | hidden |  |  | 레벨 미달 시 처리 방식(hidden=숨김/locked=잠금표시) |
| `hidden_from_header` | tinyint(1) | N | 0 |  |  | 상단메뉴(GNB)에서만 숨김 여부(사이트맵에도 동일 적용) |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `boards` — 게시판 설정(스킨/권한/옵션)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `name` | varchar(255) | N |  |  |  | 게시판명 |
| `slug` | varchar(255) | N |  |  |  | URL 식별자 |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `skin` | varchar(255) | N | default |  |  | 목록 화면 스킨(list/gallery/faq/custom-fields 등) |
| `layout` | varchar(255) | N | list |  |  | 레이아웃 방식 |
| `use_editor` | tinyint(1) | N | 1 |  |  | 에디터 사용 여부 |
| `allow_comment` | tinyint(1) | N | 1 |  |  | 댓글 허용 여부 |
| `allow_reply` | tinyint(1) | N | 1 |  |  | 답글 허용 여부 |
| `allow_file` | tinyint(1) | N | 1 |  |  | 첨부파일 허용 여부 |
| `allow_anonymous` | tinyint(1) | N | 0 |  |  | 비회원 글쓰기 허용 여부 |
| `allow_image_upload` | tinyint(1) | N | 1 |  |  | 본문 이미지 업로드 허용 여부 |
| `use_captcha` | tinyint(1) | N | 0 |  |  | 캡차 사용 여부 |
| `requires_identity_verification` | tinyint(1) | N | 0 |  |  | 본인인증 필요 여부 |
| `identity_verification_consent_text` | text | Y |  |  |  | 본인인증 동의 안내 문구 |
| `min_read_level` | tinyint | N | 1 |  |  | 읽기 최소 회원등급 |
| `min_write_level` | tinyint | N | 2 |  |  | 쓰기 최소 회원등급 |
| `min_comment_level` | tinyint | N | 1 |  |  | 댓글 최소 회원등급 |
| `files_per_post` | tinyint | N | 5 |  |  | 글당 첨부파일 최대 개수 |
| `per_page` | tinyint | N | 15 |  |  | 페이지당 게시글 수 |
| `order_by` | varchar(255) | N | latest |  |  | 정렬 기준(latest/views 등) |
| `order_direction` | varchar(255) | N | desc |  |  | 정렬 방향: asc=오름차순, desc=내림차순 |
| `description` | text | Y |  |  |  | 게시판 설명 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `exclude_from_search` | tinyint(1) | N | 0 |  |  | 통합검색 제외 여부 |
| `custom_field_schema` | json | Y |  |  |  | 게시판별 커스텀 필드 정의(JSON) |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `board_categories` — 게시판별 카테고리

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `board_id` | bigint unsigned | N |  |  | `boards` | 소속 게시판 |
| `name` | varchar(255) | N |  |  |  | 카테고리명 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `posts` — 게시글

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `board_id` | bigint unsigned | N |  |  | `boards` | 소속 게시판 |
| `user_id` | bigint unsigned | Y |  |  | `users` | 작성 회원(비회원 작성 시 null) |
| `category_id` | bigint unsigned | Y |  |  | `board_categories` | 게시판 카테고리 |
| `title` | varchar(255) | N |  |  |  | 제목 |
| `content` | longtext | N |  |  |  | 본문 |
| `author_name` | varchar(50) | Y |  |  |  | 비회원 작성자명 |
| `author_password` | varchar(255) | Y |  |  |  | 비회원 작성 비밀번호(해시) |
| `ip` | varchar(45) | Y |  |  |  | 작성 IP |
| `views` | int | N | 0 |  |  | 조회수 |
| `is_global_notice` | tinyint(1) | N | 0 |  |  | 전체 게시판 공통 공지 여부 |
| `is_notice` | tinyint(1) | N | 0 |  |  | 게시판 내 공지 여부 |
| `is_secret` | tinyint(1) | N | 0 |  |  | 비밀글 여부 |
| `is_active` | tinyint(1) | N | 1 |  |  | 노출 여부(삭제 시 false) |
| `is_draft` | tinyint(1) | N | 0 |  |  | 임시저장 여부 |
| `recruitment_start_at` | timestamp | Y |  |  |  | 모집(접수) 시작일시 |
| `recruitment_end_at` | timestamp | Y |  |  |  | 모집(접수) 마감일시 |
| `custom_fields` | json | Y |  |  |  | 게시판별 커스텀 필드 값(JSON) |
| `created_at` | timestamp | Y |  |  |  | 작성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제일시 |

### `post_files` — 게시글 첨부파일

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `post_id` | bigint unsigned | N |  |  | `posts` | 소속 게시글 |
| `original_name` | varchar(255) | N |  |  |  | 원본 파일명 |
| `stored_name` | varchar(255) | N |  |  |  | 저장된 파일명 |
| `file_path` | varchar(255) | N |  |  |  | 저장 경로 |
| `file_size` | int unsigned | N |  |  |  | 파일 크기(byte) |
| `mime_type` | varchar(255) | N |  |  |  | MIME 타입 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `comments` — 게시글 댓글

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `post_id` | bigint unsigned | N |  |  | `posts` | 소속 게시글 |
| `user_id` | bigint unsigned | Y |  |  | `users` | 작성 회원(비회원 작성 시 null) |
| `parent_id` | bigint unsigned | Y |  |  | `comments` | 부모 댓글(대댓글인 경우) |
| `depth` | tinyint | N | 0 |  |  | 댓글 깊이(0=댓글, 1=대댓글) |
| `content` | text | N |  |  |  | 댓글 내용 |
| `author_name` | varchar(50) | Y |  |  |  | 비회원 작성자명 |
| `author_password` | varchar(255) | Y |  |  |  | 비회원 작성 비밀번호(해시) |
| `ip` | varchar(45) | Y |  |  |  | 작성 IP |
| `is_active` | tinyint(1) | N | 1 |  |  | 노출 여부(삭제 시 false) |
| `created_at` | timestamp | Y |  |  |  | 작성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제일시 |

### `policies` — 약관/정책(이용약관, 개인정보처리방침 등)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `type` | varchar(255) | N |  |  |  | 약관 종류(이용약관/개인정보처리방침 등) |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `title` | varchar(255) | N |  |  |  | 약관 제목 |
| `content` | longtext | N |  |  |  | 현재 게시 중인 본문(content_type=editor) |
| `content_type` | varchar(255) | N | editor |  |  | 콘텐츠 작성 방식(editor/html_file) |
| `html_file_path` | varchar(255) | Y |  |  |  | 현재 게시 중인 HTML 파일 경로(content_type=html_file) |
| `is_required` | tinyint(1) | N | 1 |  |  | 가입/이용 시 필수 동의 여부 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `version` | varchar(255) | Y |  |  |  | 현재 게시 중인 버전 |
| `pending_version` | varchar(255) | Y |  |  |  | 예약된 다음 버전(사전고지 중인 개정판) |
| `pending_title` | varchar(255) | Y |  |  |  | 예약된 다음 버전 제목 |
| `pending_content` | longtext | Y |  |  |  | 예약된 다음 버전 본문 |
| `pending_content_type` | varchar(255) | N | editor |  |  | 예약된 다음 버전 콘텐츠 작성 방식 |
| `pending_html_file_path` | varchar(255) | Y |  |  |  | 예약된 다음 버전 HTML 파일 경로 |
| `effective_at` | timestamp | Y |  |  |  | 예약된 다음 버전 시행일시(도래 시 자동 적용) |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `popups` — 레이어 팝업

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `title` | varchar(255) | N |  |  |  | 팝업 제목(관리용) |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `content_type` | varchar(255) | N | image |  |  | 콘텐츠 유형(image/html) |
| `image_path` | varchar(255) | Y |  |  |  | 팝업 이미지 경로 |
| `html_content` | longtext | Y |  |  |  | HTML 직접 입력 콘텐츠 |
| `position` | varchar(255) | N | center |  |  | 노출 위치(center 등) |
| `width` | int | N | 400 |  |  | 팝업 너비(px) |
| `height` | int | N | 300 |  |  | 팝업 높이(px) |
| `started_at` | datetime | Y |  |  |  | 노출 시작일시 |
| `ended_at` | datetime | Y |  |  |  | 노출 종료일시 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `banners` — 배너(메인/푸터 등 위치별)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `group_key` | varchar(255) | N |  |  |  | 배너 노출 위치 그룹(메인 상단/푸터 등) |
| `content_type` | varchar(255) | N | image |  |  | 콘텐츠 유형(image/html) |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `title` | varchar(255) | N |  |  |  | 배너 제목(관리용/대체텍스트 참고용) |
| `image_path` | varchar(255) | Y |  |  |  | 배너 이미지 경로 |
| `html_content` | longtext | Y |  |  |  | HTML 직접 입력 콘텐츠(content_type=html일 때) |
| `link_url` | varchar(255) | Y |  |  |  | 클릭 시 이동할 링크 |
| `link_target` | varchar(255) | N | _blank |  |  | 링크 열림 방식(_blank/_self) |
| `alt_text` | varchar(255) | Y |  |  |  | 이미지 대체텍스트 |
| `captions` | json | Y |  |  |  | 슬라이드 캡션 등 부가 정보(JSON) |
| `started_at` | datetime | Y |  |  |  | 노출 시작일시 |
| `ended_at` | datetime | Y |  |  |  | 노출 종료일시 |
| `click_count` | int | N | 0 |  |  | 클릭 수 |
| `is_active` | tinyint(1) | N | 1 |  |  | 노출 활성화 여부 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `banned_words` — 금칙어

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `word` | varchar(255) | N |  |  |  | 금칙어 |
| `type` | varchar(255) | N | all |  |  | 적용 범위(all 등) |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `inquiries` — 1:1 문의

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | Y |  |  | `users` | 작성 회원(비회원 작성 시 null) |
| `type` | varchar(255) | N | general |  |  | 문의 유형 |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `category` | varchar(255) | Y |  |  |  | 세부 카테고리 |
| `name` | varchar(50) | N |  |  |  | 작성자명 |
| `email` | varchar(255) | Y |  |  |  | 연락 이메일 |
| `phone` | varchar(20) | Y |  |  |  | 연락처 |
| `author_password` | varchar(255) | Y |  |  |  | 비회원 작성 비밀번호(해시) |
| `title` | varchar(255) | N |  |  |  | 문의 제목 |
| `content` | longtext | N |  |  |  | 문의 내용 |
| `file_path` | varchar(255) | Y |  |  |  | 첨부파일 경로 |
| `status` | varchar(255) | N | pending |  |  | 처리 상태(pending 등) |
| `admin_reply` | longtext | Y |  |  |  | 관리자 답변 내용 |
| `replied_at` | timestamp | Y |  |  |  | 답변일시 |
| `is_active` | tinyint(1) | N | 1 |  |  | 노출 여부 |
| `created_at` | timestamp | Y |  |  |  | 작성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제일시 |

### `media_files` — 미디어 라이브러리(이미지 보관/재사용)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 업로드한 관리자/회원 |
| `original_name` | varchar(255) | N |  |  |  | 원본 파일명 |
| `stored_name` | varchar(255) | N |  |  |  | 저장된 파일명 |
| `file_path` | varchar(255) | N |  |  |  | 저장 경로 |
| `file_size` | int unsigned | N |  |  |  | 파일 크기(byte) |
| `mime_type` | varchar(255) | N |  |  |  | MIME 타입 |
| `alt_text` | varchar(255) | Y |  |  |  | 대체텍스트 |
| `download_count` | int unsigned | N | 0 |  |  | 다운로드/사용 횟수 |
| `created_at` | timestamp | Y |  |  |  | 업로드일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `maintenance_reports` — 유지보수 업체 작업 보고서

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 작성 관리자 |
| `title` | varchar(255) | N |  |  |  | 보고서 제목 |
| `content` | longtext | N |  |  |  | 보고서 내용 |
| `report_type` | varchar(255) | N | notice |  |  | 보고서 유형(notice 등) |
| `is_sent` | tinyint(1) | N | 0 |  |  | 발송(공지) 완료 여부 |
| `sent_at` | datetime | Y |  |  |  | 발송일시 |
| `send_response` | text | Y |  |  |  | 발송 결과 응답 로그 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `marketing_mail_logs` — 마케팅 메일 발송 로그

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `subject` | varchar(255) | N |  |  |  | 발송 메일 제목 |
| `content` | longtext | N |  |  |  | 발송 메일 본문 |
| `sent_count` | int unsigned | N | 0 |  |  | 발송 성공 건수 |
| `failed_count` | int unsigned | N | 0 |  |  | 발송 실패 건수 |
| `sent_by` | bigint unsigned | N |  |  | `users` | 발송한 관리자 ID |
| `sent_at` | datetime | N |  |  |  | 발송일시 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `vendor_notices` — 유지보수 업체 공지사항(외부 동기화)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `external_id` | varchar(255) | N |  |  |  | 유지보수 업체측 원본 공지 ID(중복 동기화 방지) |
| `title` | varchar(255) | N |  |  |  | 공지 제목 |
| `url` | varchar(255) | Y |  |  |  | 원본 공지 링크 |
| `published_at` | timestamp | Y |  |  |  | 공지 게시일시 |
| `created_at` | timestamp | Y |  |  |  | 동기화 수집일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `visit_logs` — 방문 로그(일별/IP/경로)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `date` | date | N |  |  |  | 방문 일자(일별 집계 기준) |
| `ip` | varchar(45) | N |  |  |  | 방문자 IP |
| `user_id` | bigint unsigned | Y |  |  | `users` | 로그인 회원(비로그인 시 null) |
| `path` | varchar(255) | N |  |  |  | 방문 경로(URL path) |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |

### `ai_chat_conversations` — AI 챗봇 대화방(회원별)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `user_id` | bigint unsigned | N |  |  | `users` | 대화 소유 회원 |
| `provider` | varchar(20) | N |  |  |  | AI 제공자(openai/gemini) |
| `title` | varchar(100) | Y |  |  |  | 대화방 제목 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제일시 |

### `ai_chat_messages` — AI 챗봇 대화 메시지

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `conversation_id` | bigint unsigned | N |  |  | `ai_chat_conversations` | 소속 대화방 |
| `role` | varchar(10) | N |  |  |  | 발화자(user/assistant) |
| `content` | text | Y |  |  |  | 메시지 본문 |
| `image_path` | varchar(255) | Y |  |  |  | 첨부 이미지 경로(있는 경우) |
| `created_at` | timestamp | N | CURRENT_TIMESTAMP |  |  | 전송일시 |

### `pages` — 정적 페이지(회사소개 등)

| 컬럼명 | 타입 | NULL | 기본값 | PK | FK | 설명 |
|---|---|---|---|---|---|---|
| `id` | bigint unsigned | N |  | PK |  | 기본 키 |
| `title` | varchar(255) | N |  |  |  | 페이지 제목 |
| `slug` | varchar(255) | N |  |  |  | URL 식별자 |
| `locale` | varchar(5) | N | ko |  |  | 언어(로케일) |
| `content_type` | varchar(255) | N | editor |  |  | 콘텐츠 작성 방식(editor/html_file) |
| `content` | longtext | Y |  |  |  | 에디터로 작성한 본문(content_type=editor) |
| `html_file_path` | varchar(255) | Y |  |  |  | 업로드한 HTML 파일 경로(content_type=html_file) |
| `meta_title` | varchar(255) | Y |  |  |  | SEO 메타 타이틀 |
| `meta_description` | varchar(255) | Y |  |  |  | SEO 메타 설명 |
| `meta_keywords` | varchar(255) | Y |  |  |  | SEO 메타 키워드 |
| `og_image` | varchar(255) | Y |  |  |  | SNS 공유용 OG 이미지 |
| `min_level` | tinyint | N | 1 |  |  | 열람 최소 회원등급 |
| `is_active` | tinyint(1) | N | 1 |  |  | 활성화 여부 |
| `sort_order` | int | N | 0 |  |  | 정렬 순서 |
| `created_at` | timestamp | Y |  |  |  | 생성일시 |
| `updated_at` | timestamp | Y |  |  |  | 수정일시 |
| `deleted_at` | timestamp | Y |  |  |  | 소프트 삭제일시 |
