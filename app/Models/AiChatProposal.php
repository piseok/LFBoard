<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AI 비서의 "행동 카드(제안)". 모델이 도구 호출로 제안한 내용을 서버가 검증·정규화해 담아 둔 것이다.
// 이 모델/표에는 "실행"이 없다 - 관리자가 카드의 링크로 해당 작성 화면(Create 페이지)을 열면 그 화면이 값을
// 폼에 채우기만 하고, 최종 저장은 사람이 화면의 저장 버튼으로 한다(AiProposalService, HasAiProposalPrefill 참고).
class AiChatProposal extends Model
{
    public const KIND_POPUP = 'popup';

    public const KIND_POST = 'post';

    public const KIND_PAGE = 'page';

    public const KIND_BANNER = 'banner';

    public const KIND_OPEN_PAGE = 'open_page';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_IMAGE_READY = 'image_ready';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    // 제안을 열 수 있는 시간(분). 지나면 작성 화면이 거부한다.
    public const TTL_MINUTES = 60;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'kind',
        'payload',
        'status',
        'expires_at',
        'image_path',
        'used_at',
        'used_record_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'message_id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    // 작성 화면에서 열 수 있는 상태인가 - 만료되지 않았고 아직 저장에 쓰이지 않은 제안.
    public function isOpenable(): bool
    {
        return ! $this->isExpired()
            && in_array($this->status, [self::STATUS_PROPOSED, self::STATUS_IMAGE_READY], true);
    }

    // 이 제안에 이미지 생성이 필요한가(이미지 프롬프트가 있고 아직 이미지가 없음).
    public function needsImage(): bool
    {
        return filled($this->payload['image_prompt'] ?? null) && blank($this->image_path);
    }

    // 현재 소유자(제안 → 메시지 → 대화 → user_id).
    public function ownerId(): ?int
    {
        $ownerId = $this->message?->conversation?->user_id;

        return $ownerId === null ? null : (int) $ownerId;
    }
}
