# AI 비서 2.0 (cms-admin) — 최종 보고

> 브랜치 `feat/ai-assistant-v2`(dev `50e171f0` 에서 분기, **푸시·병합 안 함**), worktree `C:\Users\USER\PhpstormProjects\cms-admin-ai`.
> 기준 문서 `AI_ASSISTANT_V2_SCOPE.md`, 접점 전수표·설계 결정 `AI_V2_NOTES.md`, 리뷰 결과 `AI_V2_REVIEW_FINDINGS.md`.
> 측정하지 않은 것은 "미실행/미확인"으로 구분했다. 숫자는 이 세션에서 실제로 돌린 출력이다.

## 1. 한눈에

| 항목 | 상태 |
|---|---|
| 서버(한도·시스템 지시문·도구 호출·행동 카드·가짜 제공자) | 완료 |
| 위젯 재작성(다크 모드 수정·마크다운·카드·접근성) | 완료 |
| 작성 화면 미리 채우기(팝업·게시글·페이지·배너, 저장은 사람) | 완료 |
| 사이트 설정 AI 탭·감독 화면(오늘 사용량·읽기 전용 카드) | 완료 |
| 사용자 테스트 결함 2건(다크 모드 입력칸, 글쓰기 화면 채우기) | 수정 + 회귀 시험 |
| 회의적 리뷰 반영(M1·M2·M4, L1~L8) | **완료(이 보고의 §3)** |
| 문서(table-spec·erd·README·사용가이드·소개서) | 완료 |
| 실제 Gemini/OpenAI 호출 | **미실행(금지 사항 - 메인이 마지막에 최소로)** |

변경 규모: 73 파일(추가 46·수정 27), +8,325/-279 (`git diff --shortstat dev...HEAD`). 새 표 2개(`ai_chat_usage`·`ai_chat_proposals`)는 새 마이그레이션 2개로만 추가(기존 마이그레이션 무수정).

## 2. 커밋

`a1b4d13b` 문서(접점 전수표) → `bbd8c5e7` WIP 서버 기반 → `e66cdd6e` 서버+위젯 → `e1defc29` 화면+결함 2건 → `0bc5204d` 차단 안내 → `44cec3f6` 문서 → `e31948bc` 차단 한도 미소모 명시 → `e11d249c` WIP(한도 중단) → `21a07f31` 리뷰 결과 기록 → `94c1ddf7` **리뷰 반영(이 작업)**.

## 3. 리뷰 반영 결과 (코드로 먼저 확인 → 실제 문제일 때만 수정)

수정 전 `app/`·`config/`(직전 커밋 `21a07f31` 상태)에 새 시험 22건을 돌려 18건이 실패함을 확인했다(오류 4·실패 14). 통과한 4건은 대조군·판별력 없는 시험이다: markUsed 거부 규칙(대조군), L3 배너 `link_url`, L4 스모크, 정책 차단은 로그하지 않음(구코드는 로그 자체가 없음).

| # | 확인 결과 | 처리 | 회귀 시험 |
|---|---|---|---|
| **M1** | 실제 문제. `markUsed` 가 `resolveForPrefill→isOpenable()`(60분 만료 포함)을 다시 검사해 늦게 저장하면 `used` 가 안 되고, 보관 정리가 `status != used` 카드 이미지를 지워 팝업·배너·페이지 이미지가 404. 메시지 이미지("이 이미지 사용하기")도 사용 여부와 무관하게 삭제 | `markUsed` 는 소유자·종류·미사용 상태(proposed/image_ready)만 보고 만료는 무시(열기 제한이지 저장 제한이 아님). 보관 정리는 파일 삭제 전에 팝업·배너·페이지(`AiFormFillSchema` 의 image_field)가 그 경로를 쓰는지 DB 로 직접 확인해 쓰이면 지우지 않음(소프트삭제 포함, 상태와 무관) | 늦은 저장 → used + 감사 로그, 남의 제안/종류 불일치/이중 저장 거부, 정리 시 사용 중 이미지 보존·고아 삭제 |
| **M2** | 실제 문제. 위젯이 `RuntimeException` 전체를 잡아 `getMessage()` 를 채팅창에 표시(QueryException 도 RuntimeException → SQL 문구 노출). `InvalidArgumentException`(키 삭제·가짜 제공자 대화)은 미처리 500 | 사용자 노출용 `AiUserFacingException`(한도·진행 중·차단·제안 만료·제공자 사용 불가) 계열만 메시지를 그대로 보이고, 나머지는 `report()` 후 일반 문구. `ModelNotFoundException` 은 대화 해제 + "대화를 찾을 수 없습니다". `provider()`/`requireConfigured()` 는 InvalidArgumentException 대신 안내 예외 | SQL 문구 비노출+report, 안내 예외는 그대로+report 안 함, 카드 이미지 경로, 키가 지워진 제공자 → 안내(한도 미소모) |
| **M3** | **하지 않음(사용자 결정)** - "내부용이라 환불 상한은 불필요, 인당 하루 한도만" | 환불 횟수 상한 없음. 대신 환불 대상을 `IMAGE_*` 접두 전부·"STOP 아닌 모든 사유"에서 **명시 정책 코드 8개**(`GeminiProvider::POLICY_REASONS`: IMAGE_RECITATION·RECITATION·IMAGE_SAFETY·SAFETY·IMAGE_PROHIBITED_CONTENT·PROHIBITED_CONTENT·BLOCKLIST·SPII)로 좁힘. IMAGE_OTHER·OTHER·NO_IMAGE·MAX_TOKENS·프롬프트 차단 사유 OTHER 는 일반 실패(한도 소모). OpenAI 는 기존 명시 코드(`content_filter`·`content_policy_violation`·`moderation_blocked`)만. 결정은 `GeminiProvider`·`AiUsageService`·`AiBlockedException` 주석과 이 보고서에 기록 | 허용 목록 8개 고정, 일반 코드는 환불 안 함(IMAGE_OTHER 한도 1 소모 / IMAGE_SAFETY 환불), 정책 거절 반복해도 상한 없음 |
| **M4** | 실제 문제(코드가 migrate 보다 먼저 배포되면 열린 패널에서 전 화면 500). 닫힌 패널도 매 페이지 COUNT 쿼리 4개 | `AdminPanelProvider` BODY_END 훅 `try/catch` + `report()` + `''`. `render()` 는 패널이 열렸을 때만 대화·사용량 계산 | 위젯 장애(사용량 계산 예외)·`ai_chat_usage` 표 누락에서도 `/admin` 200 + report 1건, 닫힌 패널은 표 없어도 정상·무보고, 닫힌 패널 사용량 쿼리 0건/열면 있음 |
| **L1** | 실제 문제(잠금 전 조회 → 다른 탭이 끝낸 뒤 두 번째 생성·과금) | `reserve()`(관리자별 잠금) 뒤 제안을 다시 읽어 이미지가 있으면 제공자를 부르지 않고 선기록 사용 줄도 되돌림 | 다른 탭이 끝낸 경우 Http 0건·사용 0·파일 1개 |
| **L2** | 실제 문제(권한 박탈 관리자가 기존 카드로 이미지 한도 사용) | `resolveOwned`/`resolveForPrefill` 에 `ai_assistant` 권한 + 종류별 `AdminScreenMap::canCreate` 재검사 | 팝업 권한·AI 권한 박탈 각각 거절, Http 0건 |
| **L3** | 실제 문제. `https://evil.com\@bank.com/` 가 링크 title 호스트 위장(마크다운 `\@` 이스케이프로 `@` 가 남음) | `AiText::httpHost()` 신설: `\`·공백·제어문자·authority 의 `@`·빈 authority 거부(서버와 브라우저가 같게 읽는 주소만). `SafeMarkdown` 링크와 `AiText::httpUrl` 모두 사용. (배너 `link_url` 시험은 구코드도 통과 - 대조군) | 위장 5종 링크 안 됨, 정상·한글 경로 링크 유지, 에디터용은 항상 글자만 |
| **L4** | 영향 미미(실측). commonmark 2.8 에서 20,000자 기호 폭주도 0.1초 미만 | `max_delimiters_per_line = 200` 만 추가. `MAX_INPUT`·캐시는 그대로. `.live` 는 제공자 select·모드 라디오에만 있고 textarea 는 deferred 라 키 입력마다 재파싱하지 않음 | 시험은 연기(스모크) 수준 - 구코드도 통과(판별력 없음, 정직하게 표기) |
| **L5** | 실제 문제(`$` 가 끝 개행 허용) | `\z` | 끝 개행 경로 거부 |
| **L6** | 실제 문제 2가지 | ① 시작=오프셋 우선(없으면 start_date), 종료=기간 우선(없으면 end_date)으로 따로 정함 - 명시 end_date 가 오프셋에 묻히지 않음, 기간만 있으면 오늘부터 ② `intInRange` 는 float 의 범위를 먼저 확인하고 INF·NaN·2^64·소수는 null(예전엔 `(int)` 로 0=오늘) | 두 날짜 시나리오·INF/NaN/2^64/1e30/문자열 거대 수 |
| **L7** | 실제 문제 2가지 | `conversations()`·`messages()`·`selectConversation`·`deleteConversation` 에 `requireUser()`. 가짜 제공자는 `APP_ENV=local` **그리고** `.env` `AI_FAKE_PROVIDER=true`(config `services.ai.fake_provider`, 기본 false)일 때만 → 로컬 개발은 플래그 한 줄로 계속 사용(`.env.example` 에 문서화). `$notice` 잠금은 이스케이프되고 본인에게만 보여서 하지 않음 | 권한 박탈 후 4개 메서드 403, 플래그 기본 꺼짐·환경 불일치 시 꺼짐 |
| **L8** | 실제 문제(실패가 로그 없이 삼켜짐) | `AiProviderException`(상태 코드·사유 코드만, 원문·키 비포함)을 제공자가 던지고 서비스가 `Log::warning`(제공자·작업·예외 클래스·HTTP 상태·사유 코드·발생 위치)로 기록. 정책 차단은 로그하지 않음 | 404 NOT_FOUND → 로그에 상태/사유만, 키·응답 본문 없음, 정책 차단은 로그 없음 |
| **L9** | **보류** | 실측: `sendMessage` 1회 = 쿼리 13개·22ms(권한 확인 `canAccess` 는 쿼리 없는 메모리 연산). 정적 메모이즈는 권한 변경 직후 오판 위험이 더 큼 | - |
| **L10** | **보류** | 두 탭에서 같은 카드를 두 번 저장하는 경우 두 번째는 `used` 라 감사 링크만 누락 - 기능·보안 영향 없음 | - |

제공자 호출 실패 문구(`AI 응답을 받아오지 못했습니다`)·관리자 화면 동작은 그대로다.

## 4. 시험·Pint (실제 출력)

- 전체 스위트(차단 수정·리뷰 반영 이후 처음 재실행): **`OK (404 tests, 1968 assertions)`** (Docker `cmsadmin-test:intl-gd`, `php -d memory_limit=2G vendor/bin/phpunit`, 29초). 직전 기준 377건/1809 단언.
- 새 시험 파일 `AiReviewFixesTest`: 22건/115 단언 통과. 기존 시험은 허용 목록·플래그·안내 예외에 맞춰 갱신(`AiProviderBlockedTest`·`AiProviderToolsTest`·`AiChatToolFlowTest` 등) 후 전부 통과.
- Pint: 브랜치 변경 PHP 62개 파일 중 12개에 스타일 위반(이전 작업분 포함) → `pint` 적용(`62 files, 12 style issues fixed`) 후 `pint --test` 재확인 `PASS 62 files`. (전체 스위트는 Pint 적용 뒤에 돌렸다.)
- **미실행**: 접근성 수동 확인(스크린리더 낭독)·모바일 폭 확인, 운영 서버 마이그레이션 리허설, Gemini/OpenAI **실호출**(도구 호출 실측 포함).

## 5. 8095 스택(`cmsadminai`) 확인 — 비용 0(가짜 제공자)

브라우저(내장 pane)로 `http://localhost:8095` 에서 시더 관리자로 로그인해 확인했다(이번 변경 후 코드가 바인드 마운트로 반영된 상태).

- 위젯 표시: `.env` 에 `AI_FAKE_PROVIDER=true` 를 추가한 뒤 가짜 제공자 위젯이 그대로 뜬다(플래그 없이는 안 뜨는 것은 시험으로 확인).
- 흐름: "팝업 만들어줘" 전송 → 답변 + 팝업 초안 카드(제목·본문·노출 기간 2026-10-21 ~ 2026-11-03·이미지 "만들 예정"·"이미지 만들고 작성 화면 열기"/"이미지 없이 열기"·남은 이미지 3장) → "이미지 없이 열기"(`/admin/popups/create?ai_proposal=4`) 열기 → **제목 "여름 휴가 안내"·시작 2026-10-21T00:00·종료 2026-11-03T23:59:59·"활성" 토글 꺼짐 상태로 채워짐**(저장 안 됨), 전체 새로고침 후에도 위젯이 열린 채 유지.
- 사용량 표시 "오늘 남은 질문 25회 · 이미지 3장 · 내 질문 5/30 · 사이트 전체 5/150".
- 콘솔/네트워크 오류 확인은 하지 않았다(미확인).

### 위젯 대비 수치 (WCAG 2.2 AA: 일반 글자 4.5:1 이상)

이전 에이전트가 브라우저로 쟀다는 수치는 어디에도 기록돼 있지 않아(이 파일이 미작성이었다) **이번에 같은 방식(computed color, 조상 배경 합성, oklch 는 canvas 로 환산)으로 다시 쟀다.** 다크는 실제 테마, 라이트는 `html` 의 `dark` 클래스를 제거해 `:root` 토큰을 적용한 상태(Filament 의 테마 전환 UI 를 거치지는 않음)에서 측정.

| 요소 | 다크 | 라이트 |
|---|---|---|
| 패널 글자 / 사용량 | 14.05:1 | 17.74:1 |
| 도움말·사용량 보조(`.aic-hint`·`.aic-usage-sub`) | 9.96:1 | 7.56:1 |
| 입력칸(textarea)·select·선택 안 된 구분 라벨 | 16.98:1 | 17.74:1 |
| AI 말풍선 | 9.86:1 | 16.12:1 |
| 내 말풍선·머리글(제목·닫기)·전송 버튼·카드 주 버튼·선택된 구분 라벨 | 10.30:1 | 5.03:1 |
| 카드 링크 | 14.05:1 | 17.74:1 |
| "이전 대화" 링크 버튼 | 8.14:1 | 6.70:1 |

전부 4.5:1 이상(최저: 라이트 주 버튼 5.03:1). 닫힌 상태의 열기 버튼(FAB)은 측정하지 않았다(미확인). 토큰 값 자체의 두 모드 대비는 `AiWidgetCssTest` 가 매번 계산·검증한다.

## 6. 사용자 결정이 필요한 항목 (그대로 유지된 기본값)

1. 한도 기본값: 관리자 질문 30·이미지 5, 사이트 질문 150·이미지 20(사이트 설정 > AI 연동에서 변경), 0 = 그 종류 차단(무제한 없음), 오류로 끝난 호출은 한도 소모.
2. **정책 차단 환불**: 명시 코드 8개만 환불, 환불 횟수 상한 없음(사용자 결정 반영). 정책 거절만 반복하는 호출은 하루 한도에 안 걸리고 관리자별 동시 1건 잠금만이 속도 제한이다.
3. 카드 유효 시간 60분(열기 제한. 저장은 그 뒤에도 가능), `propose_image`·사이드바 항목을 만들지 않은 결정.
4. 운영 배포 순서: 코드와 `php artisan migrate` 는 같이(표가 없으면 이제 위젯만 빠지고 화면은 뜨지만 AI 비서는 동작하지 않는다). 운영 `.env` 에는 `AI_FAKE_PROVIDER` 를 두지 않는다(기본 false).

## 7. 남은 위험

- **Gemini 도구 호출(`functionDeclarations`)·OpenAI 모델 노후 여부는 실측 전**: 문제가 있으면 사이트 설정 "도구 호출 사용"을 꺼서 일반 대화로 내려간다(`ai_tools_enabled`). OpenAI `gpt-4o-mini`·`gpt-image-1` 의 현행 여부 미확인.
- 보관 정리는 팝업·배너·페이지의 이미지 칸만 "사용 중"으로 본다. 관리자가 AI 이미지를 미디어 라이브러리에서 게시글 본문(에디터)에 직접 넣었다면(AI 비서 흐름 밖) 보관 기간이 지나 파일이 지워질 수 있다.
- L9(성능)·L10(두 탭 이중 저장의 감사 링크 누락)은 보류.
- 로컬 전용: `docker-compose.override.yml`(커밋 안 함), 8095 개발 DB 의 시험 데이터, worktree·스택·볼륨 정리는 메인이 한다(`AI_V2_NOTES.md` §7 명령).
- 사용자 작업 트리(`cms-admin`, dev)의 `.env` 에서 개발용 가짜 제공자를 쓰려면 `AI_FAKE_PROVIDER=true` 한 줄이 새로 필요하다.
