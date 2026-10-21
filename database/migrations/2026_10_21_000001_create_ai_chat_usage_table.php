<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// AI 비서 일일 한도 계산용 사용 기록(2026-10-21, AI 비서 2.0).
// 예전에는 한도 자체가 없었다. 대화(ai_chat_conversations/messages)와 따로 두는 이유:
//  - 대화를 삭제해도 오늘 사용 횟수가 되살아나면 안 된다(삭제로 한도를 초기화하는 우회 방지).
//  - 제공자를 부르기 "직전에" 먼저 한 줄 적고, 오늘 건수를 센 뒤 초과면 지운다(동시 요청 경쟁 방어).
// 한 줄 = 제공자 호출 1회(성공/실패 무관 - 실패해도 제공자 쪽에서 과금될 수 있어 보수적으로 센다).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_usage', function (Blueprint $table) {
            $table->id()->comment('기본 키');
            $table->foreignId('user_id')->nullable()->comment('호출한 관리자(users.id) - 관리자 삭제 시 null, 사이트 전체 합계에는 계속 포함')
                ->constrained('users')->nullOnDelete();
            $table->string('provider', 20)->comment('AI 제공자(openai/gemini/fake)');
            $table->string('kind', 10)->comment('호출 종류(chat=질문, image=이미지 생성)');
            $table->timestamp('created_at')->comment('호출 시각(앱 시간대) - "오늘" 건수 계산 기준');

            $table->index(['kind', 'created_at'], 'ai_chat_usage_kind_created_idx');
            $table->index(['user_id', 'kind', 'created_at'], 'ai_chat_usage_user_kind_created_idx');
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'ai_chat_usage` COMMENT = '.DB::getPdo()->quote('AI 비서 일일 한도 계산용 호출 기록(대화 삭제와 무관하게 유지)'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_usage');
    }
};
