<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use App\Models\Board;
use App\Models\BoardCategory;
use App\Models\Post;
use App\Services\UploadService;
use BackedEnum;
use UnitEnum;
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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PostResource extends Resource
{
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'posts';

    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?string $navigationLabel = '게시글 관리';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    // Post 자체엔 locale 컬럼이 없어(소속 게시판에만 있음) HasLocaleScope 트레이트를 그대로 못 쓰고,
    // "담당 언어" + "담당 게시판" 두 스코프를 함께 직접 적용한다. "담당 언어"만 있던 지금까지는
    // 게시글관리 권한이 있는 일반관리자가 언어와 무관하게 전체 게시글을 볼 수 있었던 것도 이번에
    // 함께 바로잡는다(BoardResource는 이미 스코프되어 있었음).
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount(['files', 'comments']);
        $user = auth()->user();

        if ($boardScope = $user?->boardScope()) {
            $query->whereIn('board_id', $boardScope);
        }

        if ($localeScope = $user?->localeScope()) {
            $query->whereHas('board', fn (Builder $q) => $q->whereIn('locale', $localeScope));
        }

        // 임시저장 글은 작성한 본인(회원이든 관리자든)만 볼 수 있다 — 다른 관리자는 물론
        // 관리자들끼리도 서로의 임시저장 글이 보이면 안 된다(슈퍼관리자도 예외 없음).
        $query->where(fn (Builder $q) => $q->where('is_draft', false)->orWhere('user_id', $user?->id));

        return $query;
    }

    protected static ?string $modelLabel = '게시글';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('board_id')->label('게시판')
                ->options(fn () => Board::query()->pluck('name', 'id'))
                ->required()->live()->native(false),
            Select::make('category_id')->label('카테고리')
                ->options(fn (callable $get) => $get('board_id')
                    ? BoardCategory::query()->where('board_id', $get('board_id'))->pluck('name', 'id')
                    : [])
                ->native(false),
            TextInput::make('title')->label('제목')->required()->maxLength(255)->columnSpanFull(),
            self::richEditor('content')->columnSpanFull()
                ->visible(fn (callable $get) => self::boardUsesEditor($get('board_id')))
                ->required(fn (callable $get) => self::boardUsesEditor($get('board_id')))
                ->dehydrated(fn (callable $get) => self::boardUsesEditor($get('board_id'))),
            Textarea::make('content_text')->label('내용')->rows(12)->columnSpanFull()
                ->visible(fn (callable $get) => ! self::boardUsesEditor($get('board_id')))
                ->required(fn (callable $get) => ! self::boardUsesEditor($get('board_id')))
                ->afterStateHydrated(function (Textarea $component, ?Post $record) {
                    if ($record) {
                        $component->state($record->content);
                    }
                }),
            FileUpload::make('attachments')->label('첨부파일')->columnSpanFull()
                ->multiple()->disk('uploads')->storeFileNamesIn('attachment_names')
                ->visible(fn (callable $get) => self::boardAllowsFile($get('board_id')))
                ->maxFiles(fn (callable $get) => self::boardFilesPerPost($get('board_id')))
                ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
                    try {
                        return app(UploadService::class)->upload($file, 'files');
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return null;
                    }
                })
                ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file))
                ->afterStateHydrated(function (FileUpload $component, ?Post $record) {
                    if ($record) {
                        $component->state($record->files->pluck('file_path')->all());
                    }
                }),
            Toggle::make('is_global_notice')->label('전체 공지')->helperText('모든 게시판 최상단 고정'),
            Toggle::make('is_notice')->label('게시판 공지')->helperText('해당 게시판 내 최상단 고정'),
            Toggle::make('is_secret')->label('비밀글'),
            Toggle::make('is_active')->label('활성 상태')->default(true),
            Toggle::make('is_draft')->label('임시저장')
                ->helperText('켜두면 정식 게시 전 상태로 취급되어 게시판 목록/공지에 노출되지 않습니다. 회원이 직접 임시저장한 글도 여기서 이어서 관리할 수 있습니다.'),
            DateTimePicker::make('created_at')->label('작성일')
                ->helperText('목록 정렬/노출 순서에 사용되는 작성일을 직접 변경할 수 있습니다.')
                ->native(false)->seconds(false)->default(now()),
            DateTimePicker::make('recruitment_start_at')->label('모집 시작일시')
                ->helperText('채용/모집 공고 글에만 사용. 둘 다 비워두면 상태 표시 자체가 안 나타납니다.')
                ->native(false)->seconds(false),
            DateTimePicker::make('recruitment_end_at')->label('모집 종료일시')
                ->helperText('글쓰기를 막지 않는 정보 표시 전용입니다 — 예정/기간중/마감 상태만 보여줍니다. 실제 지원 접수는 이 글 밖에서 이루어집니다.')
                ->native(false)->seconds(false),
            Section::make('커스텀 필드')
                ->schema(fn (callable $get) => self::buildCustomFieldComponents($get('board_id')))
                ->visible(fn (callable $get) => self::buildCustomFieldComponents($get('board_id')) !== [])
                ->columns(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    // 게시판(Board::custom_field_schema)마다 자유롭게 정의한 필드를 그 자리에서 폼으로 만들어준다.
    // 값은 실제 컬럼이 아니라 Post::custom_fields(JSON)에 {key: value} 형태로 저장되므로,
    // statePath를 "custom_fields.{key}" 점 표기로 지정해 중첩된 JSON 배열 상태로 바로 매핑한다.
    private static function buildCustomFieldComponents(mixed $boardId): array
    {
        if (! $boardId) {
            return [];
        }

        $schema = Board::query()->find($boardId)?->customFieldSchema() ?? [];

        return collect($schema)->filter(fn (array $field) => filled($field['key'] ?? null))->map(function (array $field) {
            $statePath = "custom_fields.{$field['key']}";
            $label = $field['label'] ?? $field['key'];
            $required = (bool) ($field['required'] ?? false);
            $options = collect($field['options'] ?? [])->mapWithKeys(fn ($option) => [$option => $option])->all();

            return match ($field['type'] ?? 'text') {
                'textarea' => Textarea::make($statePath)->label($label)->required($required),
                'number' => TextInput::make($statePath)->numeric()->label($label)->required($required),
                'date' => DatePicker::make($statePath)->native(false)->label($label)->required($required),
                'select' => Select::make($statePath)->options($options)->native(false)->label($label)->required($required),
                'radio' => Radio::make($statePath)->options($options)->label($label)->required($required),
                'checkbox' => CheckboxList::make($statePath)->options($options)->label($label)->required($required),
                default => TextInput::make($statePath)->label($label)->required($required),
            };
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('board.name')->label('게시판'),
                TextColumn::make('category.name')->label('카테고리')->placeholder('-'),
                TextColumn::make('title')->label('제목')->limit(40)->searchable(),
                TextColumn::make('is_draft')->label('상태')
                    ->state(fn (?Post $record) => $record && $record->is_draft ? '임시저장' : '게시됨')
                    ->badge()->color(fn (?Post $record) => $record && $record->is_draft ? 'warning' : 'success'),
                TextColumn::make('author')->label('작성자')
                    ->state(fn (?Post $record) => $record ? ($record->user?->name ?? $record->author_name ?? '비회원') : null),
                IconColumn::make('files_count')->label('첨부')
                    ->icon(fn (?Post $record) => $record?->files_count ? Heroicon::OutlinedPaperClip : null)
                    ->tooltip(fn (?Post $record) => $record?->files_count ? __(':count개 첨부', ['count' => $record->files_count]) : null),
                TextColumn::make('comments_count')->label('댓글'),
                TextColumn::make('recruitment_status')->label('모집 상태')
                    ->state(fn (?Post $record) => $record?->recruitmentStatus())
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        '예정' => 'gray', '기간중' => 'success', '마감' => 'danger', default => null,
                    })
                    ->placeholder('-'),
                TextColumn::make('views')->label('조회수'),
                TextColumn::make('notice')->label('공지')
                    ->state(fn (?Post $record) => $record ? ($record->is_global_notice ? '전체공지' : ($record->is_notice ? '게시판공지' : '')) : null)
                    ->badge()
                    ->color(fn (?Post $record) => $record?->is_global_notice ? 'danger' : 'warning')
                    ->visible(fn (?Post $record) => $record && ($record->is_global_notice || $record->is_notice)),
                TextColumn::make('created_at')->label('작성일')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('board_id')->label('게시판')->options(fn () => Board::query()->pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('활성 상태'),
                TernaryFilter::make('is_draft')->label('임시저장 여부'),
                SelectFilter::make('notice')->label('공지 구분')
                    ->options(['global' => '전체공지', 'board' => '게시판공지', 'none' => '일반'])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'global' => $query->where('is_global_notice', true),
                            'board' => $query->where('is_notice', true)->where('is_global_notice', false),
                            'none' => $query->where('is_notice', false)->where('is_global_notice', false),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('move')
                    ->label('이동')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->schema([
                        Select::make('board_id')->label('대상 게시판')->options(fn () => Board::query()->pluck('name', 'id'))->required()->native(false),
                    ])
                    ->action(fn (Post $record, array $data) => $record->update(['board_id' => $data['board_id'], 'category_id' => null])),
                Action::make('copy')
                    ->label('복사')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->schema([
                        Select::make('board_id')->label('대상 게시판')->options(fn () => Board::query()->pluck('name', 'id'))->required()->native(false),
                    ])
                    ->action(fn (Post $record, array $data) => self::duplicatePost($record, $data['board_id'])),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('영구 삭제 시 첨부파일도 함께 삭제되며 복구할 수 없습니다.')
                    ->action(function (Post $record) {
                        $uploadService = app(\App\Services\UploadService::class);
                        foreach ($record->files as $file) {
                            $uploadService->delete($file->file_path);
                            $file->delete();
                        }
                        $record->forceDelete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setNotice')
                        ->label('일괄 공지 설정')
                        ->action(fn (Collection $records) => $records->each->update(['is_notice' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unsetNotice')
                        ->label('일괄 공지 해제')
                        ->action(fn (Collection $records) => $records->each->update(['is_notice' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('activate')
                        ->label('일괄 활성화')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('일괄 비활성화')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkMove')
                        ->label('일괄 이동')
                        ->schema([
                            Select::make('board_id')->label('대상 게시판')->options(fn () => Board::query()->pluck('name', 'id'))->required()->native(false),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['board_id' => $data['board_id'], 'category_id' => null]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkCopy')
                        ->label('일괄 복사')
                        ->schema([
                            Select::make('board_id')->label('대상 게시판')->options(fn () => Board::query()->pluck('name', 'id'))->required()->native(false),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each(fn (Post $record) => self::duplicatePost($record, $data['board_id'])))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription('영구 삭제 시 첨부파일도 함께 삭제되며 복구할 수 없습니다.')
                        ->action(function (Collection $records) {
                            $uploadService = app(\App\Services\UploadService::class);

                            foreach ($records as $record) {
                                foreach ($record->files as $file) {
                                    $uploadService->delete($file->file_path);
                                    $file->delete();
                                }
                                $record->forceDelete();
                            }
                        }),
                ]),
            ]);
    }

    // 게시판별 에디터 사용 여부(use_editor)에 따라 리치에디터/일반 텍스트 입력을 전환하는 데 쓰인다.
    private static function boardUsesEditor(mixed $boardId): bool
    {
        if (! $boardId) {
            return true;
        }

        return (bool) Board::query()->whereKey($boardId)->value('use_editor');
    }

    private static function boardAllowsFile(mixed $boardId): bool
    {
        if (! $boardId) {
            return true;
        }

        return (bool) Board::query()->whereKey($boardId)->value('allow_file');
    }

    private static function boardFilesPerPost(mixed $boardId): ?int
    {
        if (! $boardId) {
            return null;
        }

        return Board::query()->whereKey($boardId)->value('files_per_post');
    }

    // FileUpload는 저장 시 'attachments'(경로 배열)/'attachment_names'(경로=>원본파일명)를
    // Post 모델에는 없는 컬럼으로 함께 내려주므로, Post::create()/update()에 그대로 넘기면
    // (특히 preventSilentlyDiscardingAttributes가 켜진 환경에서) 에러가 난다 — 미리 뽑아내고
    // $data에서 제거한다. 실제 PostFile 레코드 생성/동기화는 레코드가 존재해야 하는(afterCreate/
    // afterSave) 시점에 syncAttachments()로 따로 처리한다.
    public static function extractAttachments(array &$data): array
    {
        $paths = $data['attachments'] ?? [];
        $names = $data['attachment_names'] ?? [];

        unset($data['attachments'], $data['attachment_names']);

        return collect($paths)->map(fn (string $path) => [
            'path' => $path,
            'original_name' => is_array($names) ? ($names[$path] ?? basename($path)) : basename($path),
        ])->all();
    }

    // $attachments에 없는(=사용자가 지운) 기존 파일은 실제 디스크 파일까지 함께 삭제하고,
    // 새로 추가된 경로만 PostFile로 새로 만든다 — 이미 있던 파일은 그대로 둔다(재생성 방지).
    public static function syncAttachments(Post $post, array $attachments): void
    {
        $keepPaths = collect($attachments)->pluck('path')->all();
        $uploadService = app(UploadService::class);

        foreach ($post->files as $existingFile) {
            if (! in_array($existingFile->file_path, $keepPaths, true)) {
                $uploadService->delete($existingFile->file_path);
                $existingFile->delete();
            }
        }

        $existingPaths = $post->files()->pluck('file_path')->all();
        $disk = Storage::disk('uploads');
        $sortOrder = $post->files()->count();

        foreach ($attachments as $attachment) {
            if (in_array($attachment['path'], $existingPaths, true)) {
                continue;
            }

            $post->files()->create([
                'original_name' => $attachment['original_name'],
                'stored_name' => basename($attachment['path']),
                'file_path' => $attachment['path'],
                'file_size' => $disk->exists($attachment['path']) ? $disk->size($attachment['path']) : 0,
                'mime_type' => $disk->exists($attachment['path']) ? ($disk->mimeType($attachment['path']) ?: 'application/octet-stream') : 'application/octet-stream',
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    // 폼에는 리치에디터(content)와 일반 텍스트(content_text) 두 입력이 공존하지만 DB 컬럼은 content 하나뿐이다.
    // RichEditor는 자체 상태 캐스팅 로직이 있어 두 필드가 같은 statePath(content)를 공유하면 값이 깨지는 문제가 있어
    // 이름을 분리하고, 저장 직전에 게시판 설정에 맞는 값을 content로 병합한 뒤 content_text는 제거한다.
    public static function mergePlainContent(array $data): array
    {
        if (array_key_exists('content_text', $data)) {
            if (! self::boardUsesEditor($data['board_id'] ?? null)) {
                $data['content'] = $data['content_text'];
            }

            unset($data['content_text']);
        }

        return $data;
    }

    // replicate()는 Post 자신의 컬럼만 복사하고 첨부파일(files 관계)은 복사하지 않는다 —
    // 게시글에 딸린 PostFile까지 물리 파일 단위로 복제해야 한다. Banner::duplicateBanner()와
    // 같은 이유로 UploadService::duplicate()로 파일 자체를 새로 복사한다(복사본과 원본이 같은
    // 파일을 그대로 가리키면 둘 중 하나만 지워도 실제 파일이 사라져 나머지도 깨진다).
    private static function duplicatePost(Post $record, int|string $boardId): Post
    {
        // getEloquentQuery()가 목록 화면의 첨부/댓글 아이콘 표시를 위해 withCount(['files',
        // 'comments'])를 걸어두는데, 그 결과 $record에 files_count/comments_count가 실제
        // 컬럼이 아닌 추가 속성으로 붙는다. replicate()는 이걸 그대로 복사해버려서 save() 시
        // "Unknown column 'files_count'"로 죽었다 — 둘 다 명시적으로 제외해야 한다.
        $new = $record->replicate(['views', 'files_count', 'comments_count']);
        $new->board_id = $boardId;
        $new->category_id = null;
        $new->title = '[복사] '.$record->title;
        $new->views = 0;
        $new->is_notice = false;
        $new->is_global_notice = false;
        $new->save();

        $uploadService = app(UploadService::class);

        foreach ($record->files as $file) {
            $new->files()->create([
                'original_name' => $file->original_name,
                'stored_name' => basename($newPath = $uploadService->duplicate($file->file_path)),
                'file_path' => $newPath,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'sort_order' => $file->sort_order,
            ]);
        }

        return $new;
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
