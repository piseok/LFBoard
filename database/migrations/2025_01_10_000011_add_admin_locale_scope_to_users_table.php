<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 일반관리자(manager)가 담당하는 언어 코드 배열(JSON, 예: ["en","ja"]) — 비어있으면(null)
            // 전체 언어 접근 허용. 슈퍼관리자는 이 값과 무관하게 항상 전체 접근(User::localeScope() 참고).
            $table->json('admin_locale_scope')->nullable()->after('admin_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_locale_scope');
        });
    }
};
