# AI 비서 2.0 — 진행 기록 · 접점 전수표 · 설계 결정 (cms-admin)

> 기준 문서: `AI_ASSISTANT_V2_SCOPE.md`(이 작업의 단일 기준). 근거·Critic 반박은 java-cms 판 `AI_ASSISTANT_V2_SCOPE.md`(읽기 전용).
> 브랜치 `feat/ai-assistant-v2`(dev `50e171f0` 에서 분기). 푸시 금지. 사용자 WIP `public/css/slider.css`·`public/js/slider-init.js` 는 건드리지 않는다(커밋은 항상 경로 지정).
> **컨텍스트가 요약돼도 이 파일 맨 아래 "진행 로그"를 보면 어디까지 했는지 알 수 있다.**

## 1. AI 접점 전수표 (`grep -rIn -i "ai_chat\|AiChat\|AiProvider\|HasAiFormFill\|fill-form-field\|ai_openai\|ai_gemini\|gemini\|openai\|AI 비서\|ai-chat" app resources config database routes tests lang docs README.md scripts`)

| # | 접점 | 처리 | 이유/내용 |
|---|---|---|---|
| 1 | `app/Livewire/AiChatWidget.php` | 갱신 | 한도·잔여량·행동 카드·현재 화면(잠금 속성)·채우기 정비·IME 안전한 Enter |
| 2 | `resources/views/livewire/ai-chat-widget.blade.php` | 갱신(재작성) | 말풍선·전송 중 표시·안전한 마크다운·칩·카드·접근성, `<style>` 분리 |
| 3 | `public/css/ai-chat.css`(신규) | 신규 | 위젯 스타일(토큰 색 + 라이트/다크), `AdminPanelProvider` HEAD_END 로 로드 |
| 4 | `app/Services/AiChatService.php` | 갱신 | 한도 선기록, 시스템 지시문, 도구 호출, 제안 저장, 이력 정규화 |
| 5 | `app/Services/Ai/AiProviderContract.php` | 갱신 | `chatWithTools()`·`supportsTools()` 추가, `chat()` 은 위임으로 보존 |
| 6 | `app/Services/Ai/GeminiProvider.php`·`OpenAiProvider.php` | 갱신 | systemInstruction/tools 전송, 모든 파트에서 텍스트+호출 수집 |
| 7 | `app/Services/Ai/FakeChatProvider.php`(신규) | 신규 | local 환경 전용 개발용 가짜(도구 호출 흉내·단색 PNG) |
| 8 | `app/Models/AiChatConversation.php`·`AiChatMessage.php` | 갱신 | `proposals` 관계, `AiChatProposal`·`AiChatUsage` 모델 신규 |
| 9 | `database/migrations/2025_01_16_*` (기존 2개) | 그대로 | 마이그레이션 수정 금지. 새 마이그레이션 2개로 표 추가 |
| 10 | `database/migrations/2026_07_28_000001_add_comments...` | 그대로 | 기존 표 설명 보존. 새 표는 새 마이그레이션에서 `->comment()` |
| 11 | `app/Http/Middleware/PruneAiChatHistory.php` | 그대로(서비스만 갱신) | `AiChatService::pruneExpired()` 가 사용 기록(`ai_chat_usage`)·제안도 함께 정리 |
| 12 | `app/Services/AdminAuditLogService.php` | 갱신 | `recordAiUsage` 유지 + 제안 사용 기록 메서드 추가 |
| 13 | `app/Filament/Resources/AdminAuditLogs/AdminAuditLogResource.php` | 그대로 | `ai_chat` 라벨 이미 있음(제안 사용도 같은 action 으로 기록) |
| 14 | `app/Filament/Resources/AiChatLogs/*`(Resource·ListAiChatLogs·`filament/resources/ai-chat-logs/messages.blade.php`) | 갱신 | 읽기 전용 카드, 오늘 사용량 요약, 마크다운 안전 렌더 |
| 15 | `app/Filament/Pages/SiteSettings.php`(AI 탭·저장 그룹 매핑) | 갱신 | 한도 설정 키, 도구 사용 토글, 개인정보 안내, 오늘 사용량 요약 |
| 16 | `database/seeders/SiteSettingSeeder.php` | 갱신 | `ai` 그룹 기본값(현재 AI 키가 시더에 없음 - 한도 기본값만 추가) |
| 17 | `app/Services/SiteSettingService.php` | 그대로 | 암호화 키 목록(ai_openai/gemini)은 변경 없음 |
| 18 | `app/Filament/Concerns/HasAiFormFill.php` | 갱신 | 허용 필드 검증, 채운 칸 안내, 저장 코드 없음을 정적 시험으로 고정 |
| 19 | Create/Edit 페이지 11개(Banners·Boards·Pages·Popups·Posts 의 Create/Edit, Inquiries Edit) | 갱신(Create 4개) | 지시문의 "12개"는 실제 11개(Inquiries 는 Edit 만). 제안 미리 채우기는 팝업·게시글·페이지·배너 Create 4개에 `HasAiProposalPrefill` 추가 |
| 20 | `app/Filament/Resources/Users/UserResource.php`(권한 체크박스 `ai_assistant`) | 그대로 | 권한 키 변경 없음 |
| 21 | `app/Providers/Filament/AdminPanelProvider.php` | 갱신 | 위젯에 현재 경로 전달, `ai-chat.css`·`ai-chat.js` 로드 |
| 22 | `lang/{ko,en,ja}.json` | 그대로(이유 기록) | 관리자 화면 문구는 코드에 한국어 직접 기재가 이 프로젝트 관례(`lang` 에 AI 키 0건). 서버 오류 문구는 기존처럼 `__()` 로만 감쌈 |
| 23 | `docs/table-spec.md`·`docs/erd.html` | 갱신 | 새 표 2개 반영(PROJECT.md DB 규칙) |
| 24 | `README.md`·`사용가이드.md`·`소개서.md` | 갱신(문서) | AI 비서 설명이 새 동작과 어긋나는 문장 정리(HANDOFF/PROJECT_STATUS/PROJECT.md 는 건드리지 않음) |
| 25 | 시험 8개(`AiChatServiceTest`·`AiChatWidgetTest`·`AiFormFillSafetyTest`·`PruneAiChatHistoryTest`·`AiChatLogResourceTest`·`AiChatLogResourceBulkActionsTest`·`OperationsGroupPermissionTest`·`PruneInquiriesTest`) | 갱신/보존 | 기존 단정은 그대로 통과해야 함. 새 시험 파일 추가 |
| 26 | `scripts/` | 그대로 | AI 호출 스크립트 없음(실호출 금지) |

## 2. 설계 결정 (SCOPE §2 를 코드에 맞게 확정한 것)

- **D1 현재 화면 판정**: 기존 `request()->is(...)` 는 Livewire 갱신 요청(`/livewire/update`)에서 항상 false 라 첫 응답 뒤 "채우기" 버튼이 사라지는 잠복 버그였다. → 위젯이 `mount()` 때 `request()->path()` 를 `#[Locked]` 속성에 담는다(클라이언트 변조 불가, 시험에서 주입 가능).
- **D2 한도**: `ai_chat_usage` 선기록→카운트→초과 시 삭제. 관리자별 + 사이트 전체, 질문/이미지 따로. 0 = 해당 종류 차단(무제한 없음). 실패한 호출도 1건으로 센다(비용 안전). 관리자별 동시 1건은 `Cache::lock`.
- **D3 제안 저장**: 새 표 `ai_chat_proposals`(메시지 FK cascade). Create 페이지는 `?ai_proposal=<id>` 로 열고 `fillForm()` 에서 소유자·만료·종류를 확인해 값만 채운다(비공개 상태). 저장은 사람이 하고, 저장 직후 `used` 로 바꾸고 감사 로그에 제안 id 기록.
- **D4 도구**: `open_admin_page`·`propose_popup`·`propose_post`·`propose_page`·`propose_banner`. 한 응답 최대 3장, 중복 제거. `propose_image` 는 만들지 않음 - 이미지는 기존 "이미지 생성" 모드 + "이 이미지 사용하기"가 이미 담당하고, 카드는 팝업·배너·페이지 안의 "이미지 만들고 열기"가 담당(중복 기능 방지).
- **D5 마크다운**: JS 가 아니라 서버에서 `league/commonmark`(Laravel 에 포함)를 잠가서 렌더 - HTML 입력 이스케이프, http/https 링크만(`rel=noopener noreferrer nofollow`, `target=_blank`), **이미지·제목 태그 렌더 안 함**. 폼 채우기용은 링크도 글자만 남김.
- **D6 본문 안전**: 도구 인자의 본문은 평문만 받아 이스케이프 후 `<p>` 로 감쌈. 모델 HTML 은 받지 않음.
- **D7 폴백**: `supportsTools()` + 사이트 설정 `ai_tools_enabled`(기본 켬). 꺼지면 도구·카드 없이 일반 대화.
- **D8 가짜 제공자**: `app()->environment('local')` 에서만 목록에 나옴. 호출도 한도에 센다(브라우저로 한도 흐름을 비용 없이 확인하려고).
- **D9 화면 지도**: `App\Services\Ai\AdminScreenMap` 한 곳에 화면 키·이름·설명·리소스 클래스. 현재 관리자의 `canAccess()` 로 거른 것만 지시문·도구 enum 에 노출. "도구에 노출되는 모든 키는 설명 보유" 시험으로 고정.
- **D10 URL**: 모델에는 화면 키만, URL 은 서버가 `Resource::getUrl(panel: 'admin')` 로 만든다(관리자 경로가 외부 제공자로 나가지 않게).

## 3. java-cms Critic 12건 → cms-admin 반영 위치

(구현하면서 채운다 - 최종 보고 §5 에 옮긴다)

## 4. 갱신할 문서 문장(메인이 합칠 것 - HANDOFF/PROJECT_STATUS/PROJECT.md 는 건드리지 않음)

(구현하면서 채운다)

## 5. 진행 로그

- 2026-10-21 단계 0: SCOPE·java-cms SCOPE·PROJECT.md·CLAUDE.md·HANDOFF·PROJECT_STATUS·AI 코드 전체 읽음. 브랜치 `feat/ai-assistant-v2` 생성. 접점 전수표·설계 결정 작성.
