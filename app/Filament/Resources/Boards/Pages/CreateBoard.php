<?php

namespace App\Filament\Resources\Boards\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Boards\BoardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBoard extends CreateRecord
{
    use HasAiFormFill;

    protected static string $resource = BoardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BoardResource::validateCustomFieldSchema($data);
    }
}
