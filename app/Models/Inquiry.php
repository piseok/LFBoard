<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'locale',
        'category',
        'name',
        'email',
        'phone',
        'author_password',
        'title',
        'content',
        'file_path',
        'status',
        'admin_reply',
        'replied_at',
        'is_active',
    ];

    protected $hidden = [
        'author_password',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // InquiryResource가 이미 쓰는 HasLocaleScope와 동일한 기준 — 대시보드 위젯처럼 Resource 쿼리를
    // 안 거치는 곳에서도 담당 언어 범위를 그대로 적용하기 위한 로컬 스코프.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($localeScope = $user->localeScope()) {
            $query->whereIn('locale', $localeScope);
        }

        return $query;
    }
}
