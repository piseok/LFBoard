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
