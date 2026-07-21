<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use App\Models\Language;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BannerResource extends Resource
{
    use HasLocaleScope;
    use HasPermissionCheck;

    protected static string $permissionKey = 'banners';

    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = '배너 관리';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $modelLabel = '배너';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group_key')->label('그룹 키')->required()->maxLength(50)
                ->placeholder('예: main_top, sidebar_right')
                ->helperText(fn () => '기존에 쓰인 그룹 키: '.(Banner::query()->distinct()->orderBy('group_key')->pluck('group_key')->implode(', ') ?: '없음')),
            Select::make('locale')->label('언어')
                ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                ->required()->native(false)->default(fn () => Language::defaultCode()),
            TextInput::make('title')->label('제목')->required()->maxLength(100),
            Select::make('content_type')->label('콘텐츠 타입')
                ->options(['image' => '이미지', 'html' => 'HTML/텍스트'])
                ->required()->native(false)->default('image')->live(),
            FileUpload::make('image_path')->label('이미지')->disk('uploads')->image()->columnSpanFull()
                ->required(fn (callable $get) => $get('content_type') === 'image')
                ->visible(fn (callable $get) => $get('content_type') === 'image')
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
            Textarea::make('html_content')->label('HTML/텍스트 내용')->rows(6)->columnSpanFull()
                ->helperText('스타일은 사이트 CSS에서 별도로 입혀지는 걸 전제로, 여기엔 HTML 코드를 그대로 붙여넣으면 됩니다.')
                ->visible(fn (callable $get) => $get('content_type') === 'html'),
            TextInput::make('link_url')->label('링크 URL')->url(),
            Select::make('link_target')->label('링크 타겟')
                ->options(['_self' => '현재 창', '_blank' => '새 창'])
                ->native(false)->default('_blank'),
            TextInput::make('alt_text')->label('ALT 텍스트'),
            Repeater::make('captions')->label('이미지 위 텍스트')->columnSpanFull()
                ->schema([
                    Textarea::make('text')->label('텍스트')->rows(2)->required()
                        ->helperText('줄바꿈은 그대로 반영되고, <span>/<em> 같은 HTML 태그도 그대로 사용할 수 있습니다.'),
                ])
                ->addActionLabel('텍스트 줄 추가')
                ->defaultItems(0)
                ->reorderable()
                ->visible(fn (callable $get) => $get('content_type') === 'image')
                ->helperText('각 줄은 화면에서 순서대로(1번째, 2번째, ...) 렌더링되며, CSS로 줄마다 색상 등을 다르게 지정할 수 있습니다.'),
            DateTimePicker::make('started_at')->label('노출 시작일'),
            DateTimePicker::make('ended_at')->label('노출 종료일'),
            TextInput::make('click_count')->label('클릭수')->disabled()->dehydrated(false),
            TextInput::make('sort_order')->label('순서')->numeric()->default(0),
            Toggle::make('is_active')->label('활성 상태')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')->label('이미지')->disk('uploads'),
                TextColumn::make('content_type')->label('타입')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'html' ? 'HTML/텍스트' : '이미지'),
                TextColumn::make('group_key')->label('그룹 키')->badge(),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('title')->label('제목'),
                TextColumn::make('click_count')->label('클릭수'),
                IconColumn::make('is_active')->label('활성')->boolean(),
                TextColumn::make('sort_order')->label('순서'),
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
                    ->action(fn (Banner $record) => self::duplicateBanner($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkDuplicate')
                        ->label('일괄 복제')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(fn (Collection $records) => $records->each(fn (Banner $record) => self::duplicateBanner($record)))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // 복제본이 원본과 같은 이미지 파일을 그대로 가리키면, 둘 중 하나만 지워도 실제 파일이
    // 사라져 나머지 하나도 이미지가 깨진다 — UploadService::duplicate()로 파일 자체를
    // 별도로 복사한 뒤 그 새 경로를 복제본에 지정한다.
    private static function duplicateBanner(Banner $record): Banner
    {
        $new = $record->replicate();
        $new->title = '[복사] '.$record->title;
        $new->click_count = 0;

        if ($record->content_type === 'image' && filled($record->image_path)) {
            $new->image_path = app(UploadService::class)->duplicate($record->image_path);
        }

        $new->save();

        return $new;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
