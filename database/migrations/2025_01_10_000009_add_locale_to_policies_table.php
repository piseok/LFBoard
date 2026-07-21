<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->string('locale', 5)->default('ko')->after('type');
        });

        DB::table('policies')->update(['locale' => 'ko']);

        Schema::table('policies', function (Blueprint $table) {
            $table->unique(['type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropUnique(['type', 'locale']);
            $table->dropColumn('locale');
            $table->unique(['type']);
        });
    }
};
