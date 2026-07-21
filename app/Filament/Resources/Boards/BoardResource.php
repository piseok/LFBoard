<?php

namespace App\Filament\Resources\Boards;

use App\Filament\Concerns\HasLevelSelect;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Resources\Boards\Pages\CreateBoard;
use App\Filament\Resources\Boards\Pages\EditBoard;
use App\Filament\Resources\Boards\Pages\ListBoards;
use App\Filament\Resources\Boards\RelationManagers\CategoriesRelationManager;
use App\Models\Board;
use App\Models\Language;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class BoardResource extends Resource
{
    use HasLevelSelect;
    use HasPermissionCheck;

    protected static string $permissionKey = 'boards';

    // "담당 언어"(HasLocaleScope와 동일한 규칙)와 "담당 게시판" 두 스코프를 함께 적용해야 하는데,
    // 둘 다 트레이트로 분리하면 같은 이름의 getEloquentQuery()가 충돌하므로 여기서는 직접 조합한다
    // (PostResource도 board_id/locale 두 스코프를 동시에 적용해야 해서 동일한 방식).
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($localeScope = $user?->localeScope()) {
            $query->whereIn('locale', $localeScope);
        }

        if ($boardScope = $user?->boardScope()) {
            $query->whereIn('id', $boardScope);
        }

        return $query;
    }

    protected static ?string $model = Board::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = '게시판 관리';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $modelLabel = '게시판';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('board')
                ->tabs([
                    Tab::make('기본 설정')
                        ->schema([
                            TextInput::make('name')->label('게시판명')->required()->maxLength(50)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (callable $set, ?string $state) => $set('slug', Str::slug($state))),
                            TextInput::make('slug')->label('URL 주소')->required()->maxLength(50)
                                ->helperText('게시판 주소(/board/이-값)에 쓰입니다. 게시판명 입력 시 자동으로 채워지며, 영문 소문자/숫자/하이픈만 사용할 수 있습니다(한글 게시판명은 자동으로 채워지지 않으니 직접 입력해 주세요). 같은 슬러그를 다른 언어에서도 재사용할 수 있습니다(언어+슬러그 조합으로 구분).')
                                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                ->validationMessages(['regex' => 'URL 주소는 영문 소문자/숫자/하이픈만 사용할 수 있습니다(한글, 대문자, 공백, 특수문자 불가).'])
                                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, callable $get) => $rule->where('locale', $get('locale'))),
                            Select::make('locale')->label('언어')
                                ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                                ->required()->native(false)->default(fn () => Language::defaultCode()),
                            Select::make('skin')->label('스킨')->options(self::detectSkins())->native(false)->default('default')
                                ->helperText('스킨(레이아웃)은 언어와 무관하게 독립적으로 선택합니다.'),
                            Select::make('layout')->label('레이아웃')->options(['list' => '목록형', 'gallery' => '갤러리형'])->native(false)->default('list'),
                            Textarea::make('description')->label('설명')->rows(2)->columnSpanFull(),
                            Toggle::make('is_active')->label('활성 상태')->default(true),
                            Toggle::make('exclude_from_search')->label('전체검색에서 제외')
                                ->helperText('카드뉴스처럼 목록형 안내가 아닌 게시판을 통합검색 결과에서 숨기고 싶을 때 켭니다.'),
                            TextInput::make('sort_order')->label('순서')->numeric()->default(0),
                        ])->columns(2),

                    Tab::make('글쓰기 설정')
                        ->schema([
                            Toggle::make('use_editor')->label('에디터 사용')->default(true)
                                ->helperText('OFF 시 일반 텍스트(plain textarea) 입력'),
                            Toggle::make('allow_image_upload')->label('에디터 내 이미지 업로드 허용')->default(true),
                            Toggle::make('allow_file')->label('파일 첨부 허용')->default(true)->live(),
                            TextInput::make('files_per_post')->label('첨부파일 최대 개수')->numeric()->default(5)
                                ->visible(fn (callable $get) => $get('allow_file')),
                            Toggle::make('allow_anonymous')->label('비회원 작성 허용')->default(false)->live(),
                            Toggle::make('use_captcha')->label('스팸방지 적용')->default(false)
                                ->helperText('비회원 작성 시 표시. 사이트 설정에서 captcha 공급사가 설정된 경우에만 실제 동작합니다.')
                                ->visible(fn (callable $get) => $get('allow_anonymous')),
                            Toggle::make('requires_identity_verification')->label('본인인증 후 글쓰기')->default(false)->live()
                                ->disabled(fn (callable $get) => $get('locale') !== 'ko')
                                ->helperText(fn (callable $get) => $get('locale') !== 'ko'
                                    ? '본인인증(KG이니시스/NICE)은 한국 통신사 기반 서비스라 외국인은 인증 자체가 불가능합니다 — 한국어 게시판에서만 켤 수 있습니다.'
                                    : '민원게시판 등에 사용. 로그인 회원만 글쓰기가 가능해지며(비회원 작성 허용 여부와 무관), 사이트 설정에서 본인인증 공급사가 설정된 경우에만 실제 동작합니다.'),
                            Textarea::make('identity_verification_consent_text')->label('개인정보 이용 동의 문구')->rows(4)->columnSpanFull()
                                ->helperText('글쓰기 화면에 체크박스와 함께 노출됩니다. 이 게시판에서 실제로 수집·이용하는 개인정보 항목/목적을 구체적으로 적어주세요.')
                                ->visible(fn (callable $get) => $get('requires_identity_verification'))
                                ->required(fn (callable $get) => $get('requires_identity_verification')),
                        ])->columns(2),

                    Tab::make('읽기/접근 설정')
                        ->schema([
                            self::levelSelect('min_read_level', '읽기 최소 레벨', 1),
                            self::levelSelect('min_write_level', '쓰기 최소 레벨', 2),
                            self::levelSelect('min_comment_level', '댓글 최소 레벨', 1),
                            TextInput::make('per_page')->label('페이지당 글 수')->numeric()->default(15)->required(),
                            Select::make('order_by')->label('기본 정렬')
                                ->options(['latest' => '최신순', 'views' => '조회순'])
                                ->native(false)->default('latest'),
                        ])->columns(2),

                    Tab::make('댓글 설정')
                        ->schema([
                            Toggle::make('allow_comment')->label('댓글 허용')->default(true),
                            Toggle::make('allow_reply')->label('답글 허용')->default(true)
                                ->helperText('답글(대댓글)은 최대 2단계(댓글→답글)까지 고정 지원됩니다.'),
                        ])->columns(2),

                    Tab::make('커스텀 필드')
                        ->schema([
                            Repeater::make('custom_field_schema')
                                ->label('필드 목록')
                                ->default([])
                                ->schema([
                                    TextInput::make('key')->label('필드 키')->required()
                                        ->regex('/^[a-z0-9_]+$/')
                                        ->validationMessages(['regex' => '필드 키는 영문 소문자/숫자/언더스코어만 사용할 수 있습니다.'])
                                        ->helperText('저장용 내부 식별자입니다(예: company_name). 다른 필드와 겹치지 않아야 하며, 저장 후에는 바꾸지 마세요(기존 게시글 값과 연결이 끊깁니다).'),
                                    TextInput::make('label')->label('표시명')->required()
                                        ->helperText('관리자 화면과 게시판에 보여줄 이름입니다(예: 기업명).'),
                                    Select::make('type')->label('입력 방식')
                                        ->options([
                                            'text' => '한 줄 텍스트',
                                            'textarea' => '여러 줄 텍스트',
                                            'number' => '숫자',
                                            'date' => '날짜',
                                            'select' => '선택(드롭다운)',
                                            'radio' => '라디오(단일 선택)',
                                            'checkbox' => '체크박스(다중 선택)',
                                        ])
                                        ->required()->native(false)->live()->default('text'),
                                    TagsInput::make('options')->label('선택지')
                                        ->helperText('입력 후 Enter로 하나씩 추가하세요.')
                                        ->visible(fn (callable $get) => in_array($get('type'), ['select', 'radio', 'checkbox']))
                                        ->required(fn (callable $get) => in_array($get('type'), ['select', 'radio', 'checkbox'])),
                                    Toggle::make('required')->label('필수 입력'),
                                ])
                                ->columns(2)
                                ->addActionLabel('필드 추가')
                                ->reorderable()
                                ->helperText('게시판마다 자유롭게 항목을 추가/삭제할 수 있습니다. 게시글 작성 화면과 목록 검색에 자동으로 반영됩니다(예: 기업명/업종/주요제품/대표자/연락처).')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('게시판명'),
                TextColumn::make('slug')->label('URL 주소'),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('skin')->label('스킨')->badge(),
                TextColumn::make('posts_count')->label('글 수'),
                IconColumn::make('is_active')->label('활성')->boolean(),
                IconColumn::make('exclude_from_search')->label('검색제외')->boolean(),
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
                    ->action(fn (Board $record) => self::duplicateBoard($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkDuplicate')
                        ->label('일괄 복제')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(fn (Collection $records) => $records->each(fn (Board $record) => self::duplicateBoard($record)))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // slug는 (locale, slug) 조합으로 유니크해야 해서, 복제본은 원본과 같은 slug를 그대로 쓸 수
    // 없다 — "-copy", "-copy-2"... 식으로 비어있는 값을 찾을 때까지 늘려간다. 게시판 자체 설정만
    // 복제하고 소속 게시글/카테고리는 복제하지 않는다(별개의 콘텐츠라 게시판 설정 복제와는 무관).
    private static function duplicateBoard(Board $record): Board
    {
        // 이 리소스의 table()이 ->modifyQueryUsing()으로 posts_count를 붙여 가져오는데,
        // replicate()는 실제 컬럼이 아닌 이 계산된 속성까지 그대로 복사해버려서 INSERT 시
        // "존재하지 않는 컬럼" 에러가 난다 — 명시적으로 제외해야 한다.
        $new = $record->replicate(['posts_count']);
        $new->name = '[복사] '.$record->name;

        $suffix = 1;
        $slug = "{$record->slug}-copy";
        while (Board::query()->where('locale', $record->locale)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$record->slug}-copy-{$suffix}";
        }
        $new->slug = $slug;

        $new->save();

        return $new;
    }

    // Repeater는 각 항목(key)이 서로 겹치지 않아야 한다는 걸 자체적으로 검증해주지 않는다 —
    // 키가 같은 필드 2개를 등록하면 게시글 폼에서 둘 다 같은 custom_fields.{key} statePath를
    // 공유하게 되어 한쪽에 입력한 값이 다른 쪽에도 그대로 반영되는(사실상 하나로 합쳐지는)
    // 혼란스러운 동작이 생긴다. CreateBoard/EditBoard에서 저장 직전에 호출한다.
    public static function validateCustomFieldSchema(array $data): array
    {
        $keys = collect($data['custom_field_schema'] ?? [])->pluck('key')->filter();
        $duplicates = $keys->duplicates()->unique();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'custom_field_schema' => __('커스텀필드 키가 중복되었습니다: :keys', ['keys' => $duplicates->implode(', ')]),
            ]);
        }

        return $data;
    }

    // resources/views/board/skins/ 하위 디렉토리를 스캔해 선택 가능한 스킨 목록을 만든다.
    private static function detectSkins(): array
    {
        $path = resource_path('views/board/skins');

        if (! File::isDirectory($path)) {
            return ['default' => 'default'];
        }

        $dirs = collect(File::directories($path))
            ->map(fn (string $dir) => basename($dir))
            ->mapWithKeys(fn (string $dir) => [$dir => $dir])
            ->all();

        return $dirs ?: ['default' => 'default'];
    }

    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoards::route('/'),
            'create' => CreateBoard::route('/create'),
            'edit' => EditBoard::route('/{record}/edit'),
        ];
    }
}
