<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'admin_name',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'ip',
        'url',
        'before',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'changes' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
