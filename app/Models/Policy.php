<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Policy extends Model
{
    protected $fillable = [
        'type',
        'locale',
        'title',
        'content',
        'content_type',
        'html_file_path',
        'is_required',
        'is_active',
        'version',
        'pending_version',
        'pending_title',
        'pending_content',
        'pending_content_type',
        'pending_html_file_path',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'effective_at' => 'datetime',
        ];
    }

    // 요청 언어 버전이 없으면 기본 언어(한국어)로 폴백한다 — 약관은 회원가입 시 필수 동의 항목이라
    // 번역이 아직 없다고 체크박스 자체가 사라지면 안 되므로(법적 동의 자체가 통째로 빠지는 문제),
    // 이메일 템플릿과 달리 "영어로 통일" 대신 안전하게 기본 언어로 폴백한다.
    public static function findByType(string $type, ?string $locale = null): ?self
    {
        $locale ??= Language::defaultCode();

        return static::where('type', $type)->where('is_active', true)->where('locale', $locale)->first()
            ?? static::where('type', $type)->where('is_active', true)->where('locale', Language::defaultCode())->first();
    }

    /**
     * 회원가입 폼 등에서 "지금 언어에서 활성화된 약관 전체"가 필요할 때 사용 — 타입별로 현재 언어
     * 버전이 있으면 그걸, 없으면 기본 언어 버전으로 채워서 반환한다(findByType과 동일한 폴백 규칙).
     *
     * @return Collection<int, self>
     */
    public static function activeForLocale(?string $locale = null): Collection
    {
        $locale ??= Language::defaultCode();
        $defaultLocale = Language::defaultCode();

        $fallback = static::where('is_active', true)->where('locale', $defaultLocale)->get()->keyBy('type');

        if ($locale === $defaultLocale) {
            return $fallback->values();
        }

        $localized = static::where('is_active', true)->where('locale', $locale)->get()->keyBy('type');

        return $fallback->merge($localized)->values();
    }

    // 시행 예정일이 아직 안 지난 "예약된 변경"이 걸려 있는지 — 사전고지 배너/안내 페이지 노출 여부 판단에 쓴다.
    public function hasPendingChange(): bool
    {
        return $this->pending_version !== null && $this->effective_at !== null && $this->effective_at->isFuture();
    }

    /**
     * 지금 언어에서 실제로 보여지는(폴백 포함) 정책들 중 예약된 변경이 걸려 있는 것만 반환한다.
     *
     * @return Collection<int, self>
     */
    public static function pendingForLocale(?string $locale = null): Collection
    {
        return static::activeForLocale($locale)->filter(fn (self $policy): bool => $policy->hasPendingChange())->values();
    }

    // 시행 예정일이 된 예약 변경을 실제 활성 내용으로 승격한다. 이후 버전이 달라지므로 기존
    // 재동의 강제(EnsureRequiredPolicyConsent)가 자연스럽게 다시 트리거된다.
    public function applyPendingChange(): void
    {
        $this->forceFill([
            'version' => $this->pending_version,
            'title' => $this->pending_title ?? $this->title,
            'content' => $this->pending_content ?? $this->content,
            'content_type' => $this->pending_content_type ?? $this->content_type,
            'html_file_path' => $this->pending_content !== null || $this->pending_html_file_path !== null
                ? $this->pending_html_file_path
                : $this->html_file_path,
            'pending_version' => null,
            'pending_title' => null,
            'pending_content' => null,
            'pending_content_type' => 'editor',
            'pending_html_file_path' => null,
            'effective_at' => null,
        ])->save();
    }

    // PageResource와 같은 규약(content_type: editor/html_file) — 방문자 화면에 실제로 보여줄
    // HTML을 계산한다. html_file 타입은 관리자만 올릴 수 있는 신뢰된 콘텐츠라는 전제로 sanitize하지
    // 않는다(에디터 타입도 지금까지 그래왔음 — 이 메서드가 그 기존 동작을 그대로 옮긴 것뿐, 새로
    // sanitize를 추가한 게 아님).
    public function renderedContent(): ?string
    {
        return $this->resolveContent($this->content_type, $this->content, $this->html_file_path);
    }

    // pending_content_type이 html_file인데 아직 파일이 없거나, editor인데 pending_content가
    // 비어있으면(= 예약 변경을 아직 준비 안 함) 안내 화면에서 "현재 시행 중" 내용을 그대로 보여준다
    // (기존 `pending_content ?? content` 폴백 규칙을 content_type 인지 버전으로 그대로 옮김).
    public function renderedPendingContent(): ?string
    {
        return $this->resolveContent($this->pending_content_type, $this->pending_content, $this->pending_html_file_path)
            ?? $this->renderedContent();
    }

    private function resolveContent(?string $contentType, ?string $content, ?string $htmlFilePath): ?string
    {
        return match ($contentType) {
            'html_file' => $htmlFilePath ? (string) Storage::disk('uploads')->get($htmlFilePath) : null,
            default => $content,
        };
    }
}
