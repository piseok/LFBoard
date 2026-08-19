<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SystemUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// SSH 없는 호스팅에서 FTP로만 배포하다 보니, Filament 패키지 버전이 바뀌면 컴파일된 CSS/JS가
// 새 마크업과 안 맞아 모달이 깨지는 문제가 실제로 있었다(관리자 프로필 2FA 설정 모달이
// position:static/height:0으로 렌더되어 오버레이가 아니라 페이지 안에 끼어들어가는 현상 —
// php artisan filament:assets 재실행으로 해결됨). SSH 없이도 웹에서 이 복구를 할 수 있어야 한다.
class SystemUpdateCacheClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_run_clear_cache_and_assets_action(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        Livewire::actingAs($super)->test(SystemUpdate::class)
            ->call('clearCacheAndAssets')
            ->assertHasNoErrors();
    }

    public function test_super_admin_can_run_fix_legacy_upload_paths_action(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        Livewire::actingAs($super)->test(SystemUpdate::class)
            ->call('fixLegacyUploadPaths')
            ->assertHasNoErrors();
    }

    public function test_super_admin_can_run_check_upload_environment_action(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super3@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $component = Livewire::actingAs($super)->test(SystemUpdate::class)
            ->call('checkUploadEnvironment')
            ->assertHasNoErrors();

        $this->assertStringContainsString('base_path()', $component->get('lastOutput'));
        $this->assertStringContainsString('upload_max_filesize', $component->get('lastOutput'));
    }

    // 마이그레이션이 하나도 안 밀려 있는 게 대부분이라, 그 상태에서까지 "대기 중인 마이그레이션"
    // 섹션을 항상 보여주면 캐시 정리/업로드 환경 점검 등 무관한 버튼을 눌렀을 때도 매번 같이
    // 떠서 헷갈렸다 — 밀린 게 없으면 아예 섹션 자체가 안 보여야 한다.
    public function test_pending_migrations_section_is_hidden_when_nothing_is_pending(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super4@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        Livewire::actingAs($super)->test(SystemUpdate::class)
            ->assertDontSee('대기 중인 마이그레이션');
    }

    public function test_manager_cannot_access_system_update_page(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'is_active' => true,
        ]);

        $this->actingAs($manager)->get(SystemUpdate::getUrl())->assertForbidden();
    }
}
