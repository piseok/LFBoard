<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            // 활성화 시 글쓰기 전 본인인증(User.ci/di) + 아래 동의 문구 체크를 요구한다.
            $table->boolean('requires_identity_verification')->default(false)->after('use_captcha');
            // 게시판마다 실제로 수집/이용하는 개인정보 항목이 달라 문구를 게시판별로 직접 입력한다.
            $table->text('identity_verification_consent_text')->nullable()->after('requires_identity_verification');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn(['requires_identity_verification', 'identity_verification_consent_text']);
        });
    }
};
