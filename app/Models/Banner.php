<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'group_key',
        'content_type',
        'locale',
        'title',
        'image_path',
        'html_content',
        'link_url',
        'link_target',
        'alt_text',
        'captions',
        'started_at',
        'ended_at',
        'click_count',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'captions' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'click_count' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
