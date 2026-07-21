<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Popups\PopupResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePopup extends CreateRecord
{
    use HasAiFormFill;

    protected static string $resource = PopupResource::class;
}
