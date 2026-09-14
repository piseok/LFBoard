<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('banner_group_key', 50)->nullable()->after('sort_order')
                ->comment('연동할 배너 그룹 키(banners.group_key) — 비어있으면 배너 미표시');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('banner_group_key');
        });
    }
};
