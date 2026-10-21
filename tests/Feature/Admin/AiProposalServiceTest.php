<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\Board;
use App\Models\Popup;
use App\Models\User;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiToolCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// 행동 카드(제안) - 모델의 도구 호출 인자를 서버가 검증·정규화하고, 권한 없는 것은 걸러내며, 소유자·만료·종류를 확인하는지.
// 모델은 제안만 한다: 이 서비스에는 실행(저장)이 없다.
class AiProposalServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiProposalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiProposalService::class);
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul'));
    }

    private function super(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function manager(array $permissions, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Manager', 'email' => 'manager'.count($permissions).'@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => $permissions, 'is_active' => true,
        ], $extra));
    }

    private function board(string $name, bool $editor = true, array $extra = []): Board
    {
        return Board::create(array_merge([
            'name' => $name, 'slug' => 'b'.Board::count(), 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'use_editor' => $editor,
        ], $extra));
    }

    /** @return array<int, array{kind: string, payload: array<string, mixed>}> */
    private function normalizeOne(User $admin, string $tool, array $args): array
    {
        return $this->service->normalize($admin, [new AiToolCall($tool, $args)]);
    }

    // ───────────── 정규화 ─────────────

    public function test_popup_title_is_one_line_capped_at_100_and_empty_title_drops_the_card(): void
    {
        $admin = $this->super();

        $cards = $this->normalizeOne($admin, 'propose_popup', ['title' => "여름\n이벤트\t안내".str_repeat('가', 200)]);
        $this->assertCount(1, $cards);
        $this->assertSame(100, mb_strlen($cards[0]['payload']['title']));
        $this->assertStringNotContainsString("\n", $cards[0]['payload']['title']);
        $this->assertStringStartsWith('여름 이벤트 안내', $cards[0]['payload']['title']);

        $this->assertSame([], $this->normalizeOne($admin, 'propose_popup', ['title' => "  \n  "]));
        $this->assertSame([], $this->normalizeOne($admin, 'propose_popup', ['body' => '제목 없음']));
    }

    public function test_relative_offsets_are_computed_by_the_server_and_preferred_over_absolute_dates(): void
    {
        $cards = $this->normalizeOne($this->super(), 'propose_popup', [
            'title' => '이벤트', 'start_offset_days' => 2, 'duration_days' => 14,
            'start_date' => '2030-01-01', 'end_date' => '2030-01-02',
        ]);

        $this->assertSame('2026-10-23 00:00:00', $cards[0]['payload']['started_at']);
        $this->assertSame('2026-11-05 23:59:59', $cards[0]['payload']['ended_at']);
    }

    public function test_absolute_dates_must_be_strict_and_in_range(): void
    {
        $admin = $this->super();

        $ok = $this->normalizeOne($admin, 'propose_popup', ['title' => 'a', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $this->assertSame('2026-11-01 00:00:00', $ok[0]['payload']['started_at']);
        $this->assertSame('2026-11-30 23:59:59', $ok[0]['payload']['ended_at']);

        foreach (['2026-02-31', 'next monday', '2026/11/01', '26-11-01', '2026-11-01T10:00', '2000-01-01', '2099-01-01', 20261101] as $bad) {
            $cards = $this->normalizeOne($admin, 'propose_popup', ['title' => 'a', 'start_date' => $bad]);
            $this->assertNull($cards[0]['payload']['started_at'], "잘못된 날짜 '{$bad}' 는 버려져야 합니다");
        }

        // 종료가 시작보다 앞서면 종료만 버린다.
        $reversed = $this->normalizeOne($admin, 'propose_popup', ['title' => 'a', 'start_date' => '2026-12-01', 'end_date' => '2026-11-01']);
        $this->assertSame('2026-12-01 00:00:00', $reversed[0]['payload']['started_at']);
        $this->assertNull($reversed[0]['payload']['ended_at']);

        // 범위를 벗어난 오프셋은 버린다(음수·1년 초과·숫자가 아닌 값).
        $badOffset = $this->normalizeOne($admin, 'propose_popup', ['title' => 'a', 'start_offset_days' => -3, 'duration_days' => 'abc']);
        $this->assertNull($badOffset[0]['payload']['started_at']);
    }

    public function test_image_prompt_is_single_line_and_capped_at_500_chars(): void
    {
        $cards = $this->normalizeOne($this->super(), 'propose_popup', [
            'title' => '이미지', 'image_prompt' => "여름 바다\n\r\n\u{0007}해변".str_repeat('x', 600),
        ]);

        $prompt = $cards[0]['payload']['image_prompt'];
        $this->assertSame(500, mb_strlen($prompt));
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1f]/', $prompt);
        $this->assertSame('image', $cards[0]['payload']['content_type']);

        $textOnly = $this->normalizeOne(User::first(), 'propose_popup', ['title' => '글만', 'body' => '안내']);
        $this->assertSame('html', $textOnly[0]['payload']['content_type']);
        $this->assertNull($textOnly[0]['payload']['image_prompt']);
    }

    public function test_body_is_rendered_into_safe_html_with_raw_html_escaped_and_links_images_reduced_to_text(): void
    {
        $admin = $this->super();
        $cards = $this->normalizeOne($admin, 'propose_popup', [
            'title' => '공지',
            'body' => "첫 문단 <script>alert(1)</script>\n\n<img src=https://evil.test/t.png onerror=alert(1)>\n\n[링크](https://evil.test) ![추적](https://evil.test/p.png?q=1)",
        ]);
        $proposal = $this->persistOne($admin, $cards[0]);

        $html = $this->service->prefillData($proposal)['html_content'];

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('evil.test/p.png', $html, '이미지 주소는 어디에도 남지 않는다');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('링크', $html); // 링크는 글자만 남는다
        $this->assertGreaterThanOrEqual(2, substr_count($html, '<p>'));
    }

    public function test_light_markdown_in_the_body_becomes_real_formatting_and_single_newlines_become_line_breaks(): void
    {
        $admin = $this->super();
        $cards = $this->normalizeOne($admin, 'propose_page', ['title' => '소개', 'body' => "**굵게**\n둘째 줄\n\n- 하나\n- 둘"]);
        $html = $this->service->prefillData($this->persistOne($admin, $cards[0]))['content'];

        $this->assertStringContainsString('<strong>굵게</strong><br />', $html);
        $this->assertStringContainsString('<li>하나</li>', $html);
    }

    public function test_unknown_tool_names_are_ignored_and_duplicates_collapse_and_cards_are_capped_at_three(): void
    {
        $admin = $this->super();

        $this->assertSame([], $this->service->normalize($admin, [new AiToolCall('delete_everything', ['x' => 1])]));

        $dup = $this->service->normalize($admin, [
            new AiToolCall('propose_popup', ['title' => '같은 팝업']),
            new AiToolCall('propose_popup', ['title' => '같은 팝업']),
        ]);
        $this->assertCount(1, $dup);

        $many = $this->service->normalize($admin, array_map(
            fn (int $i) => new AiToolCall('propose_popup', ['title' => "팝업 {$i}"]), range(1, 6)
        ));
        $this->assertCount(AiProposalService::MAX_CARDS, $many);
    }

    public function test_banner_link_must_be_http_or_https_and_group_key_is_validated(): void
    {
        $admin = $this->super();

        $bad = $this->normalizeOne($admin, 'propose_banner', ['title' => '배너', 'link_url' => 'javascript:alert(1)', 'group_key' => 'main top!']);
        $this->assertNull($bad[0]['payload']['link_url']);
        $this->assertNull($bad[0]['payload']['group_key']);

        $good = $this->normalizeOne($admin, 'propose_banner', ['title' => '배너', 'link_url' => 'https://example.com/a?b=1', 'group_key' => 'main_top']);
        $this->assertSame('https://example.com/a?b=1', $good[0]['payload']['link_url']);
        $this->assertSame('main_top', $good[0]['payload']['group_key']);
    }

    public function test_page_slug_is_validated_and_meta_fields_are_single_line(): void
    {
        $admin = $this->super();

        $cards = $this->normalizeOne($admin, 'propose_page', [
            'title' => '회사 소개', 'slug' => 'About Us!', 'meta_description' => "설명\n두 줄", 'body' => '본문',
        ]);
        $this->assertNull($cards[0]['payload']['slug']);
        $this->assertSame('설명 두 줄', $cards[0]['payload']['meta_description']);

        $ok = $this->normalizeOne($admin, 'propose_page', ['title' => '소개', 'slug' => 'about-us']);
        $this->assertSame('about-us', $ok[0]['payload']['slug']);
    }

    // ───────────── 권한 ─────────────

    public function test_declarations_and_cards_only_include_what_the_admin_may_use(): void
    {
        $limited = $this->manager(['popups', 'ai_assistant']);

        $names = array_map(fn ($tool) => $tool->name, $this->service->declarations($limited));
        $this->assertContains('propose_popup', $names);
        $this->assertContains('open_admin_page', $names);
        $this->assertNotContains('propose_post', $names);
        $this->assertNotContains('propose_page', $names);
        $this->assertNotContains('propose_banner', $names);

        $open = collect($this->service->declarations($limited))->firstWhere('name', 'open_admin_page');
        $screens = $open->parameters['properties']['screen']['enum'];
        $this->assertContains('popups', $screens);
        $this->assertNotContains('posts', $screens);
        $this->assertNotContains('site_settings', $screens);
        $this->assertNotContains('ai_chat_logs', $screens);

        // 모델이 권한 밖 도구를 억지로 호출해도 카드가 만들어지지 않는다.
        $this->assertSame([], $this->normalizeOne($limited, 'propose_post', ['title' => '글', 'body' => '본문']));
        $this->assertSame([], $this->normalizeOne($limited, 'open_admin_page', ['screen' => 'site_settings']));
        $this->assertCount(1, $this->normalizeOne($limited, 'open_admin_page', ['screen' => 'popups']));
    }

    public function test_open_page_create_target_only_when_the_screen_has_a_create_page_and_the_admin_may_create(): void
    {
        $admin = $this->super();

        $this->assertSame('create', $this->normalizeOne($admin, 'open_admin_page', ['screen' => 'boards', 'target' => 'create'])[0]['payload']['target']);
        // 문의에는 작성 화면이 없다.
        $this->assertSame('list', $this->normalizeOne($admin, 'open_admin_page', ['screen' => 'inquiries', 'target' => 'create'])[0]['payload']['target']);
        // 키를 지어내면 버린다.
        $this->assertSame([], $this->normalizeOne($admin, 'open_admin_page', ['screen' => 'admin_secret_panel']));
    }

    public function test_board_is_resolved_only_among_writable_boards_and_ambiguity_leaves_it_unset(): void
    {
        $notice = $this->board('공지사항');
        $this->board('자유게시판');
        $this->board('자유 질문게시판');
        $this->board('비활성 게시판', true, ['is_active' => false]);
        $admin = $this->super();

        $exact = $this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '공지사항']);
        $this->assertSame($notice->id, $exact[0]['payload']['board_id']);

        // "자유" 는 두 게시판에 부분 일치 → 모호하므로 고르지 않는다(작성 화면에서 사람이 고른다).
        $ambiguous = $this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '자유']);
        $this->assertNull($ambiguous[0]['payload']['board_id']);

        // 비활성 게시판·없는 게시판은 고르지 않는다.
        $this->assertNull($this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '비활성 게시판'])[0]['payload']['board_id']);
        $this->assertNull($this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '없는 게시판'])[0]['payload']['board_id']);
    }

    public function test_board_scope_of_a_manager_limits_which_boards_the_model_may_pick(): void
    {
        $mine = $this->board('내 게시판');
        $other = $this->board('남의 게시판');
        $manager = $this->manager(['posts', 'ai_assistant'], ['admin_board_scope' => [$mine->id]]);

        $names = $this->service->writableBoards($manager)->pluck('name')->all();
        $this->assertSame(['내 게시판'], $names);

        $card = $this->normalizeOne($manager, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '남의 게시판']);
        $this->assertNull($card[0]['payload']['board_id']);
        $this->assertNotSame($other->id, $card[0]['payload']['board_id']);
    }

    // ───────────── 저장 · 소유자 · 만료 ─────────────

    private function persistOne(User $admin, array $card, string $provider = 'gemini'): AiChatProposal
    {
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => $provider, 'title' => '대화']);
        $message = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '준비했어요']);

        return $this->service->persist($message, [$card])[0];
    }

    private function popupCard(): array
    {
        return ['kind' => AiChatProposal::KIND_POPUP, 'payload' => [
            'title' => '여름 이벤트', 'body' => '본문', 'content_type' => 'html',
            'started_at' => '2026-10-21 00:00:00', 'ended_at' => '2026-11-03 23:59:59', 'image_prompt' => null, 'image_alt' => '여름',
        ]];
    }

    public function test_persist_sets_a_one_hour_expiry_and_proposed_status(): void
    {
        $proposal = $this->persistOne($this->super(), $this->popupCard());

        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->status);
        $this->assertTrue($proposal->expires_at->equalTo(now()->addMinutes(60)));
    }

    public function test_only_the_owner_can_resolve_a_proposal_and_the_kind_must_match(): void
    {
        $owner = $this->super('owner@test.local');
        $other = $this->super('other@test.local');
        $proposal = $this->persistOne($owner, $this->popupCard());

        $this->assertNotNull($this->service->resolveForPrefill($owner, $proposal->id, AiChatProposal::KIND_POPUP));
        $this->assertNull($this->service->resolveForPrefill($other, $proposal->id, AiChatProposal::KIND_POPUP), '남의 제안은 열 수 없다');
        $this->assertNull($this->service->resolveForPrefill($owner, $proposal->id, AiChatProposal::KIND_POST), '종류가 다르면 열 수 없다');
        $this->assertNull($this->service->resolveForPrefill($owner, 999999, AiChatProposal::KIND_POPUP));
        $this->assertNull($this->service->resolveOwned($other, $proposal->id));
    }

    public function test_expired_and_used_proposals_cannot_be_opened_and_deleted_conversations_hide_cards(): void
    {
        $owner = $this->super();
        $proposal = $this->persistOne($owner, $this->popupCard());

        Carbon::setTestNow(now()->addMinutes(61));
        $this->assertNull($this->service->resolveForPrefill($owner, $proposal->id, AiChatProposal::KIND_POPUP), '1시간이 지나면 만료');

        Carbon::setTestNow(Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul'));
        $this->assertNotNull($this->service->resolveForPrefill($owner, $proposal->id, AiChatProposal::KIND_POPUP));

        $proposal->message->conversation->delete(); // 대화 삭제(소프트) - 카드도 더는 열 수 없다
        $this->assertNull($this->service->resolveForPrefill($owner, $proposal->id, AiChatProposal::KIND_POPUP));
    }

    public function test_mark_used_is_atomic_owner_checked_and_links_the_audit_log(): void
    {
        $owner = $this->super('owner@test.local');
        $other = $this->super('other@test.local');
        $proposal = $this->persistOne($owner, $this->popupCard());
        $popup = Popup::create(['title' => '여름 이벤트', 'locale' => 'ko', 'content_type' => 'html', 'is_active' => false]);

        // 남이 호출해도 아무 일도 일어나지 않는다.
        $this->service->markUsed($other, $proposal->id, AiChatProposal::KIND_POPUP, $popup);
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status);

        $this->service->markUsed($owner, $proposal->id, AiChatProposal::KIND_POPUP, $popup);
        $fresh = $proposal->fresh();
        $this->assertSame(AiChatProposal::STATUS_USED, $fresh->status);
        $this->assertSame($popup->id, $fresh->used_record_id);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $owner->id, 'action' => 'ai_chat', 'auditable_type' => Popup::class, 'auditable_id' => $popup->id,
        ]);

        // 두 번째 호출은 열 수 없는 상태(used)라 아무 일도 없다 - 감사 로그가 중복되지 않는다.
        $this->service->markUsed($owner, $proposal->id, AiChatProposal::KIND_POPUP, $popup);
        $this->assertSame(1, AdminAuditLog::where('action', 'ai_chat')->where('auditable_type', Popup::class)->count());
    }

    // ───────────── 미리 채우기 값 ─────────────

    public function test_prefill_data_is_always_unpublished_and_matches_each_form(): void
    {
        $admin = $this->super();
        $board = $this->board('공지사항', true);
        $textBoard = $this->board('텍스트 게시판', false);

        $popup = $this->service->prefillData($this->persistOne($admin, $this->popupCard()));
        $this->assertFalse($popup['is_active']);
        $this->assertSame('html', $popup['content_type']);
        $this->assertSame('<p>본문</p>', $popup['html_content']);
        $this->assertSame('2026-10-21 00:00:00', $popup['started_at']);

        $post = $this->service->prefillData($this->persistOne($admin, $this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문', 'board_name' => '공지사항'])[0]));
        $this->assertTrue($post['is_draft']);
        $this->assertSame($board->id, $post['board_id']);
        $this->assertArrayHasKey('content', $post);
        $this->assertArrayNotHasKey('content_text', $post);

        $textPost = $this->service->prefillData($this->persistOne($admin, $this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => "본문\n둘째줄", 'board_name' => '텍스트 게시판'])[0]));
        $this->assertSame("본문\n둘째줄", $textPost['content_text']);
        $this->assertArrayNotHasKey('content', $textPost);

        // 게시판을 정하지 못하면 두 칸 모두 채워 둔다(나중에 고른 게시판 형식에 맞게 쓰이도록).
        $unset = $this->service->prefillData($this->persistOne($admin, $this->normalizeOne($admin, 'propose_post', ['title' => '글', 'body' => '본문'])[0]));
        $this->assertArrayHasKey('content', $unset);
        $this->assertArrayHasKey('content_text', $unset);
        $this->assertArrayNotHasKey('board_id', $unset);

        $page = $this->service->prefillData($this->persistOne($admin, $this->normalizeOne($admin, 'propose_page', ['title' => '소개', 'body' => '본문'])[0]));
        $this->assertFalse($page['is_active']);

        $banner = $this->service->prefillData($this->persistOne($admin, $this->normalizeOne($admin, 'propose_banner', ['title' => '배너'])[0]));
        $this->assertFalse($banner['is_active']);
    }

    public function test_image_path_is_included_once_the_card_has_an_image(): void
    {
        $admin = $this->super();
        $proposal = $this->persistOne($admin, $this->normalizeOne($admin, 'propose_popup', ['title' => '이미지 팝업', 'image_prompt' => '바다'])[0]);
        $proposal->update(['image_path' => 'uploads/ai_generated/2026/10/x.png', 'status' => AiChatProposal::STATUS_IMAGE_READY]);

        $data = $this->service->prefillData($proposal->fresh());

        $this->assertSame('image', $data['content_type']);
        $this->assertSame(['uploads/ai_generated/2026/10/x.png'], $data['image_path']);
        $this->assertArrayNotHasKey('html_content', $data);
    }

    public function test_open_url_is_built_by_the_server_with_the_proposal_id(): void
    {
        $admin = $this->super();
        $popup = $this->persistOne($admin, $this->popupCard());
        $open = $this->persistOne($admin, ['kind' => AiChatProposal::KIND_OPEN_PAGE, 'payload' => ['screen' => 'boards', 'target' => 'list', 'label' => '게시판 관리']]);

        $this->assertMatchesRegularExpression('#/popups/create\?ai_proposal='.$popup->id.'$#', $this->service->openUrl($popup));
        $this->assertMatchesRegularExpression('#/boards$#', $this->service->openUrl($open));
    }
}
