<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\LatestInquiriesWidget;
use App\Filament\Widgets\LatestPostsWidget;
use App\Filament\Widgets\MonthlyStatsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Board;
use App\Models\Inquiry;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 2026-07-05에 실제로 있었던 버그의 회귀 테스트: Filament CheckboxList는 admin_permissions를
// 항상 단순 배열(['boards','posts'])로 저장하는데, HasPermissionCheck::canAccess()가 한때
// 연관배열({boards:true})을 기대하고 있어서 관리자 화면에서 권한을 아무리 체크해도 전부
// 403이 나던 문제가 있었다. 이 형식 불일치는 tinker로 직접 값을 넣어 테스트하면 못 잡히므로
// (연관배열을 직접 만들어 넣었기 때문), 아래 테스트는 실제 폼이 저장하는 것과 동일한 배열
// 형식으로 계정을 만들어 검증한다.
class PermissionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_unrestricted_access_regardless_of_permissions_field(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($super)->get('/admin')->assertSuccessful();
        $this->actingAs($super)->get('/admin/boards')->assertSuccessful();
        $this->actingAs($super)->get('/admin/posts')->assertSuccessful();
        $this->actingAs($super)->get('/admin/inquiries')->assertSuccessful();
        $this->actingAs($super)->get('/admin/users')->assertSuccessful();

        $this->assertTrue(StatsOverviewWidget::canView());
        $this->assertTrue(LatestPostsWidget::canView());
        $this->assertTrue(LatestInquiriesWidget::canView());
        $this->assertTrue(MonthlyStatsWidget::canView());
    }

    public function test_manager_with_checkboxlist_style_permissions_can_access_granted_resources_only(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['boards', 'posts'], // CheckboxList가 실제로 저장하는 형식
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get('/admin/boards')->assertSuccessful();
        $this->actingAs($manager)->get('/admin/posts')->assertSuccessful();
        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($manager)->get('/admin/inquiries')->assertForbidden();
    }

    public function test_manager_without_permission_does_not_see_the_nav_item_in_the_sidebar(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['boards'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get('/admin');

        $response->assertSuccessful();
        $response->assertSee('게시판 관리');
        $response->assertDontSee('회원 관리');
        $response->assertDontSee('1:1 상담');
    }

    public function test_manager_with_zero_permissions_does_not_see_the_empty_nav_group_label(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get('/admin');

        $response->assertSuccessful();
        // "콘텐츠 관리" 그룹 안의 모든 화면(게시판/게시글/페이지/배너/팝업/1:1상담/미디어)에 대한
        // 권한이 하나도 없으면, 그 하위 항목들뿐 아니라 그룹 이름(1뎁스) 자체도 사이드바에서
        // 사라져야 한다(빈 그룹 헤더만 남으면 안 됨).
        $response->assertDontSee('콘텐츠 관리');
        $response->assertDontSee('게시판 관리');
        $response->assertDontSee('회원 관리');
    }

    public function test_manager_board_and_locale_scope_restricts_boards_and_posts(): void
    {
        $enBoard = Board::create(['name' => 'EN Board', 'slug' => 'en-board', 'locale' => 'en', 'skin' => 'default', 'is_active' => true]);
        $koBoard = Board::create(['name' => 'KO Board', 'slug' => 'ko-board', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);

        $manager = User::create([
            'name' => 'EN Manager', 'email' => 'en-manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['boards', 'posts'],
            'admin_locale_scope' => ['en'],
            'admin_board_scope' => [$enBoard->id],
            'is_active' => true,
        ]);

        $enPost = Post::create(['board_id' => $enBoard->id, 'title' => 'EN post', 'content' => 'x', 'is_active' => true]);
        Post::create(['board_id' => $koBoard->id, 'title' => 'KO post', 'content' => 'x', 'is_active' => true]);

        $this->assertEqualsCanonicalizing([$enBoard->id], $manager->boardScope());
        $this->assertEqualsCanonicalizing(['en'], $manager->localeScope());
        $this->assertSame(1, Post::query()->visibleTo($manager)->count());
        $this->assertSame($enPost->id, Post::query()->visibleTo($manager)->first()->id);

        // 다른 언어의 게시판을 ID로 직접 접근해도 차단되어야 한다(목록에서만 안 보이는 게 아니라
        // 레코드 단위로도 막혀야 함). getEloquentQuery() 자체가 스코프하므로 스코프 밖 레코드는
        // "존재 안 함" 취급되어 403이 아니라 404가 난다(2026-07-05 실제 브라우저 검증과 동일).
        $this->actingAs($manager)->get("/admin/boards/{$koBoard->id}/edit")->assertNotFound();
        $this->actingAs($manager)->get("/admin/boards/{$enBoard->id}/edit")->assertSuccessful();
    }

    public function test_dashboard_widgets_hide_and_scope_by_permission(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['posts'],
            'is_active' => true,
        ]);

        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        Post::create(['board_id' => $board->id, 'title' => 'x', 'content' => 'x', 'is_active' => true]);

        Inquiry::create([
            'type' => 'general', 'locale' => 'ko', 'name' => 'A', 'email' => 'a@a.com',
            'title' => 'inq', 'content' => 'x', 'status' => 'pending', 'is_active' => true,
        ]);

        $this->actingAs($manager);

        $this->assertTrue(LatestPostsWidget::canView(), 'posts 권한이 있으면 위젯이 보여야 함');
        $this->assertFalse(LatestInquiriesWidget::canView(), 'inquiries 권한이 없으면 위젯 자체가 숨겨져야 함');
    }
}
