<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Banners\BannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    use HasAiFormFill;

    protected static string $resource = BannerResource::class;
}
