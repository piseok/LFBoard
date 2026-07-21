<?php

namespace Database\Seeders;

use App\Models\Policy;
use Illuminate\Database\Seeder;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'type' => 'terms',
                'locale' => 'ko',
                'title' => '이용약관',
                'content' => '[이용약관 내용을 입력하세요]',
                'is_required' => true,
                'is_active' => true,
                'version' => now()->format('Y.m.d'),
            ],
            [
                'type' => 'privacy',
                'locale' => 'ko',
                'title' => '개인정보처리방침',
                'content' => '[개인정보처리방침 내용을 입력하세요]',
                'is_required' => true,
                'is_active' => true,
                'version' => now()->format('Y.m.d'),
            ],
            [
                'type' => 'marketing',
                'locale' => 'ko',
                'title' => '마케팅 수신동의',
                'content' => '[마케팅 수신동의 내용을 입력하세요]',
                'is_required' => false,
                'is_active' => true,
                'version' => now()->format('Y.m.d'),
            ],
        ];

        foreach ($policies as $policy) {
            Policy::updateOrCreate(['type' => $policy['type'], 'locale' => $policy['locale']], $policy);
        }
    }
}
