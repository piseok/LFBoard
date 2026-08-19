<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 사이트 설정 > 테마 탭 — 브랜드 컬러 3키(theme_color_brand_primary/-dark/-accent)를 관리한다.
// 인라인 <style>(layouts/app.blade.php)로 그대로 출력되므로, 저장 시점에 "#rrggbb" 형식만
// 허용하는 정규식 검증이 CSS 인젝션(세미콜론/중괄호로 브레이크아웃)을 막는 유일한 방어선이다.
class SiteSettingsThemeTabTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_saving_valid_hex_colors_persists_them_under_the_theme_group(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'admin_email' => 'admin@test.local',
                'theme_color_brand_primary' => '#f58220',
                'theme_color_brand_primary_dark' => '#c96a15',
                'theme_color_brand_accent' => '#2563eb',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('#f58220', SiteSetting::where('key', 'theme_color_brand_primary')->value('value'));
        $this->assertSame('theme', SiteSetting::where('key', 'theme_color_brand_primary')->value('group'));
        $this->assertSame('#c96a15', SiteSetting::where('key', 'theme_color_brand_primary_dark')->value('value'));
        $this->assertSame('theme', SiteSetting::where('key', 'theme_color_brand_primary_dark')->value('group'));
        $this->assertSame('#2563eb', SiteSetting::where('key', 'theme_color_brand_accent')->value('value'));
        $this->assertSame('theme', SiteSetting::where('key', 'theme_color_brand_accent')->value('group'));
    }

    public function test_saving_an_invalid_hex_color_fails_validation_and_does_not_persist(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'admin_email' => 'admin@test.local',
                'theme_color_brand_primary' => 'red; } body { display:none',
            ])
            ->call('save')
            ->assertHasFormErrors(['theme_color_brand_primary']);

        $this->assertNull(SiteSetting::where('key', 'theme_color_brand_primary')->value('value'));
    }
}
