<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::updateOrCreate(
            ['slug' => 'about', 'locale' => 'ko'],
            [
                'title' => '회사소개',
                'content_type' => 'editor',
                'content' => '[회사소개 내용을 입력하세요]',
                'is_active' => true,
                'min_level' => 1,
                'sort_order' => 1,
            ]
        );
    }
}
