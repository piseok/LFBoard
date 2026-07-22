<?php

namespace App\Filament\Resources\MaintenanceReports;

use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Concerns\RequiresClientOrSuperAdmin;
use App\Filament\Resources\MaintenanceReports\Pages\CreateMaintenanceReport;
use App\Filament\Resources\MaintenanceReports\Pages\EditMaintenanceReport;
use App\Filament\Resources\MaintenanceReports\Pages\ListMaintenanceReports;
use App\Models\MaintenanceReport;
use App\Services\SiteSettingService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Throwable;

class MaintenanceReportResource extends Resource
{
    use HasRichEditorDefaults;
    use RequiresClientOrSuperAdmin;

    protected static ?string $model = MaintenanceReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = '유지보수 리포트';

    // 일반 최고관리자(client)가 최고관리자에게 보고를 "보내는" 용도라 시스템 설정(최고관리자
    // 전용) 그룹이 아니라 "운영 관리" 그룹으로 분리 — 전송 대상 URL/토큰 설정(사이트 설정 >
    // 유지보수 리포트 탭)만 최고관리자 전용으로 남는다. 일반관리자(manager)는 접근 불가.
    protected static string|UnitEnum|null $navigationGroup = '운영 관리';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = '유지보수 리포트';

    // 사이트 설정 > 유지보수 리포트에 전송 대상 URL을 등록해두기 전까지는 리포트를 작성해도
    // 전송할 곳이 없으므로, AiChatLogResource/VendorNoticeResource와 같은 방식으로 그 전까지는
    // 메뉴 자체를 감춘다(직접 URL 접근은 계속 허용).
    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperOrClientAdmin()
            && filled(app(SiteSettingService::class)->get('maintenance_report_url'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('제목')->required()->maxLength(255),
            Select::make('report_type')->label('리포트 유형')
                ->options(['bug' => '버그', 'update' => '업데이트', 'feature' => '기능추가', 'notice' => '공지'])
                ->required()->native(false)->default('notice'),
            self::richEditor('content')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('제목'),
                TextColumn::make('user.name')->label('작성자'),
                TextColumn::make('report_type')->label('유형')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'bug' => '버그', 'update' => '업데이트', 'feature' => '기능추가', 'notice' => '공지', default => (string) $state,
                    }),
                IconColumn::make('is_sent')->label('전송여부')->boolean(),
                TextColumn::make('created_at')->label('작성일')->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (MaintenanceReport $record): bool => static::canEdit($record)),
                Action::make('send')
                    ->label(fn (?MaintenanceReport $record) => $record?->is_sent ? '재전송' : '전송')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation(fn (?MaintenanceReport $record) => (bool) $record?->is_sent)
                    ->modalDescription('이미 전송된 리포트입니다. 다시 전송하시겠습니까?')
                    ->action(fn (MaintenanceReport $record) => self::sendReport($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Filament 액션은 기본적으로 권한 체크를 자동으로 하지 않는다(코어 주석 참고:
                    // CanBeAuthorized.php) — authorizeIndividualRecords로 각 레코드마다 canDelete를
                    // 명시적으로 검사해야 전송된 리포트가 일괄삭제로 우회되는 것을 막을 수 있다.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (MaintenanceReport $record): bool => static::canDelete($record)),
                ]),
            ]);
    }

    // 전송된 리포트는 보고 기록 보존을 위해 일반관리자는 수정/삭제 불가 — 최고관리자는 예외적으로 항상 가능.
    // (향후 상태 변경/답변 기능이 추가될 예정이라, 전송 후에는 내용이 고정되어야 함)
    public static function canEdit(Model $record): bool
    {
        return ! $record->is_sent || (auth()->user()?->isSuperAdmin() ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->is_sent || (auth()->user()?->isSuperAdmin() ?? false);
    }

    private static function sendReport(MaintenanceReport $record): void
    {
        $settings = app(SiteSettingService::class);
        $url = $settings->get('maintenance_report_url');

        if (empty($url)) {
            Notification::make()->title('전송 대상 URL이 설정되지 않았습니다.')->danger()->send();

            return;
        }

        try {
            $response = Http::timeout(10)->post($url, [
                'token' => $settings->get('maintenance_report_token'),
                'title' => $record->title,
                'type' => $record->report_type,
                'content' => $record->content,
                'sent_at' => now()->toDateTimeString(),
                'site_name' => $settings->getLocalized('site_name', \App\Models\Language::defaultCode()),
            ]);

            $record->update([
                'is_sent' => $response->successful(),
                'sent_at' => now(),
                'send_response' => $response->status().' '.$response->body(),
            ]);

            Notification::make()
                ->title($response->successful() ? '전송되었습니다.' : '전송에 실패했습니다.')
                ->color($response->successful() ? 'success' : 'danger')
                ->send();
        } catch (Throwable $e) {
            $record->update(['is_sent' => false, 'send_response' => $e->getMessage()]);
            Notification::make()->title('전송 중 오류가 발생했습니다: '.$e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceReports::route('/'),
            'create' => CreateMaintenanceReport::route('/create'),
            'edit' => EditMaintenanceReport::route('/{record}/edit'),
        ];
    }
}
