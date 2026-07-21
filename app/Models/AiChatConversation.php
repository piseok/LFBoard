<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'provider',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id');
    }

    // 일반 관리자는 자기 대화만, 최고관리자/일반 최고관리자(client)는 전체 열람 — 게시글
    // 임시저장(자기 것만, 슈퍼관리자도 예외 없음)과는 반대로 여기서는 사용자가 명시적으로
    // 전체 열람/삭제를 요청했다.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isClientAdmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
