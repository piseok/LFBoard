<?php

namespace App\Filament\Resources\Popups;

use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Popups\Pages\EditPopup;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Models\Language;
use App\Models\Popup;
use App\Services\UploadService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PopupResource extends Resource
{
    use HasLocaleScope;
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'popups';

    protected static ?string $model = Popup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static ?string $navigationLabel = '팝업 관리';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $modelLabel = '팝업';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('제목')->required()->maxLength(100)->columnSpanFull(),
            Select::make('locale')->label('언어')
                ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                ->required()->native(false)->default(fn () => Language::defaultCode()),
            Select::make('content_type')->label('콘텐츠 타입')
                ->options(['image' => '이미지', 'html' => 'HTML'])
                ->required()->native(false)->default('image')->live(),
            FileUpload::make('image_path')->label('이미지')->disk('uploads')->image()
                ->visible(fn (callable $get) => $get('content_type') === 'image')
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
            self::richEditor('html_content', 'HTML 내용')->columnSpanFull()
                ->visible(fn (callable $get) => $get('content_type') === 'html'),
            Select::make('position')->label('노출 위치')
                ->options([
                    'center' => '중앙',
                    'top-left' => '좌상단',
                    'top-right' => '우상단',
                    'bottom-left' => '좌하단',
                    'bottom-right' => '우하단',
                ])->native(false)->default('center'),
            TextInput::make('width')->label('너비(px)')->numeric()->default(400),
            TextInput::make('height')->label('높이(px)')->numeric()->default(300),
            DateTimePicker::make('started_at')->label('노출 시작일'),
            DateTimePicker::make('ended_at')->label('노출 종료일'),
            TextInput::make('sort_order')->label('순서')->numeric()->default(0),
            Toggle::make('is_active')->label('활성 상태')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('제목'),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('content_type')->label('타입')->badge(),
                TextColumn::make('position')->label('위치'),
                TextColumn::make('started_at')->label('시작일')->dateTime('Y-m-d H:i')->placeholder('-'),
                TextColumn::make('ended_at')->label('종료일')->dateTime('Y-m-d H:i')->placeholder('-'),
                IconColumn::make('is_active')->label('활성')->boolean(),
            ])
            ->filters([
                SelectFilter::make('locale')->label('언어')
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('복제')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->action(fn (Popup $record) => self::duplicatePopup($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkDuplicate')
                        ->label('일괄 복제')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(fn (Collection $records) => $records->each(fn (Popup $record) => self::duplicatePopup($record)))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // 복제본이 원본과 같은 이미지 파일을 그대로 가리키면, 둘 중 하나만 지워도 실제 파일이
    // 사라져 나머지 하나도 이미지가 깨진다 — UploadService::duplicate()로 파일 자체를
    // 별도로 복사한 뒤 그 새 경로를 복제본에 지정한다.
    private static function duplicatePopup(Popup $record): Popup
    {
        $new = $record->replicate();
        $new->title = '[복사] '.$record->title;

        if ($record->content_type === 'image' && filled($record->image_path)) {
            $new->image_path = app(UploadService::class)->duplicate($record->image_path);
        }

        $new->save();

        return $new;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopups::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
        ];
    }
}
