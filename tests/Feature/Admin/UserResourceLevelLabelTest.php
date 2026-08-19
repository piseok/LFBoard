<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// levelLabel()이 새 등급(정회원)을 "비회원"으로 잘못 표시하던 버그의 회귀 테스트.
class UserResourceLevelLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_label_covers_every_level(): void
    {
        $guest = User::create(['name' => 'G', 'email' => 'g@test.local', 'password' => bcrypt('x'), 'level' => User::LEVEL_GUEST, 'is_active' => true]);
        $member = User::create(['name' => 'M', 'email' => 'm@test.local', 'password' => bcrypt('x'), 'level' => User::LEVEL_MEMBER, 'is_active' => true]);
        $verified = User::create(['name' => 'V', 'email' => 'v@test.local', 'password' => bcrypt('x'), 'level' => User::LEVEL_VERIFIED, 'is_active' => true]);
        $manager = User::create(['name' => 'MA', 'email' => 'ma@test.local', 'password' => bcrypt('x'), 'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'is_active' => true]);
        $super = User::create(['name' => 'S', 'email' => 's@test.local', 'password' => bcrypt('x'), 'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true]);

        $this->assertSame('비회원', UserResource::levelLabel($guest));
        $this->assertSame('일반회원', UserResource::levelLabel($member));
        $this->assertSame('정회원', UserResource::levelLabel($verified));
        $this->assertSame('일반관리자', UserResource::levelLabel($manager));
        $this->assertSame('슈퍼관리자', UserResource::levelLabel($super));
    }
}
