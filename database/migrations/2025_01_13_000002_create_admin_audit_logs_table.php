<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            // 관리자 계정이 나중에 탈퇴/삭제돼도 로그 자체(누가 했었는지 이름은 changes와 별개로
            // admin_name에 스냅샷)는 남아야 감사 목적에 맞으므로 SET NULL(관계는 끊기되 행은 유지).
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name', 50)->nullable();
            $table->string('action', 20); // created / updated / deleted
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('auditable_label')->nullable(); // 목록에서 바로 알아보기 위한 스냅샷(제목/이름 등)
            $table->json('before')->nullable(); // updated/deleted 시점의 변경 전 값
            $table->json('changes')->nullable(); // created/updated 시점의 실제 반영된 값
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
