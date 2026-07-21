<?php

namespace App\Filament\Resources\Languages;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Resources\Languages\Pages\CreateLanguage;
use App\Filament\Resources\Languages\Pages\EditLanguage;
use App\Filament\Resources\Languages\Pages\ListLanguages;
use App\Models\Language;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LanguageResource extends Resource
{
    use RequiresSuperAdmin;

    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = '언어 관리';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '언어';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('언어 코드')->required()->maxLength(5)
                ->helperText('URL 접두사로 쓰입니다(예: ja → /ja/...). 기본 언어는 접두사가 붙지 않습니다.')
                ->unique(ignoreRecord: true),
            TextInput::make('name')->label('표시명')->required()->maxLength(50)
                ->helperText('관리자 화면에 보일 이름(예: 日本語)'),
            Select::make('timezone')->label('시간대')
                ->options(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()))
                ->searchable()
                ->required()
                ->default('Asia/Seoul')
                ->helperText('이 언어로 볼 때 게시글/댓글 등의 날짜·시간을 표시할 시간대입니다(저장은 항상 서버 기본 시간대로 되고, 화면 표시할 때만 변환됩니다).'),
            Toggle::make('is_default')->label('기본 언어')
                ->helperText('기본 언어는 URL에 접두사가 붙지 않습니다. 하나만 지정 가능(다른 언어의 기본 지정은 자동 해제됩니다).'),
            Toggle::make('is_active')->label('활성화')->default(true)
                ->disabled(fn (?Language $record): bool => $record?->is_default ?? false)
                ->helperText(fn (?Language $record) => $record?->is_default
                    ? '기본 언어는 항상 활성 상태여야 합니다(비활성화하려면 먼저 다른 언어를 기본 언어로 지정하세요).'
                    : '꺼두면 방문자에게 노출되지 않습니다(URL 접두사 접근도 차단).'),
            TextInput::make('sort_order')->label('정렬 순서')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label('코드')->badge(),
                TextColumn::make('name')->label('표시명'),
                TextColumn::make('timezone')->label('시간대'),
                IconColumn::make('is_default')->label('기본 언어')->boolean(),
                IconColumn::make('is_active')->label('활성화')->boolean(),
                TextColumn::make('sort_order')->label('정렬 순서'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Language $record): bool => ! $record->is_default),
            ]);
    }

    // 기본 언어는 삭제 불가 — 삭제하면 사이트에 언어가 하나도 기본값이 없는 상태가 됨
    public static function canDelete(Model $record): bool
    {
        return ! $record->is_default;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLanguages::route('/'),
            'create' => CreateLanguage::route('/create'),
            'edit' => EditLanguage::route('/{record}/edit'),
        ];
    }
}
