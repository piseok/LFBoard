<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'locale',
        'skin',
        'layout',
        'use_editor',
        'allow_comment',
        'allow_reply',
        'allow_file',
        'allow_anonymous',
        'allow_image_upload',
        'use_captcha',
        'requires_identity_verification',
        'identity_verification_consent_text',
        'min_read_level',
        'min_write_level',
        'min_comment_level',
        'files_per_post',
        'per_page',
        'order_by',
        'order_direction',
        'description',
        'is_active',
        'exclude_from_search',
        'sort_order',
        'custom_field_schema',
    ];

    protected function casts(): array
    {
        return [
            'use_editor' => 'boolean',
            'allow_comment' => 'boolean',
            'allow_reply' => 'boolean',
            'allow_file' => 'boolean',
            'allow_anonymous' => 'boolean',
            'allow_image_upload' => 'boolean',
            'use_captcha' => 'boolean',
            'requires_identity_verification' => 'boolean',
            'min_read_level' => 'integer',
            'min_write_level' => 'integer',
            'min_comment_level' => 'integer',
            'files_per_post' => 'integer',
            'per_page' => 'integer',
            'is_active' => 'boolean',
            'exclude_from_search' => 'boolean',
            'sort_order' => 'integer',
            'custom_field_schema' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(BoardCategory::class);
    }

    // 커스텀필드는 게시판마다 자유롭게 늘렸다 줄였다 할 수 있어야 해서(기업명/업종/주요제품처럼
    // 게시판별로 완전히 다른 항목), 컬럼을 추가하는 대신 이 JSON 스키마 하나로 정의한다.
    // 각 항목: key(스토리지 키), label(표시명), type(text/textarea/number/date/select/radio/checkbox),
    // options(select/radio/checkbox에서만 사용하는 선택지 배열), required(필수 여부).
    public function customFieldSchema(): array
    {
        return $this->custom_field_schema ?? [];
    }

    public function customField(string $key): ?array
    {
        return collect($this->customFieldSchema())->firstWhere('key', $key);
    }
}
