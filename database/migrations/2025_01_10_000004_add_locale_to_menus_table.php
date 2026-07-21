<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('locale', 5)->default('ko')->after('title');
        });

        // 기존 메뉴는 전부 한국어로 만들어진 것이므로 명시적으로 backfill.
        DB::table('menus')->update(['locale' => 'ko']);
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
