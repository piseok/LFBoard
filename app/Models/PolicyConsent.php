<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyConsent extends Model
{
    // 자체 타임스탬프(agreed_at)만 쓰고 created_at/updated_at 컬럼 자체가 없다(append-only 로그).
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'locale',
        'version',
        'agreed_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
