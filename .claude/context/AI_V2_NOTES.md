# AI 비서 2.0 — 진행 기록 · 접점 전수표 · 설계 결정 (cms-admin)

> 기준 문서: `AI_ASSISTANT_V2_SCOPE.md`(이 작업의 단일 기준). 근거·Critic 반박은 java-cms 판 `AI_ASSISTANT_V2_SCOPE.md`(읽기 전용).
> 브랜치 `feat/ai-assistant-v2`(dev `50e171f0` 에서 분기). 푸시 금지. 사용자 WIP `public/css/slider.css`·`public/js/slider-init.js` 는 건드리지 않는다(커밋은 항상 경로 지정).
> **컨텍스트가 요약돼도 이 파일 맨 아래 "진행 로그"를 보면 어디까지 했는지 알 수 있다.** 최종 보고 전문은 `AI_V2_REPORT.md`.

## 1. AI 접점 전수표 (`grep -rIn -i "ai_chat\|AiChat\|AiProvider\|HasAiFormFill\|fill-form-field\|ai_openai\|ai_gemini\|gemini\|openai\|AI 비서\|ai-chat" app resources config database routes tests lang docs README.md scripts`)

| # | 접점 | 처리 | 이유/내용 |
|---|---|---|---|
| 1 | `app/Livewire/AiChatWidget.php` | **갱신** | 한도·잔여량·행동 카드·현재 화면(잠금 속성 `screenPath`)·`providers` 잠금·채우기 서버 검증·세션 유지·IME 안전한 Enter |
| 2 | `resources/views/livewire/ai-chat-widget.blade.php` | **갱신(재작성)** | 말풍선·전송 중 표시·안전한 마크다운·추천 칩·카드·접근성(role=log·dialog·Esc·포커스), `<style>` 분리 |
| 3 | `public/css/ai-chat.css`·`public/js/ai-chat.js`(신규) | **신규** | 토큰 색(`:root`+`.dark`) 위젯 CSS, "AI 가 채운 칸" 표시 스크립트. `AdminPanelProvider` HEAD_END 로 로드 |
| 4 | `app/Services/AiChatService.php` | **갱신** | 한도 선기록, 시스템 지시문, 도구 호출 1회, 카드 저장, 이력 정규화, 카드 이미지 생성, 차단 안내·한도 미소모, 정리 |
| 5 | `app/Services/Ai/AiProviderContract.php` | **갱신** | `chatWithTools()`·`supportsTools()` 추가, `chat()` 은 `DelegatesPlainChat` 로 위임(기존 시험 보존) |
| 6 | `GeminiProvider`·`OpenAiProvider` | **갱신** | systemInstruction/tools 전송, 모든 파트에서 텍스트+호출 수집, 빈 후보·차단·잘못된 인자는 재시도 없이 오류, 정책 차단은 `AiBlockedException` |
| 7 | `FakeChatProvider`(신규) | **신규** | local 환경 전용 개발용 가짜(도구 호출·마크다운·오류·차단 흉내, 단색 PNG) |
| 8 | 모델 `AiChatConversation`·`AiChatMessage` + `AiChatProposal`·`AiChatUsage`(신규) | **갱신/신규** | `proposals` 관계, 메시지 `created_at` 을 앱 시간대로 기록 |
| 9 | 기존 마이그레이션 2025_01_16_*(2개)·2026_07_28_000001 | 그대로 | 수정 금지 규칙. 새 마이그레이션 2개로 표 추가(`->comment()` + 표 코멘트) |
| 10 | `database/migrations/2026_10_21_00000{1,2}_*` | **신규** | `ai_chat_usage`·`ai_chat_proposals` |
| 11 | `PruneAiChatHistory` 미들웨어 | 그대로 | `AiChatService::pruneExpired()` 가 사용 기록(35일)·카드·고아 카드 이미지까지 정리 |
| 12 | `AdminAuditLogService` | **갱신** | `recordAiUsage(..., $blockedReason)`·`recordAiProposalUsed()` |
| 13 | `AdminAuditLogResource` | 그대로 | `ai_chat` 라벨 이미 있음(카드 저장·차단도 같은 action) |
| 14 | `AiChatLogResource`·`ListAiChatLogs`·`ai-chat-logs/messages.blade.php`·`AiUsageTodayWidget`(신규) | **갱신/신규** | 오늘 관리자별 사용량 위젯, 읽기 전용 카드, 안전한 마크다운 |
| 15 | `SiteSettings`(AI 탭·저장 그룹 매핑) | **갱신** | 한도 4개·도구 호출 토글·개인정보 안내·오늘 사용량, 미저장 시 실제 기본값 표시 |
| 16 | `SiteSettingSeeder` | **갱신** | `ai` 그룹 기본값(API 키는 시딩 안 함) |
| 17 | `SiteSettingService` | 그대로 | 암호화 키 목록 변경 없음 |
| 18 | `HasAiFormFill` | **갱신** | 허용 필드 표(`AiFormFillSchema`) 서버 재검증, 이미지 경로 제한, 알림·`ai-field-filled` 이벤트, 저장 호출 없음을 정적 시험으로 고정 |
| 19 | Create/Edit 페이지 11개(Banners·Boards·Pages·Popups·Posts 의 Create/Edit, Inquiries Edit) | **갱신(Create 4개)** | 지시문의 "12개"는 실제 11개. Edit 7개는 `HasAiFormFill` 그대로, 팝업·게시글·페이지·배너 Create 4개에 `HasAiProposalPrefill` 추가 |
| 20 | `UserResource`(권한 체크박스 `ai_assistant`) | 그대로 | 권한 키 변경 없음(위젯 모든 액션이 이 권한을 다시 확인) |
| 21 | `AdminPanelProvider` | **갱신** | `ai-chat.css`·`ai-chat.js` 로드 |
| 22 | `lang/{ko,en,ja}.json` | 그대로(이유) | 관리자 화면 문구는 코드에 한국어 직접 기재가 이 프로젝트 관례(`lang` 에 AI 키 0건). 서버 안내문은 기존처럼 `__()` 로만 감쌈 |
| 23 | `docs/table-spec.md`·`docs/erd.html` | **갱신** | 새 표 2개(28→30개) |
| 24 | `README.md`·`사용가이드.md`·`소개서.md` | **갱신** | AI 비서 설명을 새 동작에 맞게 |
| 25 | 시험(기존 AI 8개 + 신규 14개) | **갱신/신규** | `AiFormFillSafetyTest` 의 이미지 칸 단정만 [경로] 배열로 갱신, 나머지 기존 단정 그대로 통과 |
| 26 | `scripts/` | 그대로 | AI 호출 스크립트 없음(실호출 금지) |

## 2. 설계 결정

- **D1 현재 화면 판정**: 기존 `request()->is(...)` 는 Livewire 갱신 요청(`/livewire/update`)에서 항상 false. 위젯 패널은 토글(=갱신 요청) 뒤에 처음 그려지므로 "채우기" 버튼이 **한 번도** 안 그려지는 구조였다. → 마운트 때 `request()->path()` 를 `#[Locked] screenPath` 에 담는다(변조 불가, 시험에서 주입 가능).
- **D2 한도**: `ai_chat_usage` 선기록→집계→초과 시 삭제. 관리자별+사이트 전체, 질문/이미지 분리. 0=그 종류 차단(무제한 없음). 오류로 끝난 호출은 센다(제공자 과금 가능), **정책상 차단은 되돌린다**. 관리자별 동시 1건 `Cache::lock`.
- **D3 제안 저장/열기**: `ai_chat_proposals`. Create 페이지가 `?ai_proposal=` 를 `fillForm()` 에서 소유자·종류·만료 확인 후 폼에 채우기만(비공개·임시저장). 저장 직후 `used`+감사 로그 연결.
- **D4 도구**: `open_admin_page`·`propose_popup/post/page/banner`, 응답당 3장·중복 제거. `propose_image` 는 만들지 않음 - 기존 "이미지 생성" 모드+"이 이미지 사용하기"와 카드의 "이미지 만들고 열기"가 담당.
- **D5 마크다운**: 서버(`league/commonmark`, Laravel 포함)에서 잠가서 렌더 - 원문 HTML 이스케이프, http/https 링크만, **이미지 렌더 안 함**, 제목은 굵은 문단, soft break=`<br>`.
- **D6 본문 안전**(SCOPE 와 다른 점): "평문→`<p>`" 대신 `SafeMarkdown::editorHtml`(원문 HTML 이스케이프·링크/이미지는 글자만) - 모델이 `**굵게**`·목록을 써도 폼에 기호가 그대로 남지 않으면서 추적 이미지·피싱 링크 방어는 동일. 텍스트 게시판용은 `plainText`.
- **D7 폴백**: `supportsTools()` + 사이트 설정 `ai_tools_enabled`(기본 켬).
- **D8 가짜 제공자**: local 에서만 목록에 나옴, 호출도 한도에 센다. 키워드: 팝업/게시글·글 써/페이지/배너/열어·이동·어디/마크다운/오류/**차단**.
- **D9 화면 지도**: `AdminScreenMap`(키·이름·설명·클래스, `canAccess()`/`canCreate()` 로 거름). "도구 키는 설명 보유" 시험으로 고정. 다른 관리자 기준 판정은 `actingAs` 로 잠깐 바꿨다 `finally` 에서 복원.
- **D10 URL**: 모델에는 키만, URL 은 서버가 `Resource::getUrl(panel:'admin')`.
- **D11 세션 유지**: `#[Session]` 으로 `isOpen`·`activeConversationId` 를 유지(전체 새로고침으로 카드의 작성 화면을 열어도 위젯과 대화가 남는다). 대화 소유권은 매 렌더에서 다시 확인.
- **D12 `providers` 잠금**: 공개 속성이라 브라우저에서 바꿀 수 있었다(권한 없는 관리자가 제공자 목록을 채워 넣을 수 있음) → `#[Locked]`, 모든 액션이 `ai_assistant` 권한을 다시 확인.
- **D13 이미지 한 장 정책 차단**: Gemini `IMAGE_RECITATION` 등은 `AiBlockedException`(사유 코드만 노출, finishMessage·키·원문 비노출), 한도 미소모, 카드는 그대로 둠(이미지 없이 열 수 있음).

## 3. java-cms Critic 12건 → cms-admin 반영 위치

(java-cms SCOPE §2 의 번호 순)

| # | 반박/요구 | cms-admin 반영 | 위치 |
|---|---|---|---|
| 1 | 카드·실행 = 기존 폼 미리 채우기(권한 우회 방지) | 쓰기 엔드포인트 없음, 카드 버튼=GET 링크(`?ai_proposal=`), Create 페이지가 소유자·종류·만료 확인 후 채우기만, 비공개 상태, 카드·지시문은 `canAccess`/`canCreate` 로 거름 | `HasAiProposalPrefill`, `AiProposalService`, `AdminScreenMap`, `AiProposalPrefillTest` |
| 2 | 이미지까지 한 번에 | 카드 "이미지 만들고 작성 화면 열기"(소유자·만료·상태 확인, 이미지 한도, 원자적 `proposed→image_ready`, 검증 먼저) + "이미지 없이 열기", 프롬프트 개행·제어문자 공백·500자 | `AiChatService::generateProposalImage`, `AiChatWidget::openProposalWithImage`, `proposal-card` |
| 3 | 한 번만 호출·폴백 | `tools`+`systemInstruction` 1회, 모든 파트에서 텍스트+호출 수집, 텍스트 없으면 서버가 합성, 이력엔 카드 평문 한 줄·빈 content 금지·역할 병합·최근 30개, 카드 3장·중복 제거, 오류/차단/잘못된 인자 재시도 없음, `supportsTools()`+`ai_tools_enabled` 폴백 | `GeminiProvider`/`OpenAiProvider`/`AiChatService::historyFor`/`AiProposalService::normalize` |
| 4 | 실측 시험 준비(smoke 스크립트, 작성만) | **해당 없음** - cms-admin 에는 smoke 스크립트가 없고 실호출 확인은 메인이 최소로(HANDOFF "아직 안 한 것"). 도구 호출 실측은 미실시 → `ai_tools_enabled` 로 끌 수 있게 함 | `SiteSettings` AI 탭 |
| 5 | 채팅 한도 재설계 | `ai_chat_usage`(대화 삭제와 무관)·선기록→집계→되돌림·관리자별 동시 1건·관리자별+사이트 전체 | `AiUsageService`, `AiUsageLimitTest` |
| 6 | 제안 저장 표 | `ai_chat_proposals`(message FK cascade·kind·payload·status·만료 60분·image_path), 소유자(메시지→대화→user)·만료·원자적 전이, 감독 화면 읽기 전용 | 마이그레이션 2026_10_21_000002, `AiChatProposal`, `proposal-card` |
| 7 | 입력 안전 | 제목 한 줄·길이, 날짜 서버 계산(오프셋 우선·엄격 YYYY-MM-DD), 링크 http/https, 게시판 서버 확인, 본문은 안전한 HTML 로만(D6), 채팅 마크다운 이미지 없음·http/https 링크만 | `AiText`, `SafeMarkdown`, `AiProposalService` |
| 8 | 시스템 지시문(맞춤) | 현재 시각·시간대, 화면 지도(접근 가능한 것만, URL 없음), 규칙, 현재 화면, 쓸 수 있는 게시판 이름; 가짜 제공자 | `AiSystemPromptBuilder`, `AdminScreenMap`, `FakeChatProvider` |
| 9 | 전송 방식(CSRF 본문 파라미터) | **해당 없음** - Livewire 가 CSRF·JSON 응답을 처리. 대신 Enter(IME 안전)/pending 말풍선을 Alpine 으로 | 위젯 blade |
| 10 | UI | 말풍선·전송 중 표시·`role=log`·추천 칩·카드·대화 목록 접기·포커스·`prefers-reduced-motion`·라이트/다크 토큰 | 위젯 blade, `ai-chat.css`, `AiWidgetCssTest` |
| 11 | 사이드바에 "AI 비서" 항목 | **해당 없음(이유)** - cms-admin 은 Filament 패널에 전역 플로팅 위젯이 있고 "운영 관리 > AI 비서 사용 내역" 항목이 이미 있다. 사이드바에 중복 항목을 더하지 않음 | - |
| 12 | 감사·개인정보 | 카드 저장 시 감사 로그에 제안 id·종류·레코드 연결(제목은 안 씀), 차단 사유 코드, 사이트 설정 AI 탭·사용가이드에 외부 전송 안내 | `AdminAuditLogService`, `SiteSettings` |

## 4. 갱신할 문서 문장(메인이 합칠 것 - HANDOFF/PROJECT_STATUS/PROJECT.md 는 건드리지 않음)

- HANDOFF "아직 안 한 것": "cms-admin 에도 AI 비서 2.0 방향을 넣을지" → **구현 완료(브랜치 `feat/ai-assistant-v2`, 푸시 전)**. OpenAI 모델(`gpt-4o-mini`·`gpt-image-1`) 노후 여부는 여전히 미확인. Gemini 도구 호출(functionDeclarations)은 실측 전 - 켜져 있고 문제 시 사이트 설정에서 끄면 일반 대화로 폴백.
- PROJECT.md DB 규칙 절: 새 표 2개(`ai_chat_usage`·`ai_chat_proposals`) 추가됨, `docs/table-spec.md`·`docs/erd.html` 은 갱신 완료(28→30개). `client/future-mobility` 쪽 문서는 미포팅(HANDOFF 확정 사항).
- PROJECT_STATUS 작업 로그 한 줄 후보: "2026-10-21 AI 비서 2.0 — 일일 한도·시스템 지시문·행동 카드(작성 화면 미리 채우기)·위젯 재작성(다크 모드 수정)·사이트 설정/감독 화면, 사용자 테스트 결함 2건 수정(다크 모드 입력칸, 글쓰기 화면 채우기=갱신 요청 경로 문제)".
- 메모 후보: Livewire 갱신 요청의 `request()->path()` 는 항상 `livewire/update`(화면 판별은 마운트 때 저장). Filament 4 리치 에디터 폼 상태는 HTML 문자열이 아니라 Tiptap 문서 배열. 같은 호스트의 다른 포트 앱끼리 세션 쿠키 이름이 같으면 서로 로그아웃시킴(테스트 스택은 `SESSION_COOKIE` 를 분리).

## 5. 진행 로그

- 2026-10-21 단계 0: SCOPE·java-cms SCOPE·PROJECT.md·CLAUDE.md·HANDOFF·PROJECT_STATUS·AI 코드 전체 읽음. 브랜치 `feat/ai-assistant-v2` 생성. 접점 전수표·설계 결정 작성(`a1b4d13b`).
- 단계 1(서버 기반, `bbd8c5e7`): 제공자 중립 인터페이스·도구 호출 파싱·`FakeChatProvider`·모델·마이그레이션 2개·`AiText`.
- **작업 폴더 격리(메인 긴급 지시)**: 작업 트리 `cms-admin` 이 사용자 앱(8090)과 같아 미완성 코드가 `/admin` 500 을 냈다 → WIP 커밋 후 원래 트리를 `dev` 로 되돌리고(사용자 슬라이더 WIP·HANDOFF 수정은 그대로), **별도 worktree `C:\Users\USER\PhpstormProjects\cms-admin-ai`** 에서만 작업. 별도 Docker 스택 `docker compose -p cmsadminai`(앱 8095·Vite 5183·MySQL 33081·Mailpit 2535/8035, 볼륨 `cmsadminai_sail-mysql`·`cmsadminai_vendor`, `docker-compose.override.yml`(커밋 안 함)로 vendor 를 볼륨에 둬서 응답 2~3초, `.env` 에 `SESSION_COOKIE=cmsadminai_session` 으로 8090 세션과 분리). 시험은 기존 방식(볼륨 `cmsadmin_v2` 복사). worktree·스택·볼륨 정리는 메인이 한다.
- 단계 2(서버·위젯, `e66cdd6e`): 한도·시스템 지시문·행동 카드·위젯 재작성·미리 채우기.
- 단계 3(화면·결함 수정·시험, `e1defc29`): 사이트 설정 AI 탭·감독 화면·시험 정리, 결함 2건.
- 단계 4(차단 안내, `0bc5204d`): 메인 지시 - Gemini `IMAGE_RECITATION` 등을 이유별 안내·한도 미소모로(+ 메시지 시각 UTC 어긋남 수정).
- 단계 5(문서, `44cec3f6`) + 시험/리뷰 반영 커밋(보고 참고).
- 브라우저 확인(8095, 가짜 제공자, 비용 0): 채팅·카드 생성·게시글 채우기(리치 에디터)·작성 화면 미리 채우기(제목·본문·임시저장)·팝업 이미지 카드 → 이미지 생성 후 업로드 칸에 파일 표시·"이 이미지 사용하기"·한도 0 일 때 입력 차단·Esc 닫기 후 포커스 복귀·마크다운 안전 렌더·차단 흉내(한도 미소모)·사이트 설정 AI 탭·감독 화면(읽기 전용 카드).

## 6. 사용자 테스트로 확인된 결함 2건(2026-10-21, 로컬 위젯)

### 결함 1 — 다크 모드에서 위젯 select·textarea 글자가 안 보임
- **원인(확정, 코드+브라우저 수치)**: 위젯 CSS 가 blade 안 `<style>` 에 라이트 색(`#fff`·`#f3f4f6`…)만 하드코딩돼 있었고 `.ai-chat-widget-form select`/`textarea` 는 배경·글자색을 지정하지 않아 Filament 다크 테마(`html.dark`)가 입히는 밝은 글자색을 물려받음 → 흰 배경 위 흰 글씨.
- **수정**: `public/css/ai-chat.css` 로 분리. 색은 `:root`(라이트)/`.dark`(다크) 토큰으로만(`--aic-*`, 포인트는 Filament `--primary-*`+대체값), 입력 요소(select·option·textarea·placeholder·버튼·라디오)의 배경·글자색·테두리·`-webkit-text-fill-color`·`color-scheme` 명시, 인라인 style·`<style>` 없음.
- **회귀 시험**: `AiWidgetCssTest`(토큰 두 벌·대비 계산·토큰 밖 색 리터럴 금지·입력 요소 명시·blade 에 `<style>`/인라인 style 없음). 브라우저(8095, 실제 computed color)에서 두 모드 수치 측정 → `AI_V2_REPORT.md`.

### 결함 2 — 글쓰기 화면에서 "필드에 채우기"가 동작하지 않음
- **재현(확정)**: 새 시험 `AiChatWidgetUpdateRequestTest`(글쓰기 화면 GET → 위젯 `toggle`+`selectConversation` Livewire 갱신 요청 → 응답 HTML 에 "제목에 채우기")를 **이전 dev 코드(볼륨 `cmsadmin_old`)에서 실행하면 실패**(답변은 보이는데 채우기 버튼 없음), 새 코드에서는 통과.
- **원인(메인의 가설 그대로 확정)**: `targetTextFields()`/`targetImageField()` 가 `request()->is("{$prefix}/posts/create")` 로 화면을 판별 → 위젯 패널을 여는 것(토글)부터 모든 응답이 Livewire 갱신 요청(`livewire/update`)이라 필드 목록이 항상 `[]` → 버튼이 아예 안 그려짐.
- **수정**: 마운트 때 경로를 `#[Locked] screenPath` 로 저장(+ `AiFormFillSchema::detect` 로 허용 표 판별). 서버도 `fillTextField`·`HasAiFormFill` 에서 같은 표로 재검증.
- **추가 확인(메인 질문 ①~③)**: ① `data_set` 은 리치 에디터에 반영·표시됨(브라우저 확인; 단 폼 상태는 Tiptap 문서 배열이고 HTML 문자열을 넣어도 저장 시 HTML 로 돌아옴 - `test_widget_event_fills_the_rich_editor_and_the_human_save_persists_it`). FileUpload 는 문자열을 넣으면 미리보기가 글자 배열로 읽힐 수 있어 `[경로]` 배열로 넣도록 바꿈(브라우저에서 업로드 칸에 파일 표시 확인). ② 전체 새로고침 후 위젯 열림·대화 유지(`#[Session]`, 브라우저 확인, 시험 `test_open_state_and_active_conversation_survive_a_full_page_load_via_the_session`); Filament SPA 모드는 쓰지 않음. ③ 카드의 `?ai_proposal=` 미리 채우기 end-to-end 시험 `AiProposalPrefillTest`(게시글: 제목·본문·임시저장 채움 → 저장은 사람 → used+감사 로그) + 브라우저 확인.
