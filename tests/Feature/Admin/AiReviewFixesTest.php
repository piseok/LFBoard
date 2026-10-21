<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Livewire\AiChatWidget;
use App\Models\AdminAuditLog;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\AiChatUsage;
use App\Models\Page;
use App\Models\User;
use App\Services\Ai\AiFormFillSchema;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiToolCall;
use App\Services\Ai\AiUserFacingException;
use App\Services\AiChatService;
use App\Services\AiUsageService;
use App\Services\SiteSettingService;
use App\Support\AiText;
use App\Support\SafeMarkdown;
use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// AI 비서 2.0 회의적 리뷰(AI_V2_REVIEW_FINDINGS.md) 후속 수정의 회귀 시험. 항목 번호(M1~M4, L1~L8)는 그 문서의 번호다.
// 실제 제공자는 부르지 않는다(Http::fake / 모의).
class AiReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul'));
    }

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => 'Admin', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function manager(array $permissions, string $email = 'manager@test.local'): User
    {
        return User::create([
            'name' => 'Manager', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => $permissions, 'is_active' => true,
        ]);
    }

    private function useGemini(): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-test-key', 'ai');
    }

    private function fakeImageApi(): void
    {
        Http::swap(new Factory);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => self::PNG]]]]]],
        ])]);
    }

    private function proposal(User $owner, string $tool, array $args, array $attributes = []): AiChatProposal
    {
        $service = app(AiProposalService::class);
        $cards = $service->normalize($owner, [new AiToolCall($tool, $args)]);
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'gemini', 'title' => '대화']);
        $message = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '초안이에요']);
        $proposal = $service->persist($message, $cards)[0];

        return $attributes === [] ? $proposal : tap($proposal)->update($attributes);
    }

    // ───────────── M1: 늦게 저장해도 사용 처리 + 쓰이는 이미지는 정리에서 보호 ─────────────

    public function test_saving_after_the_card_expired_still_marks_it_used(): void
    {
        $this->seed(LanguageSeeder::class);
        $admin = $this->admin();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_page', ['title' => '늦게 저장', 'slug' => 'late-save', 'body' => '본문']);

        $page = Livewire::withQueryParams(['ai_proposal' => $proposal->id])->test(CreatePage::class)
            ->assertSet('aiProposalId', $proposal->id);

        // 카드를 열어 폼을 채운 뒤 90분이 지나서 저장한다(카드 유효 시간 60분은 "열기" 제한이지 "저장" 제한이 아니다).
        Carbon::setTestNow(now()->addMinutes(90));
        $this->assertTrue($proposal->fresh()->isExpired());

        $page->call('create')->assertHasNoFormErrors();

        $fresh = $proposal->fresh();
        $this->assertSame(AiChatProposal::STATUS_USED, $fresh->status, '만료 뒤 저장해도 used 가 돼야 보관 정리가 고아 파일로 오해하지 않는다');
        $this->assertSame(Page::firstOrFail()->id, $fresh->used_record_id);
        $this->assertSame('proposal_used', AdminAuditLog::where('action', 'ai_chat')->latest('id')->first()->changes['type']);

        // 만료된 카드를 "다시 여는 것"은 여전히 막힌다.
        $other = $this->proposal($admin, 'propose_page', ['title' => '만료 카드']);
        Carbon::setTestNow(now()->addMinutes(61));
        Livewire::withQueryParams(['ai_proposal' => $other->id])->test(CreatePage::class)->assertSet('aiProposalId', null);
    }

    public function test_mark_used_still_refuses_other_owners_wrong_kinds_and_already_used_proposals(): void
    {
        $owner = $this->admin('owner@test.local');
        $other = $this->admin('other@test.local');
        $this->actingAs($owner);
        $service = app(AiProposalService::class);
        $proposal = $this->proposal($owner, 'propose_page', ['title' => '카드']);
        $record = new Page;
        $record->id = 77;

        $service->markUsed($other, $proposal->id, AiChatProposal::KIND_PAGE, $record); // 남의 제안
        $service->markUsed($owner, $proposal->id, AiChatProposal::KIND_POPUP, $record); // 종류 불일치
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status);

        $service->markUsed($owner, $proposal->id, AiChatProposal::KIND_PAGE, $record);
        $this->assertSame(AiChatProposal::STATUS_USED, $proposal->fresh()->status);

        $record->id = 78;
        $service->markUsed($owner, $proposal->id, AiChatProposal::KIND_PAGE, $record); // 두 번째 저장은 덮어쓰지 않는다
        $this->assertSame(77, $proposal->fresh()->used_record_id);
    }

    public function test_retention_prune_keeps_images_that_content_still_uses_and_removes_orphans(): void
    {
        Storage::fake('uploads');
        $admin = $this->admin();
        $files = ['msg_used', 'msg_orphan', 'card_used', 'card_orphan'];
        $paths = [];
        foreach ($files as $name) {
            $paths[$name] = "uploads/ai_generated/2026/07/{$name}.png";
            Storage::disk('uploads')->put($paths[$name], base64_decode(self::PNG));
        }

        // 메시지 이미지를 "이 이미지 사용하기"로 팝업에 적용하고 저장했다 / 카드 이미지가 페이지 대표 이미지로 쓰이는데 사용 표시는 못 남겼다(status image_ready).
        DB::table('popups')->insert(['title' => '쓰는 팝업', 'content_type' => 'image', 'image_path' => $paths['msg_used'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pages')->insert(['title' => '쓰는 페이지', 'slug' => 'uses-card-image', 'og_image' => $paths['card_used'], 'created_at' => now(), 'updated_at' => now()]);

        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'gemini', 'title' => '오래된 대화']);
        DB::table('ai_chat_conversations')->where('id', $conversation->id)->update(['created_at' => now()->subDays(120)]);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => null, 'image_path' => $paths['msg_used']]);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => null, 'image_path' => $paths['msg_orphan']]);
        $cardMessage = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '카드']);
        foreach (['card_used', 'card_orphan'] as $name) {
            AiChatProposal::create([
                'message_id' => $cardMessage->id, 'kind' => 'page', 'payload' => ['title' => $name], 'status' => AiChatProposal::STATUS_IMAGE_READY,
                'expires_at' => now()->subDays(100), 'image_path' => $paths[$name], 'created_at' => now()->subDays(120),
            ]);
        }

        app(AiChatService::class)->pruneExpired();

        $disk = Storage::disk('uploads');
        $this->assertTrue($disk->exists($paths['msg_used']), '팝업이 쓰는 메시지 이미지는 대화 정리 때 지우면 안 된다');
        $this->assertTrue($disk->exists($paths['card_used']), '페이지가 쓰는 카드 이미지는 상태와 무관하게 지우면 안 된다');
        $this->assertFalse($disk->exists($paths['msg_orphan']), '아무 데도 안 쓰는 메시지 이미지는 지운다');
        $this->assertFalse($disk->exists($paths['card_orphan']), '아무 데도 안 쓰는 카드 이미지는 지운다');
        $this->assertSame(0, AiChatConversation::withTrashed()->count(), '대화 자체는 정리된다');
    }

    // ───────────── M2: 위젯은 안내용 예외만 보여 주고 나머지는 일반 문구 ─────────────

    private function widgetWithBrokenService(\Throwable $failure, string $method)
    {
        $this->partialMock(AiChatService::class, function ($mock) use ($failure, $method) {
            $mock->shouldReceive('availableProviders')->andReturn(['gemini' => 'Google Gemini']);
            $mock->shouldReceive($method)->andThrow($failure);
        });
        $this->actingAs($this->admin());

        return Livewire::test(AiChatWidget::class)->set('selectedProvider', 'gemini')->set('isOpen', true);
    }

    public function test_sql_error_text_never_reaches_the_chat_window_and_is_reported(): void
    {
        Exceptions::fake();
        $failure = new QueryException('mysql', 'select secret_column from hidden_table where id = ?', [1], new \Exception('SQLSTATE[42S02]: hidden_table'));

        $component = $this->widgetWithBrokenService($failure, 'sendMessage')->set('input', '안녕')->call('send');

        $notice = (string) $component->get('notice');
        $this->assertStringContainsString('처리하지 못했습니다', $notice);
        $this->assertStringNotContainsString('secret_column', $notice);
        $this->assertStringNotContainsString('hidden_table', $notice);
        $component->assertSet('input', '안녕'); // 입력은 그대로
        Exceptions::assertReported(QueryException::class);
    }

    public function test_user_facing_exceptions_are_shown_as_written_and_not_reported(): void
    {
        Exceptions::fake();
        $component = $this->widgetWithBrokenService(new AiUserFacingException('오늘 한도를 모두 썼습니다.'), 'sendMessage')
            ->set('input', '안녕')->call('send');

        $component->assertSet('notice', '오늘 한도를 모두 썼습니다.');
        Exceptions::assertNothingReported();
    }

    public function test_card_image_failures_are_also_sanitised_and_a_missing_conversation_resets_the_view(): void
    {
        Exceptions::fake();
        $failure = new RecordsNotFoundException('no rows for table secret_table');
        $component = $this->widgetWithBrokenService($failure, 'generateProposalImage')->call('openProposalWithImage', 1);
        $this->assertStringNotContainsString('secret_table', (string) $component->get('notice'));
        $this->assertStringContainsString('처리하지 못했습니다', (string) $component->get('notice'));

        $gone = $this->widgetWithBrokenService(new ModelNotFoundException, 'sendMessage')->set('input', '이어서')->call('send');
        $this->assertStringContainsString('대화를 찾을 수 없습니다', (string) $gone->get('notice'));
        $gone->assertSet('activeConversationId', null);
    }

    public function test_a_provider_whose_key_was_removed_gives_a_notice_instead_of_a_500(): void
    {
        $this->useGemini();
        $this->actingAs($this->admin());
        $component = Livewire::test(AiChatWidget::class)->set('selectedProvider', 'gemini')->set('isOpen', true);

        app(SiteSettingService::class)->set('ai_gemini_api_key', '', 'ai'); // 위젯이 열려 있는 동안 키가 지워졌다
        $component->set('input', '안녕')->call('send');

        $this->assertStringContainsString('선택한 AI 제공자를 쓸 수 없습니다', (string) $component->get('notice'));
        $this->assertSame(0, AiChatUsage::count(), '제공자를 부르기 전에 막혔으니 한도도 쓰지 않았다');
    }

    // ───────────── M4: 위젯 장애가 화면 전체를 죽이지 않고, 닫힌 패널은 사용량 쿼리를 안 돌린다 ─────────────

    public function test_a_widget_failure_is_reported_but_the_admin_page_still_renders(): void
    {
        Exceptions::fake();
        $this->useGemini();
        $admin = $this->admin();
        // 패널이 열려 있을 때만 쓰는 사용량 계산이 터진다(표 누락·DB 장애 등). 다른 화면 요소는 이 서비스를 안 쓴다.
        $this->partialMock(AiUsageService::class, function ($mock) {
            $mock->shouldReceive('status')->andThrow(new \RuntimeException('usage table missing'));
        });

        $response = $this->actingAs($admin)->withSession(['ai_chat_widget_open' => true])->get('/admin');

        $this->assertSame(200, $response->getStatusCode(), (string) $response->exception?->getMessage());
        $response->assertDontSee('aic-root', false);
        Exceptions::assertReportedCount(1); // 위젯 장애는 조용히 삼키지 않고 report() 한다
    }

    public function test_a_missing_usage_table_does_not_turn_every_admin_page_into_a_500(): void
    {
        Exceptions::fake();
        $this->useGemini();
        $admin = $this->admin();
        Schema::drop('ai_chat_usage'); // 코드가 migrate 보다 먼저 배포된 상황

        // 열린 상태(세션에 유지됨)여도 페이지는 뜬다 - 위젯만 빠진다.
        $open = $this->actingAs($admin)->withSession(['ai_chat_widget_open' => true])->get('/admin');
        $this->assertSame(200, $open->getStatusCode(), (string) $open->exception?->getMessage());
        $open->assertDontSee('aic-root', false);
        Exceptions::assertReportedCount(1);
    }

    // (한 시험에 요청을 두 번 보내면 실패한 위젯 렌더가 Livewire 의 요청 내 상태를 남겨 두 번째 응답이 깨진다 - 운영은 요청마다 새로 뜨므로 시험을 나눈다.)
    public function test_a_closed_widget_does_not_even_touch_the_missing_usage_table(): void
    {
        Exceptions::fake();
        $this->useGemini();
        $admin = $this->admin();
        Schema::drop('ai_chat_usage');

        // 닫힌 상태에서는 사용량을 아예 계산하지 않으므로 오류도 없고 위젯(열기 버튼)도 정상 표시된다.
        $closed = $this->actingAs($admin)->withSession(['ai_chat_widget_open' => false])->get('/admin');
        $this->assertSame(200, $closed->getStatusCode(), (string) $closed->exception?->getMessage());
        $closed->assertSee('aic-fab', false);
        Exceptions::assertNothingReported();
    }

    public function test_a_closed_panel_runs_no_usage_queries_and_an_open_one_does(): void
    {
        $this->useGemini();
        $this->actingAs($this->admin());
        DB::enableQueryLog();

        $component = Livewire::test(AiChatWidget::class)->assertSet('isOpen', false);
        $closedQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'ai_chat_usage'));
        $this->assertCount(0, $closedQueries, '닫힌 패널이 매 페이지 사용량 COUNT 쿼리를 돌린다');

        DB::flushQueryLog();
        $component->call('toggle');
        $openQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'ai_chat_usage'));
        $this->assertGreaterThan(0, $openQueries->count(), '열린 패널은 남은 횟수를 보여 주려고 사용량을 센다');
    }

    // ───────────── L1: 잠금 안에서 제안을 다시 읽어 이미지 이중 생성을 막는다 ─────────────

    public function test_an_image_finished_by_another_tab_while_we_waited_is_not_generated_twice(): void
    {
        Storage::fake('uploads');
        $this->useGemini();
        $admin = $this->admin();
        $proposal = $this->proposal($admin, 'propose_popup', ['title' => '이미지 팝업', 'image_prompt' => '파란 원']);
        $this->fakeImageApi();

        $theirs = 'uploads/ai_generated/2026/10/other-tab.png';
        Storage::disk('uploads')->put($theirs, base64_decode(self::PNG));
        $real = app(AiUsageService::class);

        // 첫 조회(잠금 전)와 reserve() 사이에 다른 탭의 요청이 이미지를 만들어 끝냈다고 가정한다.
        $this->partialMock(AiUsageService::class, function ($mock) use ($real, $proposal, $theirs) {
            $mock->shouldReceive('reserve')->andReturnUsing(function (...$args) use ($real, $proposal, $theirs) {
                $proposal->update(['status' => AiChatProposal::STATUS_IMAGE_READY, 'image_path' => $theirs]);

                return $real->reserve(...$args);
            });
        });

        $result = app(AiChatService::class)->generateProposalImage($admin, $proposal->id);

        Http::assertNothingSent();
        $this->assertSame($theirs, $result->image_path);
        $this->assertSame($theirs, $proposal->fresh()->image_path);
        $this->assertSame(0, AiChatUsage::where('kind', 'image')->count(), '제공자를 부르지 않았으니 선기록한 사용 한 줄도 되돌린다');
        $this->assertCount(1, Storage::disk('uploads')->allFiles('uploads/ai_generated'), '두 번째 파일이 생기지 않는다');
    }

    // ───────────── L2: 카드 버튼은 지금도 그 종류를 만들 권한이 있어야 ─────────────

    public function test_revoked_permissions_stop_card_image_generation_and_prefill(): void
    {
        Storage::fake('uploads');
        $this->useGemini();
        $manager = $this->manager(['popups', 'ai_assistant']);
        $this->actingAs($manager);
        $proposal = $this->proposal($manager, 'propose_popup', ['title' => '이미지 팝업', 'image_prompt' => '파란 원']);
        $service = app(AiProposalService::class);
        $this->assertNotNull($service->resolveOwned($manager, $proposal->id), '권한이 있으면 통과(대조군)');

        // 팝업 작성 권한을 박탈당했다.
        $manager->update(['admin_permissions' => ['ai_assistant']]);
        $this->fakeImageApi();
        try {
            app(AiChatService::class)->generateProposalImage($manager->fresh(), $proposal->id);
            $this->fail('권한을 잃은 관리자가 이미지 한도를 쓰면 안 된다');
        } catch (AiUserFacingException) {
            Http::assertNothingSent();
        }
        $this->assertNull($service->resolveForPrefill($manager->fresh(), $proposal->id, AiChatProposal::KIND_POPUP));

        // AI 비서 권한을 박탈당했다.
        $manager->update(['admin_permissions' => ['popups']]);
        $this->assertNull($service->resolveOwned($manager->fresh(), $proposal->id));
        $this->assertNull($service->resolveForPrefill($manager->fresh(), $proposal->id, AiChatProposal::KIND_POPUP));
        $this->assertSame(0, AiChatUsage::count());
    }

    // ───────────── L3: 링크 title 호스트 위장 ─────────────

    public function test_ambiguous_link_authorities_are_not_linked_and_normal_links_show_their_real_host(): void
    {
        // 마크다운은 `\@`·`\.` 처럼 역슬래시 뒤 구두점을 이스케이프로 읽어 역슬래시를 없앤다(그러면 URL 에 `@` 가 그대로 남아 막힌다).
        // (구두점이 아닌 글자 앞의 역슬래시는 CommonMark 가 %5C 로 바꿔 브라우저도 유효한 주소로 읽지 않는다.)
        $spoofs = [
            'https://evil.com\\@bank.com/login',
            'https://bank.com@evil.com/',
            'https://bank.com:pw@evil.com/',
            'https:///evil.com/',
        ];

        foreach ($spoofs as $url) {
            $html = (string) SafeMarkdown::chatHtml('[계정 확인]('.$url.') 클릭');
            $this->assertStringNotContainsString('<a ', $html, $url);
            $this->assertNull(AiText::httpHost($url), $url);
            $this->assertNull(AiText::httpUrl($url), $url);
        }

        // 서버가 직접 받는 원문 문자열(마크다운을 거치지 않음)은 역슬래시·탭도 거절한다.
        foreach (['https://evil.com\\.bank.com/', "https://evil.com\t.bank.com/", 'https://evil.com .bank.com/'] as $raw) {
            $this->assertNull(AiText::httpHost($raw), $raw);
        }

        $html = (string) SafeMarkdown::chatHtml('[안내](https://www.example.com/guide?x=1#top)');
        $this->assertStringContainsString('<a href="https://www.example.com/guide?x=1#top"', $html);
        $this->assertStringContainsString('title="www.example.com"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);

        // 한글 경로 같은 정상 주소는 계속 링크가 된다.
        $this->assertStringContainsString('<a ', (string) SafeMarkdown::chatHtml('[문서](https://example.com/가이드)'));

        // 에디터용(채우기)은 어떤 링크도 글자만 남긴다.
        $this->assertStringNotContainsString('<a ', SafeMarkdown::editorHtml('[안내](https://www.example.com/guide)'));
    }

    public function test_banner_link_urls_with_ambiguous_authority_are_dropped(): void
    {
        $admin = $this->admin();
        $service = app(AiProposalService::class);

        $cards = $service->normalize($admin, [
            new AiToolCall('propose_banner', ['title' => '배너', 'link_url' => 'https://evil.com\\@bank.com/']),
            new AiToolCall('propose_banner', ['title' => '배너 2', 'link_url' => 'https://example.com/event']),
        ]);

        $this->assertNull($cards[0]['payload']['link_url']);
        $this->assertSame('https://example.com/event', $cards[1]['payload']['link_url']);
    }

    // ───────────── L4: 서식 기호 폭주 입력 ─────────────

    public function test_a_line_full_of_emphasis_delimiters_renders_in_bounded_time(): void
    {
        $started = microtime(true);
        $html = (string) SafeMarkdown::chatHtml(str_repeat('*a _b ', 4000));
        $elapsed = microtime(true) - $started;

        $this->assertNotSame('', $html);
        $this->assertLessThan(3.0, $elapsed, "서식 기호 폭주 한 줄이 {$elapsed}초 걸렸다");
        // 일반적인 서식은 그대로 동작한다.
        $this->assertStringContainsString('<strong>굵게</strong>', (string) SafeMarkdown::chatHtml('**굵게** 글'));
    }

    // ───────────── L5: 이미지 경로 검사의 \z ─────────────

    public function test_image_path_check_rejects_a_trailing_newline(): void
    {
        $this->assertTrue(AiFormFillSchema::isAllowedImagePath('uploads/ai_generated/2026/10/a.png'));
        $this->assertFalse(AiFormFillSchema::isAllowedImagePath("uploads/ai_generated/2026/10/a.png\n"));
        $this->assertFalse(AiFormFillSchema::isAllowedImagePath("uploads/ai_generated/a.png\n\n"));
    }

    // ───────────── L6: 날짜 ─────────────

    public function test_explicit_end_date_is_kept_when_only_a_relative_start_is_given(): void
    {
        $service = app(AiProposalService::class);
        $admin = $this->admin();
        $card = fn (array $args) => $service->normalize($admin, [new AiToolCall('propose_popup', ['title' => '팝업'] + $args)])[0]['payload'];

        // "다음 주 월요일(5일 뒤)부터 11월 30일까지" - 예전에는 end_date 가 조용히 사라졌다.
        $p = $card(['start_offset_days' => 5, 'end_date' => '2026-11-30']);
        $this->assertSame('2026-10-26 00:00:00', $p['started_at']);
        $this->assertSame('2026-11-30 23:59:59', $p['ended_at']);

        // 기간(duration)은 명시 end_date 보다 우선하고, 시작이 명시 날짜여도 기간으로 종료를 계산한다.
        $p = $card(['start_date' => '2026-11-02', 'duration_days' => 3, 'end_date' => '2026-12-31']);
        $this->assertSame('2026-11-02 00:00:00', $p['started_at']);
        $this->assertSame('2026-11-04 23:59:59', $p['ended_at']);

        // 기간만 있으면 오늘부터(기존 동작), 명시 날짜만 있으면 그대로(기존 동작).
        $p = $card(['duration_days' => 14]);
        $this->assertSame('2026-10-21 00:00:00', $p['started_at']);
        $this->assertSame('2026-11-03 23:59:59', $p['ended_at']);
        $p = $card(['start_date' => '2026-11-02', 'end_date' => '2026-11-05']);
        $this->assertSame('2026-11-02 00:00:00', $p['started_at']);
        $this->assertSame('2026-11-05 23:59:59', $p['ended_at']);
    }

    public function test_infinite_or_overflowing_numbers_are_rejected_not_turned_into_today(): void
    {
        foreach ([INF, -INF, NAN, 2.0 ** 64, 1.0e30, '99999999999999999999', 3.5, 'abc', null, true] as $value) {
            $this->assertNull(AiText::intInRange($value, 0, 365), var_export($value, true));
        }

        $this->assertSame(0, AiText::intInRange(0, 0, 365));
        $this->assertSame(14, AiText::intInRange(14.0, 1, 365));
        $this->assertSame(7, AiText::intInRange(' 7 ', 0, 365));
        $this->assertNull(AiText::intInRange(366, 0, 365));

        // 오프셋이 무한대면 "오늘"이 아니라 명시한 날짜가 쓰인다.
        $admin = $this->admin();
        $card = app(AiProposalService::class)->normalize($admin, [new AiToolCall('propose_popup', [
            'title' => '팝업', 'start_offset_days' => 2.0 ** 64, 'start_date' => '2026-11-10',
        ])])[0]['payload'];
        $this->assertSame('2026-11-10 00:00:00', $card['started_at']);
    }

    // ───────────── L7: 권한을 박탈당한 관리자의 위젯 접근 ─────────────

    public function test_every_public_widget_data_method_requires_the_current_ai_permission(): void
    {
        $this->useGemini();
        $manager = $this->manager(['ai_assistant']);
        $this->actingAs($manager);
        $conversation = AiChatConversation::create(['user_id' => $manager->id, 'provider' => 'gemini', 'title' => '옛 대화']);
        // 메서드마다 따로 마운트한다(한 번 403 을 받은 컴포넌트는 같은 요청 흐름을 이어 쓰지 않는다).
        $components = [];
        foreach (['selectConversation', 'deleteConversation', 'conversations', 'messages'] as $method) {
            $components[$method] = Livewire::test(AiChatWidget::class)->assertSet('isOpen', false);
        }

        $manager->update(['admin_permissions' => []]); // 위젯이 떠 있는 동안 권한을 박탈당했다

        $components['selectConversation']->call('selectConversation', $conversation->id)->assertForbidden();
        $components['deleteConversation']->call('deleteConversation', $conversation->id)->assertForbidden();
        $components['conversations']->call('conversations')->assertForbidden();
        $components['messages']->call('messages')->assertForbidden();
        $this->assertNotNull(AiChatConversation::find($conversation->id), '삭제되지 않았다');
    }

    // ───────────── L8: 제공자 실패 로그(상태·사유 코드만) ─────────────

    public function test_provider_failures_are_logged_with_status_and_reason_but_never_the_key_or_body(): void
    {
        $this->useGemini();
        $admin = $this->admin();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'models/gemini-old is not found SECRET-BODY-TEXT'],
        ], 404)]);
        Log::spy();

        $reply = app(AiChatService::class)->sendMessage($admin, null, 'gemini', '안녕');

        $this->assertStringContainsString('AI 응답을 받아오지 못했습니다', $reply->content); // 관리자에게는 일반 문구
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $dump = json_encode($context, JSON_UNESCAPED_UNICODE);

            return $context['provider'] === 'gemini'
                && $context['operation'] === 'chat'
                && $context['status'] === 404
                && $context['reason'] === 'NOT_FOUND'
                && ! str_contains($dump, 'gm-test-key')
                && ! str_contains($dump, 'SECRET-BODY-TEXT')
                && ! str_contains($message, 'SECRET');
        });
    }

    public function test_policy_blocks_are_not_logged_as_failures(): void
    {
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);
        Log::spy();

        app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '안녕');

        Log::shouldNotHaveReceived('warning');
    }
}
