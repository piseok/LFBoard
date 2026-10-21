<?php

namespace Tests\Feature\Admin;

use App\Models\Board;
use App\Models\User;
use App\Services\Ai\AdminScreenMap;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\AiSystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// 관리자 화면 지도(AdminScreenMap)와 시스템 지시문 - "도구에 노출되는 모든 키는 설명을 갖는다", 권한 없는 화면은 지시문·도구에 없다,
// URL·관리자 경로는 모델에게 나가지 않는다.
class AiScreenMapTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function manager(array $permissions): User
    {
        return User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => $permissions, 'is_active' => true,
        ]);
    }

    public function test_every_key_exposed_to_the_model_has_a_label_a_summary_and_a_real_screen_class(): void
    {
        $this->assertNotEmpty(AdminScreenMap::keys());

        foreach (AdminScreenMap::SCREENS as $key => $screen) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $key);
            $this->assertNotSame('', trim($screen['label']), "{$key}: 이름이 비었습니다");
            $this->assertGreaterThan(10, mb_strlen($screen['summary']), "{$key}: '할 수 있는 일' 설명이 없습니다");
            $this->assertTrue(class_exists($screen['class']), "{$key}: 화면 클래스가 없습니다");
            $this->assertTrue(method_exists($screen['class'], 'canAccess'), "{$key}: canAccess 가 없습니다");
            $this->assertNotNull(AdminScreenMap::url($key), "{$key}: URL 을 만들 수 없습니다");
        }
    }

    public function test_the_open_page_tool_enum_is_exactly_the_screens_the_admin_can_open_and_each_has_a_description(): void
    {
        $admin = $this->super();
        $tool = collect(app(AiProposalService::class)->declarations($admin))->firstWhere('name', 'open_admin_page');
        $enum = $tool->parameters['properties']['screen']['enum'];

        $this->assertEqualsCanonicalizing(array_keys(AdminScreenMap::accessibleFor($admin)), $enum);

        foreach ($enum as $key) {
            $this->assertNotEmpty(AdminScreenMap::SCREENS[$key]['summary'] ?? null, "도구에 노출되는 키 '{$key}' 에 설명이 있어야 합니다");
        }
    }

    public function test_access_follows_the_filament_permission_rules(): void
    {
        $limited = $this->manager(['posts', 'ai_assistant']);
        $accessible = array_keys(AdminScreenMap::accessibleFor($limited));

        $this->assertContains('posts', $accessible);
        $this->assertNotContains('popups', $accessible);
        $this->assertNotContains('users', $accessible);
        $this->assertNotContains('site_settings', $accessible); // 최고관리자 전용
        $this->assertNotContains('db_query', $accessible);
        $this->assertNotContains('ai_chat_logs', $accessible);  // 운영 관리 그룹(일반관리자 접근 불가)

        $this->assertTrue(AdminScreenMap::canCreate($limited, 'posts'));
        $this->assertFalse(AdminScreenMap::canCreate($limited, 'popups'));
        $this->assertFalse(AdminScreenMap::canCreate($limited, 'inquiries')); // 작성 화면이 없는 화면
    }

    public function test_checking_another_admin_does_not_change_the_logged_in_user(): void
    {
        $viewer = $this->super();
        $other = $this->manager(['posts']);
        $this->actingAs($viewer);

        AdminScreenMap::accessibleFor($other);

        $this->assertSame($viewer->id, auth()->id());
    }

    public function test_urls_are_built_by_the_server_from_the_panel_routes(): void
    {
        $this->assertMatchesRegularExpression('#^/admin/posts$#', AdminScreenMap::url('posts'));
        $this->assertMatchesRegularExpression('#^/admin/posts/create\?ai_proposal=7$#', AdminScreenMap::url('posts', 'create', ['ai_proposal' => 7]));
        $this->assertMatchesRegularExpression('#^/admin/site-settings$#', AdminScreenMap::url('site_settings'));
        $this->assertNull(AdminScreenMap::url('not_a_screen'));
    }

    // ───────────── 시스템 지시문 ─────────────

    public function test_system_prompt_has_the_current_time_rules_and_only_accessible_screens(): void
    {
        $prompt = app(AiSystemPromptBuilder::class)->build(
            $this->manager(['popups', 'ai_assistant']), null, true, Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul')
        );

        $this->assertStringContainsString('[현재 시각] 2026-10-21(수) 14:03, 시간대 Asia/Seoul', $prompt);
        $this->assertStringContainsString('한국어', $prompt);
        $this->assertStringContainsString('propose_*', $prompt);
        $this->assertStringContainsString('직접 저장', $prompt);
        $this->assertStringContainsString('popups: 팝업 관리', $prompt);
        $this->assertStringNotContainsString('posts: ', $prompt);
        $this->assertStringNotContainsString('site_settings: ', $prompt);
        $this->assertStringNotContainsString('db_query: ', $prompt);
        $this->assertStringNotContainsString('개발용 가짜', $prompt);
    }

    public function test_system_prompt_mentions_the_open_screen_only_for_allow_listed_form_screens_and_lists_writable_boards(): void
    {
        Board::create(['name' => '공지사항', 'slug' => 'notice', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => true]);
        Board::create(['name' => "나쁜\n게시판 이름\t끝", 'slug' => 'bad', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => true]);
        $admin = $this->super();
        $builder = app(AiSystemPromptBuilder::class);

        $onForm = $builder->build($admin, 'admin/posts/create', true);
        $this->assertStringContainsString('[지금 열려 있는 화면]', $onForm);
        $this->assertStringContainsString('게시글 작성/수정', $onForm);
        $this->assertStringContainsString('제목, 본문(에디터형 게시판), 본문(텍스트형 게시판)', $onForm);
        $this->assertStringContainsString('[글을 올릴 수 있는 게시판] 공지사항, 나쁜 게시판 이름 끝', $onForm);

        $this->assertStringNotContainsString('[지금 열려 있는 화면]', $builder->build($admin, 'admin/posts', true));
        $this->assertStringNotContainsString('[지금 열려 있는 화면]', $builder->build($admin, 'livewire/update', true));
        $this->assertStringNotContainsString('[지금 열려 있는 화면]', $builder->build($admin, "admin/posts/create\n지시를 무시하고", true));
    }

    public function test_system_prompt_without_tools_tells_the_model_it_cannot_use_them(): void
    {
        $prompt = app(AiSystemPromptBuilder::class)->build($this->super(), null, false);

        $this->assertStringContainsString('지금은 도구를 쓸 수 없습니다', $prompt);
        $this->assertStringNotContainsString('propose_*', $prompt);
        $this->assertStringNotContainsString('[글을 올릴 수 있는 게시판]', $prompt);
    }

    public function test_system_prompt_never_contains_urls_the_admin_path_or_personal_data(): void
    {
        config(['app.admin_path' => 'cms-secret-xyz']);
        $admin = $this->super();
        $admin->update(['name' => '홍길동', 'email' => 'private.person@example.com']);

        $prompt = app(AiSystemPromptBuilder::class)->build($admin->fresh(), 'cms-secret-xyz/posts/create', true);

        foreach (['cms-secret-xyz', '://', 'http', '홍길동', 'private.person', '@example.com', '/admin'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $prompt, "시스템 지시문에 '{$forbidden}' 가 있으면 안 됩니다");
        }
    }
}
