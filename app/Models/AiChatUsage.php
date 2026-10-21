<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AI 비서 일일 한도 계산용 호출 기록 - 한 줄 = 제공자 호출 1회. 대화(AiChatConversation)와
// 독립이라 대화를 지워도 남는다(AiUsageService 참고).
class AiChatUsage extends Model
{
    public const KIND_CHAT = 'chat';

    public const KIND_IMAGE = 'image';

    protected $table = 'ai_chat_usage';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'provider',
        'kind',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
