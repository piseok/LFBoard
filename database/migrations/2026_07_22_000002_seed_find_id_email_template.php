<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // "아이디 찾기" 기능에 필요한 메일 템플릿을 미리 만들어둔다 — firstOrCreate라 관리자가 이미
    // 손댔으면 건드리지 않는다(email_templates 관리 화면에서 자유롭게 수정 가능).
    public function up(): void
    {
        EmailTemplate::firstOrCreate(
            ['type' => 'find_id', 'locale' => 'ko'],
            [
                'name' => '아이디 찾기',
                'subject' => '[{{site_name}}] 아이디 찾기 안내',
                'body' => <<<'HTML'
                <p>안녕하세요, {{user_name}}님.</p>
                <p>요청하신 로그인 아이디는 다음과 같습니다.</p>
                <p><strong>{{user_id}}</strong></p>
                HTML,
            ]
        );

        EmailTemplate::firstOrCreate(
            ['type' => 'find_id', 'locale' => 'en'],
            [
                'name' => 'Find ID',
                'subject' => '[{{site_name}}] Your login ID',
                'body' => <<<'HTML'
                <p>Hello, {{user_name}}.</p>
                <p>Your login ID is:</p>
                <p><strong>{{user_id}}</strong></p>
                HTML,
            ]
        );
    }

    public function down(): void
    {
        EmailTemplate::where('type', 'find_id')->delete();
    }
};
