<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            // 사전고지(예약 변경): 시행 예정일 전까지는 기존 title/content/version 그대로 노출하고
            // 배너로만 예고하다가, 시행일이 되면 아래 pending_* 값을 실제 컬럼으로 옮겨 적용한다.
            $table->string('pending_version')->nullable()->after('version');
            $table->string('pending_title')->nullable()->after('pending_version');
            $table->longText('pending_content')->nullable()->after('pending_title');
            $table->timestamp('effective_at')->nullable()->after('pending_content');
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn(['pending_version', 'pending_title', 'pending_content', 'effective_at']);
        });
    }
};
