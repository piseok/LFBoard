<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Banners\BannerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    use CancelsToListPage;
    use HasAiFormFill;

    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
