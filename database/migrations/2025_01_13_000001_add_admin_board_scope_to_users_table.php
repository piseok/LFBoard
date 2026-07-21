<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 일반관리자(manager)가 담당하는 게시판 id 배열(JSON, 예: [3,7]) — 비어있으면(null)
            // "게시판관리"/"게시글관리" 권한이 있는 한 전체 게시판 접근 허용. 슈퍼관리자는 이 값과
            // 무관하게 항상 전체 접근(User::boardScope() 참고, admin_locale_scope와 동일한 패턴).
            $table->json('admin_board_scope')->nullable()->after('admin_locale_scope');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_board_scope');
        });
    }
};
