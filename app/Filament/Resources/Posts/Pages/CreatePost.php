<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use HasAiFormFill;

    protected static string $resource = PostResource::class;

    private array $pendingAttachments = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PostResource::mergePlainContent($data);

        // 관리자 화면에는 작성자를 고르는 필드가 없다 — 지금 글을 만드는 관리자 본인이 작성자다.
        // 이걸 임시저장일 때만 지정하면(예전 코드) 일반 발행 글은 user_id가 계속 null로 남아
        // 목록의 "작성자" 컬럼이 항상 "비회원"으로 뜬다(실사용자 발견 버그) — 임시저장 여부와
        // 무관하게 항상 지정해야 한다.
        $data['user_id'] = auth()->id();

        // 첨부파일은 레코드가 생겨야(post_id) PostFile을 만들 수 있어 afterCreate()에서
        // 처리한다 — 그때까지 이 페이지 인스턴스에 잠시 들고 있는다.
        $this->pendingAttachments = PostResource::extractAttachments($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        PostResource::syncAttachments($this->record, $this->pendingAttachments);
    }
}
