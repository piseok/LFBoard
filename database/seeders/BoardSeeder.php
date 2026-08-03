<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        Board::updateOrCreate(
            ['slug' => 'notice', 'locale' => 'ko'],
            [
                'name' => '공지사항', 'skin' => 'default', 'layout' => 'list', 'use_editor' => true,
                'allow_comment' => true, 'allow_reply' => true, 'allow_file' => true, 'allow_anonymous' => false,
                'allow_image_upload' => true, 'use_captcha' => false, 'min_read_level' => 1, 'min_write_level' => 9,
                'min_comment_level' => 2, 'files_per_post' => 3, 'per_page' => 15, 'order_by' => 'latest',
                'description' => '서비스 관련 주요 소식을 안내합니다.', 'is_active' => true, 'sort_order' => 1,
            ]
        );
    }
}
