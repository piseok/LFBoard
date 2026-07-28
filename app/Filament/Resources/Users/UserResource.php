<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Board;
use App\Models\Language;
use App\Models\User;
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
use Filament\Forms\Components\CheckboxList;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    use HasPermissionCheck;

    protected static string $permissionKey = 'users';

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = '회원 관리';

    protected static string|UnitEnum|null $navigationGroup = '회원 관리';

    protected static ?string $modelLabel = '회원';

    // 일반 최고관리자(client)는 "회원관리" 체크박스 없이도 항상 접근 가능 — 일반관리자 계정을
    // 만들고 관리하는 것 자체가 이 역할의 존재 이유라, 체크박스를 깜빡해도 막히면 안 된다.
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || $user->level !== User::LEVEL_ADMIN) {
            return false;
        }

        if ($user->isClientAdmin()) {
            return true;
        }

        return $user->hasAdminPermission(static::$permissionKey);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('기본 정보')->schema([
                TextInput::make('name')->label('이름')->required()->maxLength(50),
                TextInput::make('email')->label('이메일')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('비밀번호')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('수정 시 비워두면 기존 비밀번호가 유지됩니다.')
                    ->visible(fn (?Model $record) => static::canEditPassword($record)),
                Select::make('level')
                    ->label('레벨')
                    ->options([
                        User::LEVEL_GUEST => '비회원',
                        User::LEVEL_MEMBER => '일반회원',
                        User::LEVEL_VERIFIED => '정회원',
                        User::LEVEL_ADMIN => '관리자',
                    ])
                    ->required()
                    ->live()
                    ->default(User::LEVEL_MEMBER),
                TextInput::make('phone')->label('전화번호')->maxLength(20),
                Textarea::make('memo')->label('관리자 메모')->rows(3),
                Toggle::make('is_active')->label('활성 상태')->default(true),
            ])->columns(2),

            Section::make('관리자 권한')
                ->schema([
                    Select::make('admin_role')
                        ->label('관리자 역할')
                        ->options(fn () => static::allowedAdminRoleOptions())
                        ->native(false)
                        ->live(),
                    CheckboxList::make('admin_permissions')
                        ->label('페이지별 접근 권한')
                        ->options([
                            'users' => '회원관리',
                            'boards' => '게시판관리',
                            'posts' => '게시글관리',
                            'pages' => '콘텐츠페이지',
                            'popups' => '팝업',
                            'banners' => '배너',
                            'inquiries' => '1:1상담',
                            'email_templates' => '이메일템플릿',
                            'media' => '미디어',
                            'marketing_mail' => '마케팅메일',
                            'visit_stats' => '방문자통계',
                            'ai_assistant' => 'AI 비서',
                        ])
                        ->helperText('시스템 설정 그룹(메뉴관리, 금지어, 사이트 설정 등)은 위험도가 높아 일반관리자·일반 최고관리자에게 부여할 수 없으며, 슈퍼관리자만 접근할 수 있습니다. "운영 관리" 그룹(약관/방침, 관리자 활동로그, AI 대화로그, 유지보수 리포트)은 일반 최고관리자에게 항상 자동으로 열려 있습니다.')
                        ->columns(2)
                        ->visible(fn (callable $get) => in_array($get('admin_role'), ['manager', 'client'], true)),
                    Select::make('admin_locale_scope')
                        ->label('담당 언어')
                        ->multiple()
                        ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                        ->native(false)
                        ->helperText('선택한 언어의 콘텐츠만 위 권한이 허용된 화면(게시판/페이지/배너/팝업/1:1상담/이메일템플릿 등)에서 보고 관리할 수 있습니다. 비워두면 모든 언어에 접근합니다.')
                        ->visible(fn (callable $get) => in_array($get('admin_role'), ['manager', 'client'], true)),
                    Select::make('admin_board_scope')
                        ->label('담당 게시판')
                        ->multiple()
                        ->options(fn () => Board::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->native(false)
                        ->searchable()
                        ->helperText('선택한 게시판의 글만 "게시판관리"/"게시글관리" 화면에서 보고 관리할 수 있습니다. 비워두면(위 담당 언어 조건을 만족하는) 모든 게시판에 접근합니다.')
                        ->visible(fn (callable $get) => in_array($get('admin_role'), ['manager', 'client'], true)),
                ])
                ->visible(fn (callable $get) => (int) $get('level') === User::LEVEL_ADMIN),
        ]);
    }

    // 최고관리자(또는 admin_role 미지정 레거시)만 슈퍼관리자·일반 최고관리자 역할을 부여할 수
    // 있다 — 일반 최고관리자(client)는 일반관리자(manager) 역할까지만 부여 가능("최고관리자
    // 권한은 못 주고 일반관리자 권한만 줄 수 있는" 계정을 클라이언트에게 전달하기 위함).
    // Select의 options()뿐 아니라 Create/EditUser 페이지의 mutateFormDataBefore*에서도
    // 이 목록으로 제출값을 검증해야 폼 조작으로 우회되지 않는다.
    public static function allowedAdminRoleOptions(): array
    {
        $actingUser = auth()->user();

        if ($actingUser && ($actingUser->admin_role === 'super' || is_null($actingUser->admin_role))) {
            return [
                'super' => '슈퍼관리자 — 모든 페이지 접근',
                'client' => '일반 최고관리자 — 회원/일반관리자 관리 및 운영 로그 열람',
                'manager' => '일반관리자 — 아래 권한 설정 기준으로 접근',
            ];
        }

        return [
            'manager' => '일반관리자 — 아래 권한 설정 기준으로 접근',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('이름')->searchable(),
                TextColumn::make('email')->label('이메일')->searchable(),
                TextColumn::make('level')
                    ->label('레벨')
                    ->badge()
                    ->formatStateUsing(fn (?User $record): string => $record ? static::levelLabel($record) : '')
                    ->color(fn (?User $record): string => $record ? static::levelColor($record) : 'gray'),
                TextColumn::make('created_at')->label('가입일')->date('Y-m-d'),
                TextColumn::make('last_login_at')->label('최근 로그인')->dateTime('Y-m-d H:i')->placeholder('-'),
                IconColumn::make('is_active')->label('활성')->boolean(),
                IconColumn::make('dormant_at')->label('휴면')->boolean()->getStateUsing(fn (User $record): bool => $record->isDormant()),
            ])
            ->filters([
                SelectFilter::make('level')->label('레벨')->options([
                    User::LEVEL_GUEST => '비회원',
                    User::LEVEL_MEMBER => '일반회원',
                    User::LEVEL_VERIFIED => '정회원',
                    User::LEVEL_ADMIN => '관리자',
                ]),
                TernaryFilter::make('is_active')->label('활성 상태'),
                TernaryFilter::make('dormant_at')->label('휴면 상태')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('dormant_at'),
                        false: fn ($query) => $query->whereNull('dormant_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                // 2FA는 본인 인증 앱으로만 "켤" 수 있어(다른 사람이 대신 켜줄 방법이 없음) 관리자가
                // 할 수 있는 건 "끄기(초기화)"뿐이다 — 인증 앱을 분실해 로그인이 막힌 계정을 풀어줄
                // 때 쓴다. 본인 계정은 프로필 화면에서 직접 끄도록 여기서는 제외한다.
                Action::make('resetTwoFactor')
                    ->label('2FA 초기화')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id() && filled($record->two_factor_secret) && static::canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription('이 계정의 2단계 인증(2FA)을 초기화합니다. 다음 로그인부터 인증 앱 없이 로그인할 수 있으며, 필요하면 본인이 다시 설정해야 합니다.')
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);

                        Notification::make()->title('2FA가 초기화되었습니다.')->success()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                RestoreAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                ForceDeleteAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription('영구 삭제 시 복구할 수 없습니다.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Filament 액션/벌크액션은 기본적으로 canEdit()/canDelete()를 자동으로 검사하지
                    // 않는다 — authorizeIndividualRecords로 명시해야 일반 최고관리자(client)가
                    // 슈퍼관리자 계정을 일괄 삭제/비활성화/레벨변경으로 건드리는 것을 막을 수 있다.
                    BulkAction::make('activate')
                        ->label('활성화')
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record))
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('비활성화')
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record))
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changeLevel')
                        ->label('레벨 일괄 변경')
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record))
                        ->schema([
                            Select::make('level')->label('레벨')->options([
                                User::LEVEL_GUEST => '비회원',
                                User::LEVEL_MEMBER => '일반회원',
                                User::LEVEL_VERIFIED => '정회원',
                            ])->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['level' => $data['level']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record)),
                    RestoreBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record)),
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => static::canEdit($record)),
                ]),
            ]);
    }

    public static function levelLabel(User $user): string
    {
        if ($user->level === User::LEVEL_ADMIN) {
            return match ($user->admin_role) {
                'manager' => '일반관리자',
                'client' => '일반 최고관리자',
                default => '슈퍼관리자',
            };
        }

        return match ($user->level) {
            User::LEVEL_MEMBER => '일반회원',
            User::LEVEL_VERIFIED => '정회원',
            default => '비회원',
        };
    }

    public static function levelColor(User $user): string
    {
        if ($user->level === User::LEVEL_ADMIN) {
            return match ($user->admin_role) {
                'manager' => 'warning',
                'client' => 'info',
                default => 'danger',
            };
        }

        return 'gray';
    }

    // 슈퍼관리자(또는 admin_role 미지정)만 계정을 새로 생성할 수 있다 — 일반 최고관리자(client)도
    // 예외적으로 가능(자신이 관리할 일반관리자 계정을 직접 만들어야 하므로). 일반관리자는 계정
    // 생성 불가(Filament 코어는 Policy가 없으면 canCreate를 기본 허용하므로 명시적으로 막아야 한다).
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->admin_role === 'super' || is_null($user->admin_role) || $user->admin_role === 'client');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // 슈퍼관리자(또는 admin_role 미지정)는 모든 계정 수정 가능
        if ($user->admin_role === 'super' || is_null($user->admin_role)) {
            return true;
        }

        // 일반 최고관리자(client)는 본인 계정 + 일반관리자(manager) 계정까지 관리 가능
        // (슈퍼관리자·다른 일반 최고관리자 계정은 수정 불가)
        if ($user->admin_role === 'client') {
            return $user->id === $record->getKey()
                || ($record instanceof User && $record->level === User::LEVEL_ADMIN && $record->admin_role === 'manager');
        }

        // 일반관리자는 본인 계정만 수정 가능
        return $user->id === $record->getKey();
    }

    // 삭제/복구/영구삭제 권한도 수정 권한과 동일한 범위로 묶는다(별도 정책 없이는 Filament
    // 코어가 canDelete를 기본 허용하므로, canEdit로 이미 검증된 범위를 그대로 재사용).
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    // 슈퍼관리자 계정의 비밀번호는 슈퍼관리자 본인 또는 다른 슈퍼관리자만 변경 가능
    public static function canEditPassword(?Model $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record instanceof User) {
            return true;
        }

        if ($record->level === User::LEVEL_ADMIN && ($record->admin_role === 'super' || is_null($record->admin_role))) {
            return $user->id === $record->id || $user->admin_role === 'super' || is_null($user->admin_role);
        }

        if ($user->admin_role === 'super' || is_null($user->admin_role) || $user->id === $record->id) {
            return true;
        }

        // 일반 최고관리자(client)는 일반관리자(manager) 계정의 비밀번호도 변경 가능
        return $user->admin_role === 'client'
            && $record->level === User::LEVEL_ADMIN
            && $record->admin_role === 'manager';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
