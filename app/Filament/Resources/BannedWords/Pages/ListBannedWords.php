<?php

namespace App\Filament\Resources\BannedWords\Pages;

use App\Filament\Resources\BannedWords\BannedWordResource;
use App\Models\BannedWord;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBannedWords extends ListRecords
{
    protected static string $resource = BannedWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkAdd')
                ->label('일괄 등록')
                ->icon('heroicon-o-queue-list')
                ->schema([
                    Textarea::make('words')
                        ->label('금지 단어 목록')
                        ->helperText('줄바꿈으로 여러 단어를 구분해서 입력하세요.')
                        ->rows(8)
                        ->required(),
                    Select::make('type')
                        ->label('적용 유형')
                        ->options([
                            'username' => '아이디',
                            'nickname' => '닉네임',
                            'content' => '게시글/댓글',
                            'all' => '전체',
                        ])
                        ->required()
                        ->native(false)
                        ->default('all'),
                ])
                ->action(function (array $data): void {
                    $words = collect(preg_split('/\r\n|\r|\n/', (string) $data['words']))
                        ->map(fn (string $w) => trim($w))
                        ->filter()
                        ->unique();

                    foreach ($words as $word) {
                        BannedWord::firstOrCreate(['word' => $word, 'type' => $data['type']]);
                    }

                    Notification::make()
                        ->title($words->count().'개 단어를 등록했습니다.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
