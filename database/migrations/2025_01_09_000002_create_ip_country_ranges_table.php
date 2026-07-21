<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IP 대역(국가 단위) 정적 데이터를 담는 테이블 — 로그인마다 외부 API를 호출하는 대신
        // 이 테이블을 직접 조회한다(`php artisan geoip:import`로 채움, database/geoip/ 참고).
        Schema::create('ip_country_ranges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('ip_start');
            $table->unsignedInteger('ip_end');
            $table->string('country_code', 2);
            $table->index('ip_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_country_ranges');
    }
};
