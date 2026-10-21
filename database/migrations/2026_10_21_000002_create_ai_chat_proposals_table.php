<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// AI 비서가 도구 호출로 만든 "행동 카드(제안)" 저장소(2026-10-21, AI 비서 2.0).
// 모델은 제안만 하고 실행하지 않는다 - 서버가 검증·정규화한 값만 여기에 담고, 관리자가 카드의 링크로
// 해당 작성 화면(Filament Create 페이지)을 열면 그 화면이 값을 폼에 "채우기만" 한다(저장은 사람).
// ai_chat_messages.content 에 JSON 을 넣지 않는 이유: 이력을 모델에 다시 보낼 때와 감독 화면이 원문 JSON 을 찍기 때문.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_proposals', function (Blueprint $table) {
            $table->id()->comment('기본 키');
            $table->foreignId('message_id')->comment('제안이 붙은 AI 답변 메시지(ai_chat_messages.id) - 메시지 삭제 시 함께 삭제')
                ->constrained('ai_chat_messages')->cascadeOnDelete();
            $table->string('kind', 20)->comment('제안 종류(popup/post/page/banner/open_page)');
            $table->json('payload')->comment('서버가 검증·정규화한 제안 값(모델 원문 아님) - 화면별 미리 채우기 값');
            $table->string('status', 20)->default('proposed')->comment('상태(proposed=제안됨, image_ready=이미지 생성됨, used=작성 화면에서 저장됨, expired=만료)');
            $table->timestamp('expires_at')->comment('이 시각이 지나면 작성 화면에서 열 수 없음(만료)');
            $table->string('image_path')->nullable()->comment('카드에서 생성한 이미지 경로(uploads 디스크 기준) - 없으면 이미지 없는 제안');
            $table->timestamp('used_at')->nullable()->comment('작성 화면에서 실제 저장된 시각');
            $table->unsignedBigInteger('used_record_id')->nullable()->comment('저장되어 만들어진 레코드의 id(종류별로 popups/posts/pages/banners)');
            $table->timestamp('created_at')->comment('제안 생성 시각');

            $table->index('message_id', 'ai_chat_proposals_message_idx');
            $table->index(['status', 'expires_at'], 'ai_chat_proposals_status_expires_idx');
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'ai_chat_proposals` COMMENT = '.DB::getPdo()->quote('AI 비서 행동 카드(제안) - 모델 도구 호출을 서버가 검증해 저장, 실행은 관리자가 작성 화면에서 직접'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_proposals');
    }
};
