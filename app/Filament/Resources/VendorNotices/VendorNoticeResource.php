<?php

namespace App\Filament\Resources\VendorNotices;

use App\Filament\Concerns\RequiresClientOrSuperAdmin;
use App\Filament\Resources\VendorNotices\Pages\ListVendorNotices;
use App\Models\VendorNotice;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

// 관리업체(모회사)가 중앙에서 배포하는 공지사항을 읽기 전용으로 보여준다. 실제 데이터는
// VendorNoticeSyncService가 SiteSettings > 관리업체 공지사항에 설정된 API를 폴링해 채운다
// (SyncVendorNotices 미들웨어가 관리자 접속 시 1시간에 한 번 자동 실행) — 이 화면에서는 조회와
// 수동 새로고침만 가능하고, 삭제는 목록 정리 목적으로 최고관리자에게만 예외로 열어둔다.
class VendorNoticeResource extends Resource
{
    use RequiresClientOrSuperAdmin;

    protected static ?string $model = VendorNotice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = '관리업체 공지사항';

    protected static string|UnitEnum|null $navigationGroup = '운영 관리';

    protected static ?int $navigationSort = 91;

    protected static ?string $modelLabel = '관리업체 공지';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('제목')
                    ->url(fn (VendorNotice $record) => $record->url, shouldOpenInNewTab: true)
                    ->color(fn (VendorNotice $record) => $record->url ? 'primary' : null),
                TextColumn::make('published_at')->label('발행일')->dateTime('Y-m-d H:i')->placeholder('-'),
                TextColumn::make('created_at')->label('수신일')->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                ]),
            ]);
    }

    // 목록을 열람하는 순간 "지금까지의 최신 공지"까지 확인한 것으로 간주해 네비게이션 배지를
    // 지운다 — 개별 항목 읽음 처리가 아니라 사이트 알림벨과 달리 여러 관리자가 공유하는
    // 화면이 아닌, 관리자 개인별(user.vendor_notice_last_seen_id) 확인 상태이기 때문에 다른
    // 관리자가 안 봤다면 그 관리자에게는 계속 배지가 보인다.
    public static function markSeenForCurrentUser(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $latestId = VendorNotice::max('id');
        if ($latestId && $latestId > ($user->vendor_notice_last_seen_id ?? 0)) {
            $user->update(['vendor_notice_last_seen_id' => $latestId]);
        }
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $count = VendorNotice::where('id', '>', $user->vendor_notice_last_seen_id ?? 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorNotices::route('/'),
        ];
    }
}
