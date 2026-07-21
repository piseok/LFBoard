<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            // level: 1=비회원, 2=일반회원, 9=관리자
            $table->tinyInteger('level')->default(1)->after('password');
            $table->string('phone', 20)->nullable()->after('level');
            $table->string('nickname', 50)->nullable()->after('phone');
            // gender: male, female
            $table->string('gender', 10)->nullable()->after('nickname');
            $table->date('birthdate')->nullable()->after('gender');
            $table->string('homepage')->nullable()->after('birthdate');
            $table->string('address')->nullable()->after('homepage');
            $table->text('memo')->nullable()->after('address');
            $table->boolean('is_active')->default(true)->after('memo');
            $table->boolean('marketing_agreed')->default(false)->after('is_active');
            $table->timestamp('marketing_agreed_at')->nullable()->after('marketing_agreed');
            $table->string('unsubscribe_token', 32)->nullable()->unique()->after('marketing_agreed_at');
            $table->timestamp('last_login_at')->nullable()->after('unsubscribe_token');
            $table->string('ci')->nullable()->after('last_login_at');
            $table->string('di')->nullable()->after('ci');
            $table->timestamp('phone_verified_at')->nullable()->after('di');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'username', 'level', 'phone', 'nickname', 'gender', 'birthdate',
                'homepage', 'address', 'memo', 'is_active', 'marketing_agreed',
                'marketing_agreed_at', 'unsubscribe_token', 'last_login_at',
                'ci', 'di', 'phone_verified_at',
            ]);
        });
    }
};
