<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('locale', 5)->default('ko')->after('slug');
        });

        // 기존 페이지는 전부 한국어로 작성된 것이므로 명시적으로 backfill.
        DB::table('pages')->update(['locale' => 'ko']);

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->unique(['slug']);
            $table->dropColumn('locale');
        });
    }
};
