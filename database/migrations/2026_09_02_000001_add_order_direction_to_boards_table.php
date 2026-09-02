<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            // 허용값: asc(오름차순)/desc(내림차순). order_by(정렬 기준 컬럼)와 별개로
            // 정렬 방향을 관리자가 고를 수 있게 한다.
            $table->string('order_direction')->default('desc')->after('order_by')
                ->comment('정렬 방향: asc=오름차순, desc=내림차순');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('order_direction');
        });
    }
};
