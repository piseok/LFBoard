<?php

namespace Database\Seeders;

use App\Models\BannedWord;
use Illuminate\Database\Seeder;

class BannedWordSeeder extends Seeder
{
    public function run(): void
    {
        // 아이디/닉네임으로 도용되면 안 되는 예약어(운영진 사칭 방지) — 아이디/닉네임 둘 다 막아야 사칭을 막을 수 있어 'all'로 등록.
        $reservedWords = [
            'admin', 'administrator', 'root', 'system', 'master', 'superuser', 'moderator',
            'staff', 'support', 'null', 'undefined', 'guest', 'test',
            '관리자', '운영자', '최고관리자', '시스템', '마스터', '고객센터', '게스트', '테스트',
        ];

        // 아이디/닉네임 둘 다 막아야 하는 비속어. 부분 문자열 일치라 과도하게 넓히지 않고
        // 널리 알려진 것 위주로만 두고, 필요하면 관리자 화면(금칙어 관리)에서 직접 추가하면 된다.
        $profanityWords = [
            '씨발', '시발', '병신', '개새끼', '지랄', '좆', '섹스',
            'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'cunt',
        ];

        foreach ([...$reservedWords, ...$profanityWords] as $word) {
            BannedWord::updateOrCreate(['word' => $word, 'type' => 'all']);
        }
    }
}
