<?php

namespace App\Filament\Resources\Boards\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Boards\BoardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBoard extends EditRecord
{
    use CancelsToListPage;
    use HasAiFormFill;

    protected static string $resource = BoardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BoardResource::validateCustomFieldSchema($data);
    }
}
