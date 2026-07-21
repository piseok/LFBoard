<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 게시글 임시저장 기능. 비회원(익명 글쓰기)은 계정이 없어 나중에 다시 찾아올 방법이 없으므로
// 임시저장은 로그인 회원(+관리자)에게만 제공한다 — 컨트롤러/뷰에서 별도로 막는다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('is_active');
            $table->index(['board_id', 'user_id', 'is_draft']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['board_id', 'user_id', 'is_draft']);
            $table->dropColumn('is_draft');
        });
    }
};
