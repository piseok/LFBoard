<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Filament\Concerns\RequiresClientOrSuperAdmin;
use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AdminAuditLogResource extends Resource
{
    use RequiresClientOrSuperAdmin;

    protected static ?string $model = AdminAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = '관리자 활동 로그';

    protected static string|UnitEnum|null $navigationGroup = '운영 관리';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = '활동 로그';

    // 조회 전용 로그이므로 생성/수정 폼은 두지 않는다.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('일시')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('admin_name')->label('관리자')->placeholder('(탈퇴한 계정)')->searchable(),
                TextColumn::make('action')->label('작업')->badge()
                    ->formatStateUsing(fn (string $state) => ['created' => '생성', 'updated' => '수정', 'deleted' => '삭제', 'access' => '접속', 'query' => 'SQL 실행', 'ai_chat' => 'AI 사용'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success', 'updated' => 'warning', 'deleted' => 'danger', 'access' => 'gray', 'query' => 'danger', 'ai_chat' => 'info', default => 'gray',
                    }),
                TextColumn::make('auditable_type')->label('대상')
                    ->formatStateUsing(fn (string $state, AdminAuditLog $record) => match ($record->action) {
                        'access' => '접속', 'query' => 'SQL', 'ai_chat' => 'AI', default => class_basename($state),
                    }),
                TextColumn::make('auditable_label')->label('제목/이름/경로/쿼리')->limit(40)->placeholder('-'),
                TextColumn::make('ip')->label('접속 IP')->placeholder('-')->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('작업')
                    ->options(['created' => '생성', 'updated' => '수정', 'deleted' => '삭제', 'access' => '접속', 'query' => 'SQL 실행', 'ai_chat' => 'AI 사용']),
                SelectFilter::make('auditable_type')->label('대상')
                    ->options(fn () => AdminAuditLog::query()->distinct()->pluck('auditable_type', 'auditable_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])),
                Filter::make('created_at')
                    ->label('기간')
                    ->schema([
                        DatePicker::make('from')->label('시작일'),
                        DatePicker::make('until')->label('종료일'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = '시작일: '.$data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = '종료일: '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('viewDetail')
                    ->label('상세보기')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalHeading('변경 내용')
                    ->modalContent(fn (AdminAuditLog $record) => new HtmlString(self::renderDiff($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('닫기'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function renderDiff(AdminAuditLog $record): string
    {
        $before = $record->before ?? [];
        $changes = $record->changes ?? [];
        $fields = array_unique([...array_keys($before), ...array_keys($changes)]);

        if (empty($fields)) {
            return '<p>세부 변경 내용이 없습니다.</p>';
        }

        $rows = '';
        foreach ($fields as $field) {
            $rows .= '<tr>'
                .'<td style="padding:6px 10px;font-weight:600;border-bottom:1px solid #e5e7eb;">'.e($field).'</td>'
                .'<td style="padding:6px 10px;color:#991b1b;border-bottom:1px solid #e5e7eb;">'.e(self::stringifyValue($before[$field] ?? null)).'</td>'
                .'<td style="padding:6px 10px;color:#166534;border-bottom:1px solid #e5e7eb;">'.e(self::stringifyValue($changes[$field] ?? null)).'</td>'
                .'</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">'
            .'<thead><tr>'
            .'<th style="text-align:left;padding:6px 10px;">필드</th>'
            .'<th style="text-align:left;padding:6px 10px;">변경 전</th>'
            .'<th style="text-align:left;padding:6px 10px;">변경 후</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private static function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAuditLogs::route('/'),
        ];
    }
}
