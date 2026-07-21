<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Concerns\HasLevelSelect;
use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Board;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Page as PageModel;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MenuResource extends Resource
{
    use HasLevelSelect;
    use HasLocaleScope;
    use RequiresSuperAdmin;

    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = '메뉴 관리';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '메뉴';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('상위 메뉴를 먼저 생성한 후 하위 메뉴를 추가하세요. 최대 3단계까지 지원합니다.')
                ->schema([
                    TextInput::make('title')->label('메뉴명')->required()->maxLength(50),
                    Select::make('locale')->label('언어')
                        ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                        ->required()->native(false)->default(fn () => Language::defaultCode())->live()
                        ->helperText('메뉴명 자체가 언어별로 별도 저장됩니다. 상위 메뉴/게시판/페이지 선택 목록도 이 언어에 맞게 필터링됩니다.'),
                    Select::make('parent_id')
                        ->label('상위 메뉴')
                        ->options(function (?Menu $record, callable $get) {
                            return Menu::query()
                                ->where('locale', $get('locale'))
                                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                                ->get()
                                ->filter(fn (Menu $menu) => $menu->depth < 2) // depth 2는 이미 3단계이므로 더 이상 하위를 가질 수 없음
                                ->mapWithKeys(fn (Menu $menu) => [$menu->id => str_repeat('— ', $menu->depth).$menu->title]);
                        })
                        ->native(false)
                        ->live(),
                    Select::make('type')
                        ->label('타입')
                        ->options([
                            'url' => 'URL',
                            'board' => '게시판',
                            'page' => '페이지',
                            'none' => '없음 (그룹 텍스트)',
                        ])
                        ->required()
                        ->native(false)
                        ->live()
                        ->default('url'),

                    TextInput::make('url')->label('URL')
                        ->visible(fn (callable $get) => $get('type') === 'url'),
                    Select::make('target')->label('열기 방식')
                        ->options(['_self' => '현재 창', '_blank' => '새 창'])
                        ->default('_self')
                        ->native(false)
                        ->visible(fn (callable $get) => $get('type') === 'url'),

                    Select::make('target_id')->label('게시판 선택')
                        ->options(fn (callable $get) => Board::query()->where('is_active', true)->where('locale', $get('locale'))->pluck('name', 'id'))
                        ->native(false)
                        ->visible(fn (callable $get) => $get('type') === 'board'),

                    Select::make('target_id')->label('페이지 선택')
                        ->options(fn (callable $get) => PageModel::query()->where('is_active', true)->where('locale', $get('locale'))->pluck('title', 'id'))
                        ->native(false)
                        ->visible(fn (callable $get) => $get('type') === 'page'),

                    self::levelSelect('min_level', '최소 접근 레벨', 1),
                    Select::make('access_mode')->label('레벨 미달 시 처리')
                        ->options([
                            'hidden' => '메뉴 숨김',
                            'locked' => '메뉴는 보이되 접근 제한(잠금 표시)',
                        ])
                        ->helperText('"접근 제한"을 선택해도 실제 접근 차단은 연결된 게시판/페이지 자체의 접근 레벨 설정을 따릅니다.')
                        ->native(false)->default('hidden')->required(),
                    TextInput::make('sort_order')->label('순서')->numeric()->default(0),
                    Toggle::make('is_active')->label('활성 상태')->default(true),
                    Toggle::make('hidden_from_header')->label('전체메뉴(헤더)에서 숨김')
                        ->onIcon(Heroicon::OutlinedEyeSlash)->offIcon(Heroicon::OutlinedEye)
                        ->helperText(
                            '켜두면 헤더 상단 메뉴/모바일 전체메뉴에는 이 메뉴가 보이지 않습니다. 다만 방문자가 이 메뉴(또는 '.
                            '하위 메뉴)가 연결된 페이지에 들어가면 그 페이지의 로컬 메뉴(LNB)에는 여전히 표시됩니다 — 예: '.
                            '"마이페이지"는 헤더에는 이미 별도 링크가 있으니 여기서는 켜서 숨기고, 마이페이지 하위 페이지에서만 '.
                            'LNB로 정보수정/수강내역 등을 보여주는 식으로 씁니다.'
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $orderedIds = self::hierarchicalOrder();

                if (empty($orderedIds)) {
                    return $query;
                }

                $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));

                return $query->orderByRaw("FIELD(id, {$placeholders})", $orderedIds);
            })
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('메뉴명')
                    ->formatStateUsing(fn (?Menu $record, string $state): string => $record ? str_repeat('　', $record->depth * 2).$state : $state),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('type')->label('타입')->badge(),
                TextColumn::make('min_level')->label('최소레벨'),
                IconColumn::make('is_active')->label('활성')->boolean()
                    ->action(fn (Menu $record) => $record->update(['is_active' => ! $record->is_active])),
                IconColumn::make('hidden_from_header')->label('헤더 숨김')->boolean(),
                TextColumn::make('sort_order')->label('순서'),
            ])
            ->filters([
                SelectFilter::make('locale')->label('언어')
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(fn (Menu $record) => $record->children()->exists()
                        ? '하위 메뉴가 있는 항목입니다. 삭제하면 하위 메뉴는 삭제되지 않고 최상위 메뉴로 승격됩니다. 계속하시겠습니까?'
                        : '이 메뉴를 삭제하시겠습니까?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // 부모→자식 순서로 재귀 정렬한 ID 목록을 만든다. 트리 형태로 읽히도록
    // (부모 바로 아래에 그 자식들이 이어서 표시됨) 목록 순서를 결정하는 데 사용된다.
    private static function hierarchicalOrder(): array
    {
        $menus = Menu::query()->orderBy('sort_order')->get(['id', 'parent_id']);
        $ordered = [];

        $walk = function ($parentId) use (&$walk, &$ordered, $menus) {
            foreach ($menus->where('parent_id', $parentId) as $menu) {
                $ordered[] = $menu->id;
                $walk($menu->id);
            }
        };

        $walk(null);

        return $ordered;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
