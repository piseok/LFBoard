<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            // 둘 다 비어있으면 상시 모집(기간 제한 없음). 값이 있으면 그 기간 밖 글쓰기를 막는다.
            $table->timestamp('recruitment_start_at')->nullable()->after('identity_verification_consent_text');
            $table->timestamp('recruitment_end_at')->nullable()->after('recruitment_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn(['recruitment_start_at', 'recruitment_end_at']);
        });
    }
};
