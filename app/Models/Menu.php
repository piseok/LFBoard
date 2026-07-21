<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'locale',
        'slug',
        'type',
        'target_id',
        'url',
        'target',
        'min_level',
        'access_mode',
        'hidden_from_header',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_level' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'hidden_from_header' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    // depth 컬럼은 별도로 저장하지 않고 parent_id 체인을 따라 계산한다 (최대 3단계: 0, 1, 2).
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent_id !== null && $depth < 10) {
            $current = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();

            if (! $current) {
                break;
            }

            $depth++;
        }

        return $depth;
    }
}
