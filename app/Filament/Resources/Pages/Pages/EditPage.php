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
}
