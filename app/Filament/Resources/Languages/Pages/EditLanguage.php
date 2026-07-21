<?php

namespace App\Filament\Resources\Languages\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Resources\Languages\LanguageResource;
use App\Models\Language;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLanguage extends EditRecord
{
    use CancelsToListPage;

    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Language $record): bool => static::getResource()::canDelete($record)),
        ];
    }
}
