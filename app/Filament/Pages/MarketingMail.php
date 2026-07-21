<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Models\EmailTemplate;
use App\Models\MarketingMailLog;
use App\Models\User;
use App\Services\EmailService;
use BackedEnum;
use UnitEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MarketingMail extends Page
{
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'marketing_mail';

    protected string $view = 'filament.pages.marketing-mail';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = '마케팅 메일 발송';

    protected static string|UnitEnum|null $navigationGroup = '마케팅';

    protected static ?string $title = '마케팅 메일 발송';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('발송 대상: 마케팅 수신 동의 + 활성 회원 '.$this->recipientCount().'명')
                    ->weight('bold'),
                TextInput::make('subject')->label('제목')->required()->maxLength(255),
                self::richEditor('content', '본문')->required()->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function recipientCount(): int
    {
        return User::query()->where('marketing_agreed', true)->where('is_active', true)->whereNotNull('unsubscribe_token')->count();
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $emailService = app(EmailService::class);

        // marketing_broadcast 템플릿은 발송 시점에 관리자가 입력한 제목을 사용한다.
        EmailTemplate::where('type', 'marketing_broadcast')->update(['subject' => $data['subject']]);

        $recipients = User::query()
            ->where('marketing_agreed', true)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $user) {
            $sent = $emailService->send('marketing_broadcast', $user->email, [
                'user_name' => $user->name,
                'content' => $data['content'],
                'unsubscribe_url' => url('/unsubscribe/'.$user->unsubscribe_token),
            ]);

            $sent ? $sentCount++ : $failedCount++;
        }

        MarketingMailLog::create([
            'subject' => $data['subject'],
            'content' => $data['content'],
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'sent_by' => auth()->id(),
            'sent_at' => now(),
        ]);

        Notification::make()
            ->title("발송 완료: 성공 {$sentCount}건, 실패 {$failedCount}건")
            ->success()
            ->send();

        $this->form->fill();
    }
}
