<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\MarketingMail;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMailTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_navigation_is_hidden_until_smtp_is_configured_in_site_settings(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertFalse(MarketingMail::shouldRegisterNavigation());
        $this->assertTrue(MarketingMail::canAccess());

        app(SiteSettingService::class)->set('mail_host', 'smtp.gmail.com', 'mail');

        $this->assertTrue(MarketingMail::shouldRegisterNavigation());
    }
}
