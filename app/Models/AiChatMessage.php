<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // created_at 의 DB 기본값(CURRENT_TIMESTAMP)은 DB 서버 시간대(보통 UTC)라 앱 시간대(Asia/Seoul)로 저장되는 다른 시각들과
    // 9시간이 어긋나 감독 화면에 엉뚱한 시각이 보였다(2026-10-21 실측: 10:53 대화가 01:53 으로 표시) - 앱 시간대로 직접 적는다.
    protected static function booted(): void
    {
        static::creating(function (AiChatMessage $message) {
            $message->created_at ??= now();
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }

    // 이 AI 답변에 붙은 행동 카드(제안) - 모델이 도구 호출로 제안하고 서버가 검증한 것.
    public function proposals(): HasMany
    {
        return $this->hasMany(AiChatProposal::class, 'message_id');
    }
}
