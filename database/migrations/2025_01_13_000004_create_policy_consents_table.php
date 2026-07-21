<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 회원이 실제로 동의한 약관 버전을 기록한다(타입별로 매번 새 행 — 이력을 남겨 나중에
// "언제 어떤 버전에 동의했는지" 분쟁이 생겨도 확인 가능하게 함, 감사로그와 같은 append-only 철학).
// 약관 버전이 바뀌었는데 회원의 최신 동의 기록이 그 버전과 다르면 재동의를 요구한다
// (User::outdatedRequiredPolicyTypes() 참고).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('locale', 5);
            $table->string('version', 50)->nullable();
            $table->timestamp('agreed_at');

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_consents');
    }
};
