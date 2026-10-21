<?php

namespace App\Services\Ai;

use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\Board;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Support\AiText;
use App\Support\SafeMarkdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// 모델의 도구 호출을 "행동 카드(제안)"로 바꾸는 곳. 핵심 원칙은 세 가지다.
//  1. 모델은 제안만 한다 - 여기에는 실행(저장)이 없다. 카드의 버튼은 해당 Filament 작성 화면으로 가는 GET 링크일 뿐이고,
//     그 화면이 폼에 값만 채운다(저장은 사람이 누른다). 쓰기 전용 AI 엔드포인트가 없으므로 Filament 의 권한 확인
//     (canAccess/canCreate)을 우회할 길이 없다.
//  2. 모델 인자는 신뢰하지 않는다 - 제목은 한 줄로·길이 제한, 본문은 "평문(가벼운 마크다운)"으로만 받아 서버가 안전하게
//     HTML 로 만들고(원문 HTML 은 전부 이스케이프, 링크·이미지는 글자만 남김 - SafeMarkdown::editorHtml),
//     날짜는 서버가 계산·검증하며(상대 오프셋 우선), 링크는 http/https 만, 게시판은 서버가 존재·쓰기 가능 여부를 확인한다.
//  3. 현재 관리자가 쓸 수 없는 것은 도구 선언에도 카드에도 나오지 않는다.
class AiProposalService
{
    // 한 응답에 붙일 수 있는 카드 수.
    public const MAX_CARDS = 3;

    // 도구 이름 → 제안 종류.
    private const TOOL_KINDS = [
        'propose_popup' => AiChatProposal::KIND_POPUP,
        'propose_post' => AiChatProposal::KIND_POST,
        'propose_page' => AiChatProposal::KIND_PAGE,
        'propose_banner' => AiChatProposal::KIND_BANNER,
        'open_admin_page' => AiChatProposal::KIND_OPEN_PAGE,
    ];

    // 제안 종류 → 작성 화면 키(AdminScreenMap).
    public const KIND_SCREENS = [
        AiChatProposal::KIND_POPUP => 'popups',
        AiChatProposal::KIND_POST => 'posts',
        AiChatProposal::KIND_PAGE => 'pages',
        AiChatProposal::KIND_BANNER => 'banners',
    ];

    public function __construct(private readonly AdminAuditLogService $auditLog) {}

    // ───────────────────────── 도구 선언 ─────────────────────────

    /**
     * 현재 관리자가 쓸 수 있는 도구만 선언한다(권한 없는 화면의 도구·선택지는 모델에게 아예 보내지 않는다).
     *
     * @return array<int, AiToolDeclaration>
     */
    public function declarations(User $admin): array
    {
        $accessible = AdminScreenMap::accessibleFor($admin);
        $tools = [];

        if ($accessible !== []) {
            $tools[] = new AiToolDeclaration(
                'open_admin_page',
                '관리자가 특정 관리 화면으로 이동하는 버튼을 보여 준다. "어디서 관리해?", "○○ 화면 열어줘", "○○ 설정은 어디야?" 같은 길 안내 요청에 쓴다. 이동은 관리자가 버튼을 눌러야 일어난다.',
                [
                    'type' => 'object',
                    'properties' => [
                        'screen' => ['type' => 'string', 'description' => '열 화면의 키', 'enum' => array_keys($accessible)],
                        'target' => ['type' => 'string', 'description' => 'list(목록, 기본) 또는 create(새로 만들기 화면). create 는 작성 화면이 있는 화면에서만 가능', 'enum' => ['list', 'create']],
                    ],
                    'required' => ['screen'],
                ],
            );
        }

        $dateProperties = [
            'start_offset_days' => ['type' => 'integer', 'description' => '노출 시작일: 오늘부터 며칠 뒤인지(0=오늘). 관리자가 상대적으로 말한 경우(예: "다음 주 월요일부터")는 오늘 날짜를 기준으로 계산해 이 값을 쓴다'],
            'duration_days' => ['type' => 'integer', 'description' => '노출 기간(일수, 시작일 포함). 예: 2주간 = 14'],
            'start_date' => ['type' => 'string', 'description' => '관리자가 정확한 날짜를 말했을 때만 YYYY-MM-DD 로 시작일'],
            'end_date' => ['type' => 'string', 'description' => '관리자가 정확한 날짜를 말했을 때만 YYYY-MM-DD 로 종료일'],
        ];

        if (AdminScreenMap::canCreate($admin, 'popups')) {
            $tools[] = new AiToolDeclaration(
                'propose_popup',
                '팝업 초안을 제안한다("팝업 만들어줘"). 실제 저장·공개는 하지 않는다 - 관리자가 카드 버튼으로 팝업 작성 화면을 열어 내용을 확인하고 직접 저장한다. 이미지 팝업이면 image_prompt 를 준다.',
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'description' => '팝업 제목(100자 이내, 한 줄)'],
                    'body' => ['type' => 'string', 'description' => '팝업에 들어갈 안내 문구. 서식 없는 평문, 문단은 빈 줄로 구분. HTML 금지'],
                    ...$dateProperties,
                    'image_prompt' => ['type' => 'string', 'description' => '이미지 팝업일 때 만들 이미지 설명(글자는 넣지 말라고 적는다). 이미지가 필요 없으면 생략'],
                    'image_alt' => ['type' => 'string', 'description' => '이미지의 대체 텍스트(스크린리더용 짧은 설명)'],
                ], 'required' => ['title']],
            );
        }

        if (AdminScreenMap::canCreate($admin, 'posts')) {
            $boards = $this->writableBoards($admin);
            $boardProperty = ['type' => 'string', 'description' => '글을 올릴 게시판 이름. 관리자가 말하지 않았거나 모르면 생략(작성 화면에서 고른다)'];

            if ($boards->isNotEmpty()) {
                $boardProperty['enum'] = $boards->pluck('name')->values()->all();
            }

            $tools[] = new AiToolDeclaration(
                'propose_post',
                '게시글 초안을 제안한다("공지 글 써줘", "게시글 작성"). 실제 저장·공개는 하지 않는다 - 관리자가 카드 버튼으로 글쓰기 화면을 열면 임시저장 상태로 제목·본문이 채워지고, 직접 저장해야 한다.',
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'description' => '글 제목(255자 이내, 한 줄)'],
                    'body' => ['type' => 'string', 'description' => '글 본문. 서식 없는 평문, 문단은 빈 줄로 구분. HTML 금지'],
                    'board_name' => $boardProperty,
                ], 'required' => ['title', 'body']],
            );
        }

        if (AdminScreenMap::canCreate($admin, 'pages')) {
            $tools[] = new AiToolDeclaration(
                'propose_page',
                '콘텐츠 페이지(회사소개 같은 고정 페이지) 초안을 제안한다. 실제 저장·공개는 하지 않는다 - 관리자가 카드 버튼으로 작성 화면을 열어 비활성 상태로 채워진 내용을 확인하고 직접 저장한다.',
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'description' => '페이지 제목(255자 이내, 한 줄)'],
                    'slug' => ['type' => 'string', 'description' => '주소에 쓸 영문 소문자/숫자/하이픈(예: about-us). 모르면 생략'],
                    'body' => ['type' => 'string', 'description' => '페이지 본문. 서식 없는 평문, 문단은 빈 줄로 구분. HTML 금지'],
                    'meta_title' => ['type' => 'string', 'description' => '검색엔진용 제목(한 줄)'],
                    'meta_description' => ['type' => 'string', 'description' => '검색엔진용 설명(한 줄, 160자 이내)'],
                    'meta_keywords' => ['type' => 'string', 'description' => '검색엔진용 키워드(쉼표로 구분)'],
                    'image_prompt' => ['type' => 'string', 'description' => '대표(OG) 이미지로 만들 이미지 설명. 필요 없으면 생략'],
                ], 'required' => ['title']],
            );
        }

        if (AdminScreenMap::canCreate($admin, 'banners')) {
            $tools[] = new AiToolDeclaration(
                'propose_banner',
                '배너 초안을 제안한다. 실제 저장·공개는 하지 않는다 - 관리자가 카드 버튼으로 작성 화면을 열어 비활성 상태로 채워진 내용을 확인하고 직접 저장한다.',
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'description' => '배너 제목(100자 이내, 한 줄)'],
                    'group_key' => ['type' => 'string', 'description' => '배너 그룹 키(영문/숫자/_/-, 예: main_top). 모르면 생략'],
                    'alt_text' => ['type' => 'string', 'description' => '이미지 대체 텍스트(짧은 한 줄)'],
                    'link_url' => ['type' => 'string', 'description' => '클릭 시 이동할 http/https 주소. 모르면 생략'],
                    ...$dateProperties,
                    'image_prompt' => ['type' => 'string', 'description' => '만들 배너 이미지 설명(글자는 넣지 말라고 적는다). 필요 없으면 생략'],
                ], 'required' => ['title']],
            );
        }

        return $tools;
    }

    // ───────────────────────── 검증·정규화 ─────────────────────────

    /**
     * 모델의 도구 호출 목록 → 검증된 카드 값 목록(최대 MAX_CARDS, 중복 제거). DB 에는 아직 아무것도 쓰지 않는다.
     *
     * @param  array<int, AiToolCall>  $calls
     * @return array<int, array{kind: string, payload: array<string, mixed>}>
     */
    public function normalize(User $admin, array $calls): array
    {
        $cards = [];
        $seen = [];

        foreach ($calls as $call) {
            if (count($cards) >= self::MAX_CARDS) {
                break;
            }

            $kind = self::TOOL_KINDS[$call->name] ?? null;
            $payload = $kind === null ? null : $this->normalizePayload($admin, $kind, $call->args);

            if ($payload === null) {
                continue;
            }

            $fingerprint = $kind.'|'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $cards[] = ['kind' => $kind, 'payload' => $payload];
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    private function normalizePayload(User $admin, string $kind, array $args): ?array
    {
        return match ($kind) {
            AiChatProposal::KIND_OPEN_PAGE => $this->normalizeOpenPage($admin, $args),
            AiChatProposal::KIND_POPUP => AdminScreenMap::canCreate($admin, 'popups') ? $this->normalizePopup($args) : null,
            AiChatProposal::KIND_POST => AdminScreenMap::canCreate($admin, 'posts') ? $this->normalizePost($admin, $args) : null,
            AiChatProposal::KIND_PAGE => AdminScreenMap::canCreate($admin, 'pages') ? $this->normalizePage($args) : null,
            AiChatProposal::KIND_BANNER => AdminScreenMap::canCreate($admin, 'banners') ? $this->normalizeBanner($args) : null,
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function normalizeOpenPage(User $admin, array $args): ?array
    {
        $screen = is_string($args['screen'] ?? null) ? $args['screen'] : '';
        $accessible = AdminScreenMap::accessibleFor($admin);

        if (! isset($accessible[$screen])) {
            return null;
        }

        $target = ($args['target'] ?? 'list') === 'create' && AdminScreenMap::canCreate($admin, $screen) ? 'create' : 'list';

        return ['screen' => $screen, 'target' => $target, 'label' => AdminScreenMap::label($screen)];
    }

    /** @return array<string, mixed>|null */
    private function normalizePopup(array $args): ?array
    {
        $title = AiText::singleLine($args['title'] ?? '', 100);

        if ($title === '') {
            return null;
        }

        [$startedAt, $endedAt] = $this->resolveDates($args);
        $imagePrompt = $this->imagePrompt($args['image_prompt'] ?? null);
        $body = AiText::multiLine($args['body'] ?? '', 5000);

        return [
            'title' => $title,
            'body' => $body,
            'content_type' => $imagePrompt !== null ? 'image' : 'html',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'image_prompt' => $imagePrompt,
            'image_alt' => AiText::singleLine($args['image_alt'] ?? '', 100) ?: $title,
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizePost(User $admin, array $args): ?array
    {
        $title = AiText::singleLine($args['title'] ?? '', 255);
        $body = AiText::multiLine($args['body'] ?? '', 50000);

        if ($title === '') {
            return null;
        }

        $board = $this->resolveBoard($admin, $args['board_name'] ?? null);

        return [
            'title' => $title,
            'body' => $body,
            'board_id' => $board?->id,
            'board_name' => $board?->name,
            'use_editor' => $board === null ? null : (bool) $board->use_editor,
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizePage(array $args): ?array
    {
        $title = AiText::singleLine($args['title'] ?? '', 255);

        if ($title === '') {
            return null;
        }

        $slug = strtolower(AiText::singleLine($args['slug'] ?? '', 255));

        return [
            'title' => $title,
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) ? $slug : null,
            'body' => AiText::multiLine($args['body'] ?? '', 50000),
            'meta_title' => AiText::singleLine($args['meta_title'] ?? '', 255) ?: null,
            'meta_description' => AiText::singleLine($args['meta_description'] ?? '', 255) ?: null,
            'meta_keywords' => AiText::singleLine($args['meta_keywords'] ?? '', 255) ?: null,
            'image_prompt' => $this->imagePrompt($args['image_prompt'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizeBanner(array $args): ?array
    {
        $title = AiText::singleLine($args['title'] ?? '', 100);

        if ($title === '') {
            return null;
        }

        [$startedAt, $endedAt] = $this->resolveDates($args);
        $groupKey = AiText::singleLine($args['group_key'] ?? '', 50);

        return [
            'title' => $title,
            'group_key' => preg_match('/^[A-Za-z0-9_\-]{1,50}$/', $groupKey) ? $groupKey : null,
            'alt_text' => AiText::singleLine($args['alt_text'] ?? '', 255) ?: $title,
            'link_url' => AiText::httpUrl($args['link_url'] ?? null, 255),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'image_prompt' => $this->imagePrompt($args['image_prompt'] ?? null),
        ];
    }

    // 이미지 프롬프트: 개행·제어문자는 공백으로, 500자로 자른다. 비면 null(이미지 없음).
    private function imagePrompt(mixed $value): ?string
    {
        $prompt = AiText::singleLine($value ?? '', 500);

        return $prompt === '' ? null : $prompt;
    }

    /**
     * 노출 기간 - 상대 오프셋(start_offset_days/duration_days)을 우선하고, 없으면 엄격한 YYYY-MM-DD 로 받는다.
     * 형식이 틀리거나 범위를 벗어나면 그 날짜는 버린다(잘못된 날짜가 조용히 들어가는 것보다 비워 두는 편이 안전).
     *
     * @return array{0: ?string, 1: ?string} 'Y-m-d H:i:s' 앱 시간대
     */
    private function resolveDates(array $args): array
    {
        $today = now()->startOfDay();
        $offset = AiText::intInRange($args['start_offset_days'] ?? null, 0, 365);
        $duration = AiText::intInRange($args['duration_days'] ?? null, 1, 365);

        // 시작·종료를 각각 정한다: 시작은 상대 오프셋이 명시 start_date 보다 우선, 종료는 기간(duration_days)이 명시 end_date 보다 우선.
        // 예전에는 오프셋/기간 중 하나라도 있으면 명시 날짜를 통째로 무시해서 "다음 주 월요일부터 12월 31일까지"처럼
        // start_offset_days 와 end_date 를 함께 주면 end_date 가 조용히 사라졌다(리뷰 L6).
        $start = $offset !== null ? $today->copy()->addDays($offset) : AiText::strictDate($args['start_date'] ?? null);

        if ($duration !== null) {
            // 기간만 있고 시작이 없으면 오늘부터.
            $start ??= $today->copy();
            $end = $start->copy()->addDays($duration - 1)->endOfDay();
        } else {
            $end = AiText::strictDate($args['end_date'] ?? null)?->endOfDay();
        }

        // 어제보다 오래됐거나 5년보다 먼 날짜는 버린다.
        $earliest = $today->copy()->subDay();
        $latest = $today->copy()->addYears(5);
        $inRange = fn ($date) => $date !== null && $date->gte($earliest) && $date->lte($latest);
        $start = $inRange($start) ? $start : null;
        $end = $inRange($end) ? $end : null;

        if ($start !== null && $end !== null && $end->lt($start)) {
            $end = null;
        }

        return [$start?->format('Y-m-d H:i:s'), $end?->format('Y-m-d H:i:s')];
    }

    // ───────────────────────── 게시판 ─────────────────────────

    /**
     * 현재 관리자가 글을 쓸 수 있는 게시판("담당 게시판"·"담당 언어" 범위 안, 활성 게시판) - 이름만 모델에게 알린다.
     *
     * @return Collection<int, Board>
     */
    public function writableBoards(User $admin): Collection
    {
        return Board::query()
            ->where('is_active', true)
            ->when($admin->boardScope(), fn ($q, array $ids) => $q->whereIn('id', $ids))
            ->when($admin->localeScope(), fn ($q, array $locales) => $q->whereIn('locale', $locales))
            ->orderBy('sort_order')->orderBy('id')
            ->limit(30)
            ->get(['id', 'name', 'locale', 'use_editor']);
    }

    // 이름이 정확히 같거나, 쓸 수 있는 게시판 중 부분 일치가 정확히 하나일 때만 고른다. 모호하면 null(작성 화면에서 사람이 고름).
    private function resolveBoard(User $admin, mixed $name): ?Board
    {
        $needle = mb_strtolower(AiText::singleLine($name ?? '', 50));

        if ($needle === '') {
            return null;
        }

        $boards = $this->writableBoards($admin);
        $exact = $boards->filter(fn (Board $board) => mb_strtolower($board->name) === $needle);

        if ($exact->count() === 1) {
            return $exact->first();
        }

        $partial = $boards->filter(fn (Board $board) => str_contains(mb_strtolower($board->name), $needle));

        return $partial->count() === 1 ? $partial->first() : null;
    }

    // ───────────────────────── 저장·조회 ─────────────────────────

    /**
     * 검증된 카드 값을 AI 답변 메시지에 붙여 저장한다(소유자는 메시지 → 대화 → user_id 로 따라간다).
     *
     * @param  array<int, array{kind: string, payload: array<string, mixed>}>  $cards
     * @return array<int, AiChatProposal>
     */
    public function persist(AiChatMessage $message, array $cards): array
    {
        $saved = [];

        foreach ($cards as $card) {
            $saved[] = AiChatProposal::create([
                'message_id' => $message->id,
                'kind' => $card['kind'],
                'payload' => $card['payload'],
                'status' => AiChatProposal::STATUS_PROPOSED,
                'expires_at' => now()->addMinutes(AiChatProposal::TTL_MINUTES),
                'created_at' => now(),
            ]);
        }

        return $saved;
    }

    // 작성 화면이 ?ai_proposal= 로 열 때: 소유자 본인의 제안이고, 종류가 맞고, 만료·사용되지 않았을 때만 돌려준다.
    public function resolveForPrefill(User $admin, int $id, string $kind): ?AiChatProposal
    {
        $proposal = AiChatProposal::query()->with('message.conversation')->find($id);

        if ($proposal === null
            || $proposal->kind !== $kind
            || $proposal->ownerId() !== (int) $admin->id
            || ! $proposal->isOpenable()
            || ! $this->mayActOn($admin, $proposal)
        ) {
            return null;
        }

        return $proposal;
    }

    // 카드 버튼(이미지 생성 등)용: 종류 무관, 소유자·만료·상태에 더해 지금도 이 종류를 만들 권한이 있는지 확인한다
    // (AI 권한이나 해당 화면의 작성 권한을 박탈당한 관리자가 기존 카드로 이미지 한도를 쓰지 못하게 - 리뷰 L2).
    public function resolveOwned(User $admin, int $id): ?AiChatProposal
    {
        $proposal = AiChatProposal::query()->with('message.conversation')->find($id);

        if ($proposal === null
            || $proposal->ownerId() !== (int) $admin->id
            || ! $proposal->isOpenable()
            || ! $this->mayActOn($admin, $proposal)
        ) {
            return null;
        }

        return $proposal;
    }

    // 지금도 AI 비서를 쓸 수 있고(ai_assistant 권한), 이 제안 종류의 작성 화면을 열 권한(canAccess/canCreate)이 있는가.
    // 화면 열기(open_page) 카드는 이동 권한을 Filament 가 목적지에서 다시 확인하므로 AI 권한만 본다.
    private function mayActOn(User $admin, AiChatProposal $proposal): bool
    {
        if (! $admin->isAdmin() || ! $admin->hasAdminPermission('ai_assistant')) {
            return false;
        }

        $screen = self::KIND_SCREENS[$proposal->kind] ?? null;

        return $screen === null || AdminScreenMap::canCreate($admin, $screen);
    }

    // 작성 화면에서 실제로 저장된 직후 호출 - 원자적으로 used 로 바꾸고 감사 로그에 제안 id 를 남긴다.
    //
    // 만료(expires_at)는 "열기" 제한이지 "저장" 제한이 아니다. 카드를 10:00 에 열어 폼을 채워 둔 채 11:30 에 저장해도 사용 처리가 돼야 한다 -
    // 예전에는 여기서 만료를 다시 검사해 조기 반환했고, 그러면 제안이 used 가 되지 못해 보관 정리가 그 이미지를 고아 파일로 보고 지워
    // 실제 팝업·배너·페이지의 이미지가 404 가 됐다(리뷰 M1). 그래서 소유자·종류·미사용 상태만 본다(권한은 저장 화면 자체가 이미 확인했다).
    public function markUsed(User $admin, int $id, string $kind, Model $record): void
    {
        $proposal = AiChatProposal::query()->with('message.conversation')->find($id);

        if ($proposal === null
            || $proposal->kind !== $kind
            || $proposal->ownerId() !== (int) $admin->id
            || ! in_array($proposal->status, [AiChatProposal::STATUS_PROPOSED, AiChatProposal::STATUS_IMAGE_READY], true)
        ) {
            return;
        }

        $updated = AiChatProposal::query()
            ->whereKey($proposal->id)
            ->whereIn('status', [AiChatProposal::STATUS_PROPOSED, AiChatProposal::STATUS_IMAGE_READY])
            ->update([
                'status' => AiChatProposal::STATUS_USED,
                'used_at' => now(),
                'used_record_id' => $record->getKey(),
            ]);

        if ($updated > 0) {
            $this->auditLog->recordAiProposalUsed($admin, $proposal, $record);
        }
    }

    // ───────────────────────── 미리 채우기 값 ─────────────────────────

    /**
     * 작성 화면 폼에 채울 값(폼 필드 이름 → 값). 항상 비공개/비활성 상태로 둔다 - 사람이 확인하고 직접 켜야 공개된다.
     *
     * @return array<string, mixed>
     */
    public function prefillData(AiChatProposal $proposal): array
    {
        $p = $proposal->payload ?? [];
        $image = $proposal->image_path;

        return match ($proposal->kind) {
            AiChatProposal::KIND_POPUP => array_filter([
                'title' => $p['title'] ?? null,
                'content_type' => ($p['content_type'] ?? 'html') === 'image' ? 'image' : 'html',
                'image_path' => $image ? [$image] : null,
                'html_content' => ($p['content_type'] ?? 'html') === 'html' ? SafeMarkdown::editorHtml((string) ($p['body'] ?? '')) : null,
                'started_at' => $p['started_at'] ?? null,
                'ended_at' => $p['ended_at'] ?? null,
            ], fn ($value) => $value !== null && $value !== '') + ['is_active' => false],

            AiChatProposal::KIND_POST => $this->postPrefill($p),

            AiChatProposal::KIND_PAGE => array_filter([
                'title' => $p['title'] ?? null,
                'slug' => $p['slug'] ?? null,
                'content_type' => 'editor',
                'content' => SafeMarkdown::editorHtml((string) ($p['body'] ?? '')),
                'meta_title' => $p['meta_title'] ?? null,
                'meta_description' => $p['meta_description'] ?? null,
                'meta_keywords' => $p['meta_keywords'] ?? null,
                'og_image' => $image ? [$image] : null,
            ], fn ($value) => $value !== null && $value !== '') + ['is_active' => false],

            AiChatProposal::KIND_BANNER => array_filter([
                'title' => $p['title'] ?? null,
                'group_key' => $p['group_key'] ?? null,
                'content_type' => 'image',
                'image_path' => $image ? [$image] : null,
                'alt_text' => $p['alt_text'] ?? null,
                'link_url' => $p['link_url'] ?? null,
                'started_at' => $p['started_at'] ?? null,
                'ended_at' => $p['ended_at'] ?? null,
            ], fn ($value) => $value !== null && $value !== '') + ['is_active' => false],

            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function postPrefill(array $p): array
    {
        $body = (string) ($p['body'] ?? '');
        $data = ['title' => $p['title'] ?? '', 'is_draft' => true];

        if (! empty($p['board_id'])) {
            $data['board_id'] = (int) $p['board_id'];
        }

        // 게시판을 정했으면 그 게시판의 입력 방식(에디터/텍스트)에 맞는 칸만, 모르면 두 칸 모두 채운다.
        $useEditor = $p['use_editor'] ?? null;

        if ($useEditor !== false) {
            $data['content'] = SafeMarkdown::editorHtml($body);
        }

        if ($useEditor !== true) {
            $data['content_text'] = SafeMarkdown::plainText($body);
        }

        return $data;
    }

    // 이력(모델에게 다시 보내는 대화)에 남기는 카드의 평문 한 줄 요약 - JSON 을 넣지 않는다.
    public function summaryLine(AiChatProposal $proposal): string
    {
        $p = $proposal->payload ?? [];

        return match ($proposal->kind) {
            AiChatProposal::KIND_POPUP => '[행동 카드: 팝업 초안 "'.($p['title'] ?? '').'"]',
            AiChatProposal::KIND_POST => '[행동 카드: 게시글 초안 "'.($p['title'] ?? '').'"]',
            AiChatProposal::KIND_PAGE => '[행동 카드: 페이지 초안 "'.($p['title'] ?? '').'"]',
            AiChatProposal::KIND_BANNER => '[행동 카드: 배너 초안 "'.($p['title'] ?? '').'"]',
            AiChatProposal::KIND_OPEN_PAGE => '[행동 카드: "'.($p['label'] ?? '').'" 화면 열기 버튼]',
            default => '[행동 카드]',
        };
    }

    // 카드 제목에 쓰는 종류 이름.
    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            AiChatProposal::KIND_POPUP => '팝업 초안',
            AiChatProposal::KIND_POST => '게시글 초안',
            AiChatProposal::KIND_PAGE => '페이지 초안',
            AiChatProposal::KIND_BANNER => '배너 초안',
            AiChatProposal::KIND_OPEN_PAGE => '화면 열기',
            default => '제안',
        };
    }

    // 카드 버튼이 여는 주소(서버가 만든다). 작성 화면 종류는 ?ai_proposal=<id>, 화면 열기는 해당 목록/작성 화면.
    public function openUrl(AiChatProposal $proposal): ?string
    {
        if ($proposal->kind === AiChatProposal::KIND_OPEN_PAGE) {
            $screen = (string) ($proposal->payload['screen'] ?? '');

            return $screen === '' ? null : AdminScreenMap::url($screen, (string) ($proposal->payload['target'] ?? 'list'));
        }

        $screen = self::KIND_SCREENS[$proposal->kind] ?? null;

        return $screen === null ? null : AdminScreenMap::url($screen, 'create', ['ai_proposal' => $proposal->id]);
    }
}
