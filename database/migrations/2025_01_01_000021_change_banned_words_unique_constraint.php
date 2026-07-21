<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 동일 단어를 type별로 중복 등록할 수 있어야 하므로(예: '관리자'를 username, nickname
    // 양쪽에 등록) word 단독 unique 대신 (word, type) 복합 unique로 변경한다.
    public function up(): void
    {
        Schema::table('banned_words', function (Blueprint $table) {
            $table->dropUnique(['word']);
            $table->unique(['word', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('banned_words', function (Blueprint $table) {
            $table->dropUnique(['word', 'type']);
            $table->unique(['word']);
        });
    }
};
