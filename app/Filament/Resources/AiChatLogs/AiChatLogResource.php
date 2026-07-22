<?php

namespace App\Filament\Resources\AiChatLogs;

use App\Filament\Concerns\RequiresClientOrSuperAdmin;
use App\Filament\Resources\AiChatLogs\Pages\ListAiChatLogs;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Services\AiChatService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

// 일반 관리자는 AiChatWidget(퀵메뉴)에서 자기 대화만 보고 지울 수 있다. 여기는 그와 별개로
// 최고관리자/일반 최고관리자(client)가 모든 관리자의 AI 사용 내역을 감독/삭제할 수 있는
// 화면이다("운영 관리" 그룹 — 일반관리자는 접근 불가, 게시글 임시저장의 "관리자도 못 봄"
// 규칙과는 반대로 여기는 사용자가 명시적으로 전체 열람/삭제 예외를 요청했다).
class AiChatLogResource extends Resource
{
    use RequiresClientOrSuperAdmin;

    protected static ?string $model = AiChatConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'AI 비서 사용 내역';

    protected static string|UnitEnum|null $navigationGroup = '운영 관리';

    protected static ?int $navigationSort = 65;

    protected static ?string $modelLabel = 'AI 대화';

    // AI 제공자(OpenAI/Gemini) API 키가 하나도 설정되어 있지 않으면 AiChatWidget과 마찬가지로
    // 메뉴 자체를 감춘다 — 키를 뺀 뒤에도 예전 대화 기록이 남아 있을 수 있어 조회 화면 자체를
    // 없애지는 않고(직접 URL로는 여전히 접근 가능), 평소엔 안 쓰는 기능이 메뉴에 계속 떠 있지
    // 않게만 한다.
    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperOrClientAdmin() && ! empty(app(AiChatService::class)->availableProviders());
    }

    // AiChatConversation에는 소유자별 글로벌 스코프가 없다(소유자 제한은 AiChatWidget 쪽
    // 쿼리에서만 적용됨) — 이 리소스는 RequiresClientOrSuperAdmin으로만 접근이 제한되므로
    // 기본 쿼리 그대로 전체 관리자의 대화가 보인다. TrashedFilter가 소프트삭제 기본 스코프를
    // 알아서 토글하므로 여기서 따로 건드리면 안 된다.

    // 조회/삭제 전용 화면이라 생성/수정 폼은 두지 않는다.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('관리자')->placeholder('(탈퇴한 계정)')->searchable(),
                TextColumn::make('provider')->label('제공자')->badge(),
                TextColumn::make('title')->label('대화 제목')->limit(40)->placeholder('(제목 없음)'),
                TextColumn::make('messages_count')->label('메시지 수')->counts('messages'),
                TextColumn::make('created_at')->label('시작일')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('deleted_at')->label('삭제일')->dateTime('Y-m-d H:i')->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('provider')->label('제공자')
                    ->options(['openai' => 'OpenAI', 'gemini' => 'Google Gemini']),
                SelectFilter::make('user_id')->label('관리자')
                    ->options(fn () => User::query()->where('level', User::LEVEL_ADMIN)->pluck('name', 'id')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (AiChatConversation $record) => $record->title ?: '(제목 없음)')
                    ->modalContent(fn (AiChatConversation $record) => view('filament.resources.ai-chat-logs.messages', [
                        'messages' => $record->messages()->orderBy('id')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('닫기'),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('영구 삭제 시 복구할 수 없습니다.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiChatLogs::route('/'),
        ];
    }
}
