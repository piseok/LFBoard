<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorNotice extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'url',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
