<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMailLog extends Model
{
    protected $fillable = [
        'subject',
        'content',
        'sent_count',
        'failed_count',
        'sent_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
