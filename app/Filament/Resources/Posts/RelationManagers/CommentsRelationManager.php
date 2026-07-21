<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = '댓글 관리';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed()->orderBy('created_at'))
            ->columns([
                TextColumn::make('depth')->label('구분')->badge()
                    ->formatStateUsing(fn (int $state) => $state === 0 ? '댓글' : '답글')
                    ->color(fn (int $state) => $state === 0 ? 'primary' : 'gray'),
                TextColumn::make('author')->label('작성자')
                    ->state(fn (?Comment $record) => $record ? ($record->user?->name ?? $record->author_name ?? '비회원') : null),
                TextColumn::make('ip')->label('IP'),
                TextColumn::make('content')->label('내용')->limit(50),
                IconColumn::make('is_active')->label('활성')->boolean(),
            ])
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (?Comment $record) => $record?->is_active ? '비활성화' : '활성화')
                    ->icon(fn (?Comment $record) => $record?->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->action(fn (Comment $record) => $record->update(['is_active' => ! $record->is_active])),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
