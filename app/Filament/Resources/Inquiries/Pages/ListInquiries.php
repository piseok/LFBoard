<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('정상'),
            'trashed' => Tab::make('삭제됨')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed()),
            'all' => Tab::make('전체')
                ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed()),
        ];
    }
}
