<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin_role: super(슈퍼관리자), manager(일반관리자)
            $table->string('admin_role')->nullable()->after('level');
            $table->json('admin_permissions')->nullable()->after('admin_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_role', 'admin_permissions']);
        });
    }
};
