<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'board_id',
        'user_id',
        'category_id',
        'title',
        'content',
        'author_name',
        'author_password',
        'ip',
        'views',
        'is_global_notice',
        'is_notice',
        'is_secret',
        'is_active',
        'is_draft',
        'recruitment_start_at',
        'recruitment_end_at',
        'custom_fields',
        'created_at',
    ];

    protected $hidden = [
        'author_password',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'is_global_notice' => 'boolean',
            'is_notice' => 'boolean',
            'is_secret' => 'boolean',
            'is_active' => 'boolean',
            'is_draft' => 'boolean',
            'recruitment_start_at' => 'datetime',
            'recruitment_end_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    // 게시판의 커스텀필드 스키마(Board::customFieldSchema())와 짝지어 값을 표시용으로 가공한다.
    // checkbox(다중 선택)는 배열로 저장되므로 쉼표로 이어붙이고, 그 외 단일값 타입은 그대로 문자열로 낸다.
    public function customFieldDisplay(array $fieldDef): ?string
    {
        $value = $this->custom_fields[$fieldDef['key']] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    // 글쓰기를 막는 기능이 아니라 순수 정보 표시용 — 실제 지원/접수는 이 글 밖(별도 addon 등)에서
    // 이루어진다. 기간이 둘 다 비어있으면 상태 표시 자체를 하지 않는다(null).
    public function recruitmentStatus(): ?string
    {
        if (! $this->recruitment_start_at && ! $this->recruitment_end_at) {
            return null;
        }

        $now = now();

        if ($this->recruitment_start_at && $now->lt($this->recruitment_start_at)) {
            return '예정';
        }

        if ($this->recruitment_end_at && $now->gt($this->recruitment_end_at)) {
            return '마감';
        }

        return '기간중';
    }

    // recruitmentStatus()는 DB 컬럼이 아니라 계산값이라 where()로 바로 못 거른다 — 검색 화면에서
    // 접수중/접수마감/접수예정으로 필터링할 수 있어야 해서, 같은 판정 로직을 SQL 조건으로 옮겨둔다.
    public function scopeRecruitmentStatus(Builder $query, string $status): Builder
    {
        $now = now();

        return match ($status) {
            '예정' => $query->whereNotNull('recruitment_start_at')->where('recruitment_start_at', '>', $now),
            '마감' => $query->whereNotNull('recruitment_end_at')->where('recruitment_end_at', '<', $now),
            '기간중' => $query
                ->where(fn (Builder $q) => $q->whereNull('recruitment_start_at')->orWhere('recruitment_start_at', '<=', $now))
                ->where(fn (Builder $q) => $q->whereNull('recruitment_end_at')->orWhere('recruitment_end_at', '>=', $now))
                ->where(fn (Builder $q) => $q->whereNotNull('recruitment_start_at')->orWhereNotNull('recruitment_end_at')),
            default => $query,
        };
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BoardCategory::class, 'category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PostFile::class);
    }

    // PostResource::getEloquentQuery()와 동일한 담당 게시판/담당 언어 스코프 — 대시보드 위젯처럼
    // Resource 쿼리를 안 거치는 곳에서도 매니저 권한 범위를 그대로 적용하기 위해 재사용 가능한
    // 로컬 스코프로 뽑아 둔다.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($boardScope = $user->boardScope()) {
            $query->whereIn('board_id', $boardScope);
        }

        if ($localeScope = $user->localeScope()) {
            $query->whereHas('board', fn (Builder $q) => $q->whereIn('locale', $localeScope));
        }

        // PostResource::getEloquentQuery()와 동일: 임시저장 글은 작성한 본인만 볼 수 있다.
        $query->where(fn (Builder $q) => $q->where('is_draft', false)->orWhere('user_id', $user->id));

        return $query;
    }
}
