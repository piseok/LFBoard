<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // RecordVisit 미들웨어가 insertOrIgnore로 동일 날짜+IP 중복 기록을 방지하려면
    // (date, ip)가 실제 unique 제약이어야 한다. 기존 index를 unique로 교체한다.
    public function up(): void
    {
        Schema::table('visit_logs', function (Blueprint $table) {
            $table->dropIndex(['date', 'ip']);
            $table->unique(['date', 'ip']);
        });
    }

    public function down(): void
    {
        Schema::table('visit_logs', function (Blueprint $table) {
            $table->dropUnique(['date', 'ip']);
            $table->index(['date', 'ip']);
        });
    }
};
