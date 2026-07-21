<?php

namespace App\Filament\Resources\MaintenanceReports\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Resources\MaintenanceReports\MaintenanceReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceReport extends EditRecord
{
    use CancelsToListPage;

    protected static string $resource = MaintenanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => static::getResource()::canDelete($this->getRecord())),
        ];
    }
}
