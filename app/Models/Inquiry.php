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

    // InquiryResource 목록의 '응답 소요시간' 컬럼이 쓰는 계산 — 접수(created_at)부터 답변(replied_at)까지
    // 걸린 시간을 분 단위로 반환한다. 아직 답변하지 않은 건(replied_at이 null)은 null을 반환해 '미답변'
    // placeholder를 렌더링하도록 한다. Attribute 접근자가 아닌 일반 메서드로 둬서 toArray()/toJson()
    // 직렬화 결과(공개 InquiryController 등)에 영향을 주지 않는다.
    // Carbon 3의 diffInMinutes()는 float을 반환하므로 반드시 (int) 캐스팅 후 사용해야 한다.
    public function responseMinutes(): ?int
    {
        if (! $this->created_at || ! $this->replied_at) {
            return null;
        }

        return max(0, (int) $this->created_at->diffInMinutes($this->replied_at));
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
