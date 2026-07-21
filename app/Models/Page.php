<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'locale',
        'content_type',
        'content',
        'html_file_path',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'min_level',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_level' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
