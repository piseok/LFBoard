<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
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
