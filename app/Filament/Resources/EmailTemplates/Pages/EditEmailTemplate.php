<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    use CancelsToListPage;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
