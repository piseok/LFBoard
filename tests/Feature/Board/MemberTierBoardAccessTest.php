<?php

namespace Tests\Feature\Board;

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 게시판의 min_read_level은 부등호 비교(userLevel < minLevel)라 새 회원 등급(정회원 등)을
// 코드 변경 없이 그대로 지원해야 한다 — 이 테스트가 그 전제를 검증한다.
class MemberTierBoardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_restricted_to_verified_tier_blocks_general_member(): void
    {
        $board = Board::create([
            'name' => '정회원 전용', 'slug' => 'verified-only', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'min_read_level' => User::LEVEL_VERIFIED,
        ]);

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($member)->get("/board/{$board->slug}")->assertRedirect(route('login'));
    }

    public function test_board_restricted_to_verified_tier_allows_verified_member(): void
    {
        $board = Board::create([
            'name' => '정회원 전용', 'slug' => 'verified-only', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'min_read_level' => User::LEVEL_VERIFIED,
        ]);

        $verified = User::create([
            'name' => 'Verified', 'email' => 'verified@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_VERIFIED, 'is_active' => true,
        ]);

        $this->actingAs($verified)->get("/board/{$board->slug}")->assertSuccessful();
    }

    public function test_board_write_restricted_to_verified_tier_blocks_general_member(): void
    {
        $board = Board::create([
            'name' => '정회원 글쓰기', 'slug' => 'verified-write', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'min_write_level' => User::LEVEL_VERIFIED,
        ]);

        $member = User::create([
            'name' => 'Member', 'email' => 'member2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($member)->get("/board/{$board->slug}/write")->assertForbidden();
    }

    public function test_board_write_restricted_to_verified_tier_allows_verified_member(): void
    {
        $board = Board::create([
            'name' => '정회원 글쓰기', 'slug' => 'verified-write', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'min_write_level' => User::LEVEL_VERIFIED,
        ]);

        $verified = User::create([
            'name' => 'Verified', 'email' => 'verified2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_VERIFIED, 'is_active' => true,
        ]);

        $this->actingAs($verified)->get("/board/{$board->slug}/write")->assertSuccessful();
    }
}
