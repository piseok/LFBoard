<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 일반 최고관리자(client)는 벤더(슈퍼관리자)가 클라이언트에게 전달하는 계정 — 일반관리자(manager)
// 계정을 만들고 권한을 부여할 수 있지만, 슈퍼관리자나 다른 일반 최고관리자 권한은 절대 부여할 수
// 없어야 한다("최고관리자 권한은 못 주고 일반관리자 권한만 줄 수 있는" 계정). Select의 options()는
// 화면 표시일 뿐이라 폼 조작으로 우회되지 않는지(서버 측 검증)도 함께 확인한다.
class ClientAdminTierTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function clientAdmin(string $email = 'client@test.local'): User
    {
        return User::create([
            'name' => 'Client', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'client', 'is_active' => true,
        ]);
    }

    private function manager(string $email = 'manager@test.local'): User
    {
        return User::create([
            'name' => 'Manager', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'is_active' => true,
        ]);
    }

    public function test_client_admin_can_create_a_manager_account(): void
    {
        $client = $this->clientAdmin();
        $this->actingAs($client);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '새 매니저', 'email' => 'new-manager@test.local', 'password' => 'password123',
                'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => ['posts'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'new-manager@test.local', 'admin_role' => 'manager']);
    }

    public function test_client_admin_cannot_create_a_super_admin_account_even_via_forged_form_data(): void
    {
        $client = $this->clientAdmin();
        $this->actingAs($client);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '탈취시도', 'email' => 'escalate@test.local', 'password' => 'password123',
                'level' => User::LEVEL_ADMIN, 'admin_role' => 'super',
            ])
            ->call('create')
            ->assertHasFormErrors(['admin_role']);

        $this->assertDatabaseMissing('users', ['email' => 'escalate@test.local']);
    }

    public function test_client_admin_cannot_create_another_client_admin_account(): void
    {
        $client = $this->clientAdmin();
        $this->actingAs($client);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '탈취시도2', 'email' => 'escalate2@test.local', 'password' => 'password123',
                'level' => User::LEVEL_ADMIN, 'admin_role' => 'client',
            ])
            ->call('create')
            ->assertHasFormErrors(['admin_role']);

        $this->assertDatabaseMissing('users', ['email' => 'escalate2@test.local']);
    }

    public function test_manager_cannot_access_create_user_page_at_all(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get(UserResource::getUrl('create'))->assertForbidden();
    }

    public function test_client_admin_can_edit_a_manager_account_but_not_a_super_admin_account(): void
    {
        $client = $this->clientAdmin();
        $manager = $this->manager();
        $super = $this->superAdmin('other-super@test.local');

        $this->actingAs($client)->get(UserResource::getUrl('edit', ['record' => $manager]))->assertSuccessful();
        $this->actingAs($client)->get(UserResource::getUrl('edit', ['record' => $super]))->assertForbidden();
    }

    public function test_client_admin_cannot_edit_another_client_admin_account(): void
    {
        $client = $this->clientAdmin();
        $otherClient = $this->clientAdmin('other-client@test.local');

        $this->actingAs($client)->get(UserResource::getUrl('edit', ['record' => $otherClient]))->assertForbidden();
    }

    public function test_client_admin_cannot_bulk_deactivate_a_super_admin_account(): void
    {
        $client = $this->clientAdmin();
        $super = $this->superAdmin('other-super@test.local');

        $this->actingAs($client);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('deactivate', [$super]);

        $super->refresh();
        $this->assertTrue($super->is_active);
    }

    // 2FA는 본인 인증 앱으로만 켤 수 있어 관리자가 대신 켜줄 수 없다 — "초기화(끄기)"만 가능하며,
    // canEdit() 범위(일반 최고관리자는 manager까지, 본인 계정 제외)를 그대로 재사용한다.
    public function test_client_admin_can_reset_a_managers_two_factor_auth(): void
    {
        $client = $this->clientAdmin();
        $manager = $this->manager();
        $manager->saveAppAuthenticationSecret('secret');
        $manager->saveAppAuthenticationRecoveryCodes(['code1', 'code2']);

        $this->actingAs($client);

        Livewire::test(ListUsers::class)
            ->callTableAction('resetTwoFactor', $manager);

        $manager->refresh();
        $this->assertNull($manager->getAppAuthenticationSecret());
        $this->assertNull($manager->getAppAuthenticationRecoveryCodes());
    }

    public function test_client_admin_cannot_reset_a_super_admins_two_factor_auth(): void
    {
        $client = $this->clientAdmin();
        $super = $this->superAdmin('other-super@test.local');
        $super->saveAppAuthenticationSecret('secret');

        $this->actingAs($client);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetTwoFactor', $super);

        $super->refresh();
        $this->assertNotNull($super->getAppAuthenticationSecret());
    }

    public function test_reset_two_factor_action_is_hidden_for_own_account_and_when_not_enabled(): void
    {
        $client = $this->clientAdmin();
        $manager = $this->manager(); // 2FA 미설정 상태

        $this->actingAs($client);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetTwoFactor', $client)
            ->assertTableActionHidden('resetTwoFactor', $manager);
    }
}
