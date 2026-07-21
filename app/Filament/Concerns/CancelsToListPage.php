<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

trait CancelsToListPage
{
    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('목록')
            ->url(static::getResource()::getUrl('index'))
            ->color('gray');
    }
}
