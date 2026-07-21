<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('dormant_at')->nullable()->after('last_login_at');
            $table->timestamp('dormant_notice_sent_at')->nullable()->after('dormant_at');
            $table->timestamp('withdrawal_notice_sent_at')->nullable()->after('dormant_notice_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['dormant_at', 'dormant_notice_sent_at', 'withdrawal_notice_sent_at']);
        });
    }
};
