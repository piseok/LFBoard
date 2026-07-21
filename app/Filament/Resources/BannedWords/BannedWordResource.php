<?php

namespace App\Filament\Resources\BannedWords;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Resources\BannedWords\Pages\ListBannedWords;
use App\Models\BannedWord;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannedWordResource extends Resource
{
    use RequiresSuperAdmin;

    protected static ?string $model = BannedWord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = '금지어 관리';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = '금지어';

    private const TYPE_OPTIONS = [
        'username' => '아이디',
        'nickname' => '닉네임',
        'content' => '게시글/댓글',
        'all' => '전체',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('word')->label('금지 단어')->required()->maxLength(100),
            Select::make('type')->label('적용 유형')->options(self::TYPE_OPTIONS)->required()->native(false)->default('all'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('word')->label('단어')->searchable(),
                TextColumn::make('type')->label('적용 유형')->badge()
                    ->formatStateUsing(fn (string $state) => self::TYPE_OPTIONS[$state] ?? $state),
                TextColumn::make('created_at')->label('등록일')->date('Y-m-d'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBannedWords::route('/'),
        ];
    }
}
