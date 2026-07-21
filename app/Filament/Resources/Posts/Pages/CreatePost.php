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

        // 임시저장으로 저장하는 경우, 저장하는 본인을 소유자로 지정해야 다른 관리자에게는
        // 보이지 않으면서 본인은 계속 볼 수 있다(getEloquentQuery()의 임시저장 스코프 참고).
        if ($data['is_draft'] ?? false) {
            $data['user_id'] = auth()->id();
        }

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
