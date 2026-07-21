<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            // 방문자에게 날짜/시간을 표시할 때 쓰는 시간대. DB에 저장된 시각 자체는 앱 기본 시간대
            // (config('app.timezone'), 이 프로젝트는 Asia/Seoul)로 통일되어 있고, 화면에 보여줄
            // 때만 이 값으로 변환한다(저장값 자체를 바꾸는 게 아니므로 기존 데이터에 영향 없음).
            $table->string('timezone', 64)->default('Asia/Seoul')->after('code');
        });

        DB::table('languages')->where('code', 'en')->update(['timezone' => 'UTC']);
        DB::table('languages')->where('code', 'ja')->update(['timezone' => 'Asia/Tokyo']);
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
