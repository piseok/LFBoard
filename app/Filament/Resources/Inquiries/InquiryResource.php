<?php

namespace App\Filament\Resources\Inquiries;

use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Models\Inquiry;
use App\Models\Language;
use App\Services\UploadService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class InquiryResource extends Resource
{
    use HasLocaleScope;
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'inquiries';

    protected static ?string $model = Inquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = '1:1 상담';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $modelLabel = '상담';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('접수 정보')
                ->schema([
                    Placeholder::make('type')->label('유형')->content(fn (?Inquiry $record) => $record?->type),
                    Placeholder::make('name')->label('이름')->content(fn (?Inquiry $record) => $record?->name),
                    Placeholder::make('email')->label('이메일')->content(fn (?Inquiry $record) => $record?->email ?: '-'),
                    Placeholder::make('phone')->label('전화')->content(fn (?Inquiry $record) => $record?->phone ?: '-'),
                    Placeholder::make('title')->label('제목')->content(fn (?Inquiry $record) => $record?->title)->columnSpanFull(),
                    Placeholder::make('content')->label('내용')
                        ->content(fn (?Inquiry $record) => new \Illuminate\Support\HtmlString(nl2br(e($record?->content))))
                        ->columnSpanFull(),
                    Placeholder::make('file_path')->label('첨부파일')
                        ->content(fn (?Inquiry $record) => $record?->file_path
                            ? new \Illuminate\Support\HtmlString('<a href="'.url($record->file_path).'" target="_blank" class="underline text-primary-600">다운로드</a>')
                            : '-'),
                ])->columns(2),

            Section::make('답변')
                ->schema([
                    Select::make('status')->label('상태')
                        ->options(['pending' => '대기', 'processing' => '처리중', 'done' => '완료'])
                        ->required()->native(false),
                    self::richEditor('admin_reply', '답변 내용')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->label('유형')->badge(),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('name')->label('이름'),
                TextColumn::make('title')->label('제목')->limit(40),
                TextColumn::make('status')->label('상태')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => '대기', 'processing' => '처리중', 'done' => '완료', default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'danger', 'processing' => 'warning', 'done' => 'success', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('접수일')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('response_time')->label('응답 소요시간')
                    ->getStateUsing(fn (Inquiry $record): ?string => self::formatResponseTime($record->responseMinutes()))
                    ->placeholder('미답변'),
            ])
            ->filters([
                SelectFilter::make('status')->label('상태')->options(['pending' => '대기', 'processing' => '처리중', 'done' => '완료']),
                SelectFilter::make('type')->label('유형')->options(['general' => '일반', 'quick' => '퀵메뉴', 'footer' => '하단폼']),
                SelectFilter::make('locale')->label('언어')
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('영구 삭제 시 첨부파일도 함께 삭제되며 복구할 수 없습니다.')
                    ->action(function (Inquiry $record) {
                        if ($record->file_path) {
                            app(UploadService::class)->delete($record->file_path);
                        }
                        $record->forceDelete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkStatus')
                        ->label('일괄 상태 변경')
                        ->schema([
                            Select::make('status')->label('상태')
                                ->options(['pending' => '대기', 'processing' => '처리중', 'done' => '완료'])
                                ->required()->native(false),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['status' => $data['status']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription('영구 삭제 시 첨부파일도 함께 삭제되며 복구할 수 없습니다.')
                        ->action(function (Collection $records) {
                            $uploadService = app(UploadService::class);

                            foreach ($records as $record) {
                                if ($record->file_path) {
                                    $uploadService->delete($record->file_path);
                                }
                                $record->forceDelete();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
            'edit' => EditInquiry::route('/{record}/edit'),
        ];
    }

    // '응답 소요시간' 컬럼용 축약 포맷터. 1일 이상이면 "N일" 또는 "N일 N시간"(DateInterval의 ->d를
    // 쓰면 월 경계에서 잔여 일수만 남아 40일이 "9일"처럼 틀리게 나오므로, 반드시 분(int) 기반으로
    // 직접 나눠 계산한다), 1시간 이상이면 "N시간", 그 미만이면 "N분"으로 표시한다.
    private static function formatResponseTime(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        if ($days > 0) {
            return $hours > 0 ? "{$days}일 {$hours}시간" : "{$days}일";
        }

        return $hours > 0 ? "{$hours}시간" : "{$minutes}분";
    }
}
