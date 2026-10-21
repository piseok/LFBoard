<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteSettings;
use App\Models\AiChatUsage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\GeminiProvider;
use App\Services\AiChatService;
use App\Services\AiUsageService;
use App\Services\SiteSettingService;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 사이트 설정 > AI 연동 탭 - 일일 한도(예전에는 없었다)·도구 호출 사용·개인정보 안내·오늘 사용량 요약.
class SiteSettingsAiTabTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_the_form_shows_the_effective_default_limits_when_nothing_is_saved_yet(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->assertFormSet([
                'ai_chat_daily_limit_per_admin' => '30',
                'ai_chat_daily_limit' => '150',
                'ai_image_daily_limit_per_admin' => '5',
                'ai_image_daily_limit' => '20',
                'ai_tools_enabled' => true,
            ])
            ->assertFormFieldExists('ai_chat_daily_limit_per_admin')
            ->assertFormFieldExists('ai_image_daily_limit')
            ->assertFormFieldExists('ai_tools_enabled');
    }

    public function test_saving_limits_and_the_tool_toggle_persists_them_in_the_ai_group_and_takes_effect(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'admin_email' => 'admin@test.local',
                'ai_chat_daily_limit_per_admin' => '7',
                'ai_chat_daily_limit' => '40',
                'ai_image_daily_limit_per_admin' => '0',
                'ai_image_daily_limit' => '9',
                'ai_tools_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach (['ai_chat_daily_limit_per_admin' => '7', 'ai_chat_daily_limit' => '40', 'ai_image_daily_limit_per_admin' => '0', 'ai_image_daily_limit' => '9', 'ai_tools_enabled' => '0'] as $key => $value) {
            $this->assertSame($value, SiteSetting::where('key', $key)->value('value'), $key);
            $this->assertSame('ai', SiteSetting::where('key', $key)->value('group'), $key);
        }

        $usage = app(AiUsageService::class);
        $this->assertSame(7, $usage->limit('chat', 'admin'));
        $this->assertSame(0, $usage->limit('image', 'admin'), '0 은 그 종류를 막는다(무제한이 아니다)');
        $this->assertFalse(app(AiChatService::class)->toolsEnabled(new GeminiProvider(app(SiteSettingService::class))));
    }

    public function test_invalid_limits_are_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['-1', 'abc', '100001', '1.5', ''] as $bad) {
            Livewire::test(SiteSettings::class)
                ->fillForm(['admin_email' => 'admin@test.local', 'ai_chat_daily_limit_per_admin' => $bad])
                ->call('save')
                ->assertHasFormErrors(['ai_chat_daily_limit_per_admin']);
        }

        $this->assertNull(SiteSetting::where('key', 'ai_chat_daily_limit_per_admin')->value('value'));
    }

    public function test_the_tab_explains_privacy_and_shows_todays_usage_by_admin(): void
    {
        $admin = $this->superAdmin();
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'image', 'created_at' => now()]);
        $this->actingAs($admin);

        Livewire::test(SiteSettings::class)
            ->assertSee('외부 AI 제공자')
            ->assertSee('개인정보')
            ->assertSee('제공자 쪽 크레딧 잔액은 조회 수단이 없어 보여 주지 않으며')
            ->assertSee('오늘 사용량')
            ->assertSee('우리 사이트의 일일 한도 기준')
            ->assertSeeInOrder(['Super', '1', '1']);
    }

    public function test_the_seeder_provides_conservative_defaults_without_touching_api_keys(): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-keep-me', 'ai');

        $this->seed(SiteSettingSeeder::class);

        $this->assertSame('30', SiteSetting::where('key', 'ai_chat_daily_limit_per_admin')->value('value'));
        $this->assertSame('5', SiteSetting::where('key', 'ai_image_daily_limit_per_admin')->value('value'));
        $this->assertSame('1', SiteSetting::where('key', 'ai_tools_enabled')->value('value'));
        $this->assertSame('gm-keep-me', app(SiteSettingService::class)->get('ai_gemini_api_key'), '재시딩이 API 키를 지우면 안 된다');
        $this->assertNull(SiteSetting::where('key', 'ai_openai_api_key')->value('value'));
    }
}
