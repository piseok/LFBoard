<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // target_id가 비어 있으면 MenuService::resolveUrl()이 게시판/페이지를 못 찾아 '#'로만
        // 연결된다 — PageSeeder가 이 시더보다 먼저 실행되고(DatabaseSeeder 순서), 게시판은
        // 2026_07_22_000001_seed_home_page_boards.php 마이그레이션이 db:seed보다 항상 먼저
        // 실행되므로 이 시점엔 둘 다 이미 존재한다.
        $noticeBoard = Board::where('slug', 'notice')->where('locale', 'ko')->first();
        $aboutPage = Page::where('slug', 'about')->where('locale', 'ko')->first();

        Menu::updateOrCreate(
            ['slug' => 'notice'],
            [
                'title' => '공지사항',
                'type' => 'board',
                'target_id' => $noticeBoard?->id,
                'url' => null,
                'target' => '_self',
                'min_level' => 1,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        Menu::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => '회사소개',
                'type' => 'page',
                'target_id' => $aboutPage?->id,
                'url' => null,
                'target' => '_self',
                'min_level' => 1,
                'sort_order' => 2,
                'is_active' => true,
            ]
        );
    }
}
