<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 관리자 접속 로그와 관리자 활동 감사로그를 하나로 합친다(아직 배포된 곳이 없어 별도 테이블을
// 만들었다 지우는 대신, 그 테이블을 만들던 마이그레이션 자체를 제거하고 admin_audit_logs에
// 필요한 컬럼만 추가한다). 접속 기록은 action='access' 행으로 admin_audit_logs에 쌓이고,
// auditable_type/auditable_id는 NOT NULL이라 접속 기록도 "그 관리자 본인(User)"을 대상으로
// 채워 넣는다(자기참조).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('auditable_label');
            $table->string('url', 2048)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip', 'url']);
        });
    }
};
