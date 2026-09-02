<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use CancelsToListPage;
    use HasAiFormFill;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // html_file_upload는 Page의 실제 컬럼이 아닌 FileUpload 전용 UI 필드라 그대로 두면
        // fillable에 없어 조용히 버려진다 — 업로드된 경로를 진짜 컬럼인 html_file_path로 옮긴다.
        if (array_key_exists('html_file_upload', $data)) {
            $data['html_file_path'] = $data['html_file_upload'];
        }
        unset($data['html_file_upload']);

        return $data;
    }
}
