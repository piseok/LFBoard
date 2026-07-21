<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'ko', 'name' => '한국어', 'is_default' => true, 'is_active' => true, 'sort_order' => 0],
            ['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_active' => true, 'sort_order' => 1],
            ['code' => 'ja', 'name' => '日本語', 'is_default' => false, 'is_active' => true, 'sort_order' => 2],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
