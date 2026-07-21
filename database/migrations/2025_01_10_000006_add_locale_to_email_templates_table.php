<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('locale', 5)->default('ko')->after('type');
        });

        DB::table('email_templates')->update(['locale' => 'ko']);

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->unique(['type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['type', 'locale']);
            $table->unique(['type']);
            $table->dropColumn('locale');
        });
    }
};
