<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\HasLevelSelect;
use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Language;
use App\Models\Page;
use App\Services\UploadService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class PageResource extends Resource
{
    use HasLevelSelect;
    use HasLocaleScope;
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'pages';

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = '콘텐츠 페이지';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $modelLabel = '콘텐츠 페이지';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('기본 정보')
                ->schema([
                    TextInput::make('title')->label('제목')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (callable $set, ?string $state) => $set('slug', Str::slug($state))),
                    TextInput::make('slug')->label('URL 주소')->required()->maxLength(255)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->validationMessages(['regex' => 'URL 주소는 영문 소문자/숫자/하이픈만 사용할 수 있습니다(한글, 대문자, 공백, 특수문자 불가).'])
                        ->helperText('페이지 주소(/page/이-값)에 쓰입니다. 제목 입력 시 자동으로 채워지며, 영문 소문자/숫자/하이픈만 사용할 수 있습니다(한글 제목은 자동으로 채워지지 않으니 직접 입력해 주세요). 같은 슬러그를 다른 언어에서도 재사용할 수 있습니다(언어+슬러그 조합으로 구분).')
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, callable $get) => $rule->where('locale', $get('locale'))),
                    Select::make('locale')->label('언어')
                        ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                        ->required()->native(false)->default(fn () => Language::defaultCode()),
                    Select::make('content_type')->label('콘텐츠 타입')
                        ->options(['editor' => '에디터', 'html_file' => 'HTML 파일 업로드'])
                        ->required()->native(false)->default('editor')->live(),
                    self::richEditor('content')->columnSpanFull()
                        ->visible(fn (callable $get) => $get('content_type') === 'editor'),

                    Select::make('html_file_input_mode')->label('입력 방식')
                        ->options(['upload' => '파일 업로드', 'manual' => '서버 경로 직접 입력'])
                        ->helperText('이미 서버에 올려둔 파일을 그대로 연결하려면 "서버 경로 직접 입력"을 선택하세요. 파일을 직접 열어 수정한 뒤에도 경로는 그대로 유지됩니다.')
                        ->default('upload')->native(false)->live()->dehydrated(false)
                        // DB 컬럼과 연결 안 된 UI 전용 필드라(dehydrated(false)) 수정 화면에서는
                        // 아무 값도 자동으로 안 채워진다 — 기존 html_file_path가 이 폼의 업로드
                        // 경로 규칙(uploads/pages/...)과 일치하면 업로드로, 아니면 수동 입력으로 간주한다.
                        ->afterStateHydrated(function (Select $component, ?Page $record) {
                            if ($record && $record->content_type === 'html_file') {
                                $component->state(
                                    str_starts_with((string) $record->html_file_path, 'uploads/pages/') ? 'upload' : 'manual'
                                );
                            }
                        })
                        ->visible(fn (callable $get) => $get('content_type') === 'html_file'),

                    FileUpload::make('html_file_path')->label('HTML 파일')->disk('uploads')
                        ->acceptedFileTypes(['text/html'])
                        ->visible(fn (callable $get) => $get('content_type') === 'html_file' && $get('html_file_input_mode') !== 'manual')
                        ->dehydrated(fn (callable $get) => $get('html_file_input_mode') !== 'manual')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'pages'))
                        ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
                    TextInput::make('html_file_path')->label('서버 경로')
                        ->placeholder('uploads/pages/custom.html')
                        ->helperText('public/ 기준 상대 경로를 입력합니다.')
                        ->visible(fn (callable $get) => $get('content_type') === 'html_file' && $get('html_file_input_mode') === 'manual')
                        ->dehydrated(fn (callable $get) => $get('html_file_input_mode') === 'manual'),

                    Placeholder::make('html_file_path_preview')->label('현재 파일 경로')
                        ->content(fn (?Page $record) => $record?->html_file_path
                            ? new HtmlString('<code>'.e($record->html_file_path).'</code> — <a href="'.url($record->html_file_path).'" target="_blank" rel="noopener" class="underline">새 창에서 열기</a>')
                            : '아직 파일이 없습니다.')
                        ->columnSpanFull()
                        ->visible(fn (callable $get) => $get('content_type') === 'html_file'),
                ])->columns(2),

            Section::make('SEO')
                ->schema([
                    TextInput::make('meta_title')->label('메타 타이틀'),
                    TextInput::make('meta_description')->label('메타 설명'),
                    TextInput::make('meta_keywords')->label('메타 키워드'),
                    FileUpload::make('og_image')->label('OG 이미지')->disk('uploads')->image()
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'images'))
                        ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
                ])->columns(2),

            Section::make('노출 설정')
                ->schema([
                    self::levelSelect('min_level', '최소 접근 레벨', 1),
                    TextInput::make('sort_order')->label('순서')->numeric()->default(0),
                    Toggle::make('is_active')->label('활성 상태')->default(true),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('제목')->searchable(),
                TextColumn::make('slug')->label('URL 주소'),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('content_type')->label('타입')->badge(),
                TextColumn::make('min_level')->label('최소레벨'),
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
                    ->action(fn (Page $record) => self::duplicatePage($record)),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('영구 삭제 시 복구할 수 없습니다.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkDuplicate')
                        ->label('일괄 복제')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(fn (Collection $records) => $records->each(fn (Page $record) => self::duplicatePage($record)))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    // slug는 (locale, slug) 조합으로 유니크해야 해서, 복제본은 원본과 같은 slug를 그대로 쓸 수
    // 없다 — "-copy", "-copy-2"... 식으로 비어있는 값을 찾을 때까지 늘려간다.
    private static function generateUniqueSlug(string $baseSlug, string $locale): string
    {
        $suffix = 1;
        $slug = "{$baseSlug}-copy";

        while (Page::withTrashed()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$baseSlug}-copy-{$suffix}";
        }

        return $slug;
    }

    // 복제본이 원본과 같은 이미지/HTML 파일을 그대로 가리키면, 둘 중 하나만 지워도 실제 파일이
    // 사라져 나머지 하나도 깨진다 — UploadService::duplicate()로 파일 자체를 별도로 복사한 뒤
    // 그 새 경로를 복제본에 지정한다.
    private static function duplicatePage(Page $record): Page
    {
        $new = $record->replicate();
        $new->title = '[복사] '.$record->title;
        $new->slug = self::generateUniqueSlug($record->slug, $record->locale);

        $uploadService = app(UploadService::class);

        if (filled($record->og_image)) {
            $new->og_image = $uploadService->duplicate($record->og_image);
        }

        if (filled($record->html_file_path) && str_starts_with($record->html_file_path, 'uploads/')) {
            $new->html_file_path = $uploadService->duplicate($record->html_file_path);
        }

        $new->save();

        return $new;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
