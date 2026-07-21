<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

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
