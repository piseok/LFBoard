<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            // hidden: 레벨 미달 시 메뉴 자체를 숨김(기존 기본 동작), locked: 메뉴는 보이되 잠금 표시(대상 콘텐츠의 자체 접근 제어에 따라 실제 접근 여부가 결정됨)
            $table->string('access_mode', 10)->default('hidden')->after('min_level');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('access_mode');
        });
    }
};
