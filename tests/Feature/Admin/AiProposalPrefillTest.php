<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\AdminAuditLog;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\Board;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Post;
use App\Models\User;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiToolCall;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

// AI 비서 행동 카드의 작성 화면 미리 채우기 - 사용자가 원한 흐름:
//   "게시글 써줘" → 답변·카드 → 글쓰기 화면으로 이동(버튼) → 제목·본문이 폼에 채워져 있고 **저장은 사람이 누른다**.
// 채우기는 화면 열기(GET)만으로 일어나고 저장은 일어나지 않으며(비공개 상태로 채움), 소유자·종류·만료를 확인하고,
// 저장하면 제안이 used 가 되고 감사 로그에 제안 id 가 연결된다.
class AiProposalPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguageSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul'));
    }

    // Filament 4 리치 에디터의 폼 상태는 HTML 문자열이 아니라 Tiptap 문서(JSON 배열)다 - 글자·서식이 들어 있는지 본다.
    private function docHas(mixed $doc, string $text, ?string $nodeType = null): bool
    {
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE) ?: '';

        return str_contains($json, $text) && ($nodeType === null || str_contains($json, '"type":"'.$nodeType.'"'));
    }

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function board(string $name = '공지사항', bool $editor = true): Board
    {
        return Board::create(['name' => $name, 'slug' => 'notice', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => $editor]);
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

    private function open(string $page, AiChatProposal|int $proposal)
    {
        return Livewire::withQueryParams(['ai_proposal' => $proposal instanceof AiChatProposal ? $proposal->id : $proposal])->test($page);
    }

    // ───────────── 게시글(핵심 흐름) ─────────────

    public function test_post_card_opens_the_write_screen_with_title_and_body_filled_as_a_draft_and_nothing_is_saved(): void
    {
        $admin = $this->admin();
        $board = $this->board();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_post', [
            'title' => '신제품 출시 안내', 'body' => "안녕하세요.\n\n**신제품**을 소개합니다.\n- 하나\n- 둘", 'board_name' => '공지사항',
        ]);

        $this->open(CreatePost::class, $proposal)
            ->assertSet('data.title', '신제품 출시 안내')
            ->assertSet('data.board_id', $board->id)
            ->assertSet('data.is_draft', true) // 공개되지 않는 임시저장 상태
            ->assertSet('data.content', fn ($doc) => $this->docHas($doc, '신제품', 'bold') && $this->docHas($doc, '하나', 'listItem'))
            ->assertSet('aiProposalId', $proposal->id)
            ->assertNotified('AI 초안을 채웠습니다');

        // 화면을 연 것만으로는 아무것도 저장되지 않는다.
        $this->assertDatabaseCount('posts', 0);
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status);
    }

    public function test_saving_the_filled_post_is_done_by_the_human_and_marks_the_proposal_used_with_an_audit_link(): void
    {
        $admin = $this->admin();
        $this->board();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_post', ['title' => '신제품 출시 안내', 'body' => '본문입니다', 'board_name' => '공지사항']);

        $page = $this->open(CreatePost::class, $proposal);
        $this->assertDatabaseCount('posts', 0);

        $page->call('create')->assertHasNoFormErrors();

        $post = Post::firstOrFail();
        $this->assertSame('신제품 출시 안내', $post->title);
        $this->assertTrue($post->is_draft, '임시저장 상태로 저장된다(사람이 해제해야 공개)');
        $this->assertSame($admin->id, $post->user_id);

        $fresh = $proposal->fresh();
        $this->assertSame(AiChatProposal::STATUS_USED, $fresh->status);
        $this->assertSame($post->id, $fresh->used_record_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id, 'action' => 'ai_chat', 'auditable_type' => Post::class, 'auditable_id' => $post->id,
        ]);
        $this->assertSame('proposal_used', AdminAuditLog::where('action', 'ai_chat')->latest('id')->first()->changes['type']);

        // 이미 사용한 카드는 다시 열 수 없다.
        $this->open(CreatePost::class, $proposal)->assertSet('data.title', null)->assertSet('aiProposalId', null);
    }

    public function test_text_type_boards_get_the_plain_text_field_filled_instead_of_the_editor(): void
    {
        $admin = $this->admin();
        $this->board('텍스트 게시판', false);
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_post', ['title' => '글', 'body' => "첫 줄\n둘째 줄", 'board_name' => '텍스트 게시판']);

        $this->open(CreatePost::class, $proposal)
            ->assertSet('data.content_text', "첫 줄\n둘째 줄")
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame("첫 줄\n둘째 줄", Post::firstOrFail()->content);
    }

    // ───────────── 팝업 · 페이지 · 배너 ─────────────

    public function test_popup_card_fills_title_dates_and_html_body_inactive(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_popup', [
            'title' => '여름 휴가 안내', 'body' => '휴무입니다', 'start_offset_days' => 1, 'duration_days' => 3,
        ]);

        $this->open(CreatePopup::class, $proposal)
            ->assertSet('data.title', '여름 휴가 안내')
            ->assertSet('data.content_type', 'html')
            ->assertSet('data.html_content', fn ($doc) => $this->docHas($doc, '휴무입니다'))
            ->assertSet('data.is_active', false)
            ->assertSet('data.position', 'center') // 기본값은 그대로 유지된다
            ->assertSet('data.started_at', fn ($value) => str_starts_with((string) $value, '2026-10-22'))
            ->assertSet('data.ended_at', fn ($value) => str_starts_with((string) $value, '2026-10-24'));

        $this->assertDatabaseCount('popups', 0);
    }

    public function test_popup_card_with_a_generated_image_fills_the_upload_field(): void
    {
        Storage::fake('uploads');
        $admin = $this->admin();
        $this->actingAs($admin);
        $path = 'uploads/ai_generated/2026/10/test.png';
        Storage::disk('uploads')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $proposal = $this->proposal($admin, 'propose_popup', ['title' => '이미지 팝업', 'image_prompt' => '바다'], [
            'image_path' => $path, 'status' => AiChatProposal::STATUS_IMAGE_READY,
        ]);

        $this->open(CreatePopup::class, $proposal)
            ->assertSet('data.content_type', 'image')
            ->assertSet('data.image_path', fn ($value) => in_array($path, array_values((array) $value), true))
            ->assertSet('data.is_active', false);

        $this->assertDatabaseCount('popups', 0);
    }

    public function test_page_card_fills_the_editor_page_form_inactive_and_saving_marks_it_used(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_page', [
            'title' => '회사 소개', 'slug' => 'about-us', 'body' => '저희 회사는...', 'meta_description' => '소개 페이지',
        ]);

        $this->open(CreatePage::class, $proposal)
            ->assertSet('data.title', '회사 소개')
            ->assertSet('data.slug', 'about-us')
            ->assertSet('data.content_type', 'editor')
            ->assertSet('data.meta_description', '소개 페이지')
            ->assertSet('data.is_active', false)
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::firstOrFail();
        $this->assertFalse($page->is_active);
        $this->assertSame(AiChatProposal::STATUS_USED, $proposal->fresh()->status);
    }

    public function test_banner_card_fills_group_key_alt_link_and_stays_inactive(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $proposal = $this->proposal($admin, 'propose_banner', [
            'title' => '가을 배너', 'group_key' => 'main_top', 'alt_text' => '가을 신제품', 'link_url' => 'https://example.com/event',
        ]);

        $this->open(CreateBanner::class, $proposal)
            ->assertSet('data.title', '가을 배너')
            ->assertSet('data.group_key', 'main_top')
            ->assertSet('data.alt_text', '가을 신제품')
            ->assertSet('data.link_url', 'https://example.com/event')
            ->assertSet('data.is_active', false);

        $this->assertDatabaseCount('banners', 0);
    }

    // ───────────── 거부 규칙 ─────────────

    public function test_someone_elses_proposal_is_refused_with_a_warning_and_leaves_the_form_untouched(): void
    {
        $owner = $this->admin('owner@test.local');
        $other = $this->admin('other@test.local');
        $this->actingAs($owner);
        $proposal = $this->proposal($owner, 'propose_popup', ['title' => '내 팝업']);

        $this->actingAs($other);
        $this->open(CreatePopup::class, $proposal)
            ->assertSet('data.title', null)
            ->assertSet('data.is_active', true) // 폼 기본값 그대로 - 미리 채워지지 않았다
            ->assertSet('aiProposalId', null)
            ->assertNotified('AI 제안을 불러오지 못했습니다');

        // 남이 저장해도 제안은 사용 처리되지 않는다.
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status);
    }

    public function test_expired_wrong_kind_and_unknown_proposals_are_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $popup = $this->proposal($admin, 'propose_popup', ['title' => '팝업']);

        // 종류가 다른 화면(게시글 작성)에서 팝업 제안을 열 수 없다.
        $this->open(CreatePost::class, $popup)->assertSet('data.title', null)->assertSet('aiProposalId', null);

        // 없는 제안.
        $this->open(CreatePopup::class, 999999)->assertSet('data.title', null)->assertNotified('AI 제안을 불러오지 못했습니다');

        // 만료.
        Carbon::setTestNow(now()->addMinutes(61));
        $this->open(CreatePopup::class, $popup)->assertSet('data.title', null)->assertSet('aiProposalId', null);
    }

    public function test_without_the_query_parameter_nothing_changes_and_the_proposal_id_cannot_be_forged_from_the_browser(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $popup = $this->proposal($admin, 'propose_popup', ['title' => '팝업']);

        $page = Livewire::test(CreatePopup::class)
            ->assertSet('data.title', null)
            ->assertSet('data.is_active', true)
            ->assertSet('aiProposalId', null);

        // 브라우저에서 제안 id 를 바꿔 다른 제안을 사용 처리하게 만들 수 없다.
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $page->set('aiProposalId', $popup->id);
    }

    public function test_a_normal_save_without_a_proposal_does_not_touch_any_proposal(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $popup = $this->proposal($admin, 'propose_popup', ['title' => 'AI 팝업']);

        Livewire::test(CreatePopup::class)
            ->fillForm(['title' => '직접 만든 팝업', 'content_type' => 'html', 'html_content' => '<p>내용</p>'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Popup::count());
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $popup->fresh()->status);
    }
}
