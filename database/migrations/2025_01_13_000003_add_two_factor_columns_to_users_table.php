<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Filament 4가 기본 내장한 "앱 인증"(TOTP, Google/MS Authenticator 등) 2FA 기능을 위한 컬럼.
// QR코드 생성/검증/복구코드 로직은 전부 Filament 코어(pragmarx/google2fa-qrcode, 이미 설치돼 있어
// 별도 composer 패키지 추가가 필요 없음)가 처리하고, 이 앱은 User 모델에 저장 방식만 정의한다
// (App\Models\User::getAppAuthenticationSecret() 등, HasAppAuthentication 컨트랙트 참고).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('admin_board_scope');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes']);
        });
    }
};
