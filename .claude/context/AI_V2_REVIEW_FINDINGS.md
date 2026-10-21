# AI 비서 2.0 — 회의적 리뷰 결과 (2026-10-21) — 처리 완료: 커밋 94c1ddf7, 결과표는 AI_V2_REPORT.md §3 (M3 는 사용자 결정으로 미적용, L9·L10 보류)

> 리뷰 에이전트(abbd323843b4aaa9e) 보고를 옮겨 적음. **모델의 정적 분석이므로 각 항목은 재현·코드 확인 후 수정**. HIGH 없음.
> 불변식 1~6(AI 쓰기 경로 없음·소유/만료·모델 출력 비신뢰·비용 안전·위젯 상태·CSS 토큰)은 코드에서 깰 수 없었다고 함.

## MEDIUM (병합 전 처리 권장)

- **M1 저장된 AI 초안이 이미지를 잃을 수 있음** — `AiProposalService.php:445-465` `markUsed` 가 `resolveForPrefill→isOpenable()`(`AiChatProposal.php:70`, 60분 만료 포함)을 다시 검사. 카드를 10:00 에 열고 11:30 저장하면 `markUsed` 가 조기 반환 → 상태가 `image_ready` 로 남고, 보존 정리(`AiChatService.php:371-376`)가 `status != used` 인 제안 이미지를 지워 실제 팝업/배너/페이지 이미지가 404. 메시지 이미지("이 이미지 사용하기")도 `:364-367` 에서 사용 여부와 무관하게 삭제. 수정: `markUsed` 는 id·소유자·kind·상태(proposed,image_ready)로 조회하고 `expires_at` 무시 / 채택 이미지는 정리에서 보호하거나 사용 시 파일 복사.
- **M2 위젯 `catch (RuntimeException)`** — `AiChatWidget.php:251-255` 가 `$e->getMessage()` 를 `$notice` 에 넣음. `QueryException`·`ModelNotFoundException`·`RecordsNotFoundException` 도 RuntimeException 이라 SQL 문구가 채팅창에 노출될 수 있음. 반대로 `requireConfigured()`/`provider()`(`AiChatService.php:70,262`)의 `InvalidArgumentException` 은 LogicException 계열이라 안 잡혀 500(대화의 제공자 키가 지워졌거나 fake 대화인데 APP_ENV 가 local 이 아닐 때; `send()` 도 같은 틈). 수정: 사용자 노출용 `AiProposalException extends RuntimeException` 을 두고 그것과 Limit/Busy 만 잡고 나머지는 일반 문구.
- **M3 정책 차단 환불이 무제한** — 환불 지점 `AiChatService.php:115,162,214`(`AiUsageReservation::refund`). 항상 RECITATION/SAFETY/`IMAGE_*` 를 돌려주는 프롬프트를 반복하면 한도(30/일·5/일)에 안 잡혀, 동시 1건 잠금만이 속도 제한 → 하루 1,000회 이상 제공자 호출 가능(제공자가 과금할 수도). `GeminiProvider.php:189-191` 은 `IMAGE_*` 전부(`IMAGE_OTHER` 같은 일반 코드 포함)를 `str_starts_with` 로 환불. 수정: 관리자별 하루 환불 횟수 상한(처음 N회만 환불, 이후 소모) + 명시적 허용 코드 목록. ← **사용자가 정한 "차단은 한도 소모 안 함" 정책의 남용 방지 보강이므로, 상한을 둬도 되는지 사용자 확인 후 반영.**
- **M4 위젯 장애가 모든 관리자 화면을 죽임** — `AdminPanelProvider.php:120-124` 가 `Blade::render('<livewire:ai-chat-widget />')` 를 try/catch 없이 호출, `render()`(`AiChatWidget.php:304-305`)가 패널이 닫혀 있어도 매 페이지 COUNT 쿼리 4개(채팅·이미지 사용량). 코드가 `migrate` 보다 먼저 배포되거나 DB 순간 장애면 `ai_chat_usage` 없음 → `status()` 예외 → 제공자·`ai_assistant` 권한이 있는 모든 관리자 페이지 500. 수정: 훅을 try/catch + `report($e)` + `''` 반환, `chatUsage`/`imageUsage` 는 `$this->isOpen` 일 때만 계산.

## LOW

- L1 이미지 이중 생성(TOCTOU): `AiChatService.php:189-206` 가 잠금 전에 제안을 읽음 → `reserve()` 뒤 재조회해 `image_path` 있으면 조기 반환(`:225` 원자 갱신이 파일은 지우지만 두 번째 생성 비용은 이미 발생).
- L2 `resolveOwned`(`AiProposalService.php:433-442`)가 prefill 보다 약함: kind 별 `canCreate`·`ai_assistant` 권한 재검사 없음(권한 박탈된 관리자가 기존 카드로 이미지 한도 소모 가능).
- L3 링크 title 호스트 스푸핑: `SafeMarkdown.php:113-119` 가 PHP `parse_url` 호스트 사용 — `https://evil.com\@bank.com/` 는 PHP 호스트 `bank.com`, 브라우저는 evil.com. `\`·authority 의 `@` 거부 또는 `AiText::httpUrl` 통과.
- L4 마크다운 비용: `SafeMarkdown.php:72-78` 에 `max_delimiters_per_line` 없음, `ai-chat-widget.blade.php:95`·`proposal-card.blade.php:29` 가 Livewire 요청마다 최대 200개 메시지+카드 본문 재파싱(`wire:model.live` 포함) → 상한 설정·`MAX_INPUT` 하향·메시지별 캐시/`.live` 제거.
- L5 `AiFormFillSchema.php:131` `preg_match('#^…+$#')` 가 끝 개행 허용 → `\z`.
- L6 날짜: `AiProposalService.php:329-335` `start_offset_days`/`duration_days` 가 있으면 명시 `end_date` 무시. `AiText.php:62-66` `floor($v) === $v` 에 상한 없이 `(int)` → INF·2^64 가 0(=오늘).
- L7 `conversations()`·`messages()`·`selectConversation`·`deleteConversation` 에 `requireUser()` 없음(권한 박탈 관리자가 `$wire.messages()` 로 자기 옛 대화 열람/삭제 — user_id 스코프는 유지). `AiChatWidget.php:54` `$notice` 잠금 없음(이스케이프되어 본인에게만). `AiChatService.php:47` fake 제공자가 `APP_ENV` 만으로 게이트 — 운영에 `APP_ENV=local` 이면 키 없이 위젯 노출 → 명시 플래그/`app.debug`.
- L8 제공자 실패가 로그 없이 삼켜짐(`AiChatService.php:116,163,218`) — 퇴역 모델·잘못된 키가 운영에서 안 보임. 상태 코드만 `report()`/로그(키는 헤더라 안전).
- L9 성능: `accessibleFor`·`canCreate` 가 send 당 `canAccess()` ~100회(`declarations`·`build`·`normalize`), `writableBoards` 3회 → 요청 단위 메모이즈. 사이트 설정 AI 탭 `todaySummary()` 가 큰 페이지의 Livewire 재렌더마다 재실행.
- L10 제안을 두 탭에서 열어 두 번 저장 가능(두 번째는 이미 used 라 감사 링크 누락) — 미관상.

## 불변식 확인 결과(리뷰 보고 요약)
AI 쓰기 경로 없음(콘텐츠 테이블 write/`->save()` 없음, 쓰는 것은 사용량·대화·메시지·제안·이미지 파일·감사 행뿐) / 소유·만료(대화 소유 스코프, 소프트삭제면 null, kind 일치, used 재오픈 불가, 감독 카드 `interactive=false`, `Locked` 속성, 열거 오라클 없음) / 모델 출력은 전부 `{{ }}` 이스케이프(`HtmlString` 은 `SafeMarkdown::chatHtml` 과 이스케이프된 요약 뷰뿐, 이미지 미렌더, 비 http(s) 링크는 텍스트로) / 시스템 지시문·도구에 URL·관리자 경로·PII 없음, 키는 헤더, UI 는 `[A-Z_]` 사유 코드만 / 사용량 예약 순서(행 생성→카운트→초과면 삭제)와 잠금 해제 / 채우기 버튼은 `screenPath` 를 마운트 때 저장해 업데이트 요청에서도 동작(실제 시험 존재) / `ai-chat.css` 색상 리터럴은 12-68줄 토큰 블록 안뿐 / `actingAs` finally 복구 / `HasAiProposalPrefill` 은 Filament 4 `CreateRecord` 흐름과 일치.
