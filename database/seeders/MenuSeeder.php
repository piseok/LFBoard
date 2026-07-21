<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::updateOrCreate(
            ['slug' => 'notice'],
            [
                'title' => '공지사항',
                'type' => 'board',
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
                'url' => null,
                'target' => '_self',
                'min_level' => 1,
                'sort_order' => 2,
                'is_active' => true,
            ]
        );
    }
}
