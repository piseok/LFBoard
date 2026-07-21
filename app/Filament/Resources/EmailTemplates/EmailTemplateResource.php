<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Services\HtmlSanitizerService;
use BackedEnum;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    use HasLocaleScope;
    use HasPermissionCheck;
    use HasRichEditorDefaults;

    protected static string $permissionKey = 'email_templates';

    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = '이메일 템플릿';

    protected static string|UnitEnum|null $navigationGroup = '마케팅';

    protected static ?string $modelLabel = '이메일 템플릿';

    // type별 사용 가능한 변수 목록
    public const VARIABLES = [
        'welcome' => ['site_name', 'user_name', 'user_email'],
        'email_verification' => ['site_name', 'user_name', 'verification_url'],
        'inquiry_received_admin' => ['site_name', 'inquiry_name', 'inquiry_email', 'inquiry_phone', 'inquiry_title', 'inquiry_category', 'inquiry_type', 'inquiry_content', 'admin_url'],
        'inquiry_received_user' => ['site_name', 'inquiry_name', 'inquiry_title', 'inquiry_category'],
        'inquiry_reply' => ['site_name', 'inquiry_name', 'inquiry_title', 'reply_content'],
        'password_reset' => ['site_name', 'user_name', 'reset_url'],
        'marketing_broadcast' => ['site_name', 'user_name', 'unsubscribe_url', 'content'],
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('템플릿 이름')->disabled()->dehydrated(false),
                    TextInput::make('type')->label('type')->disabled()->dehydrated(false),
                    TextInput::make('locale')->label('언어')->disabled()->dehydrated(false),
                    Toggle::make('is_active')->label('활성화'),
                    TextInput::make('subject')->label('이메일 제목')->required()->maxLength(255)->columnSpanFull(),
                    Placeholder::make('variables_hint')
                        ->label('사용 가능한 변수')
                        ->columnSpanFull()
                        ->content(fn (?EmailTemplate $record) => new HtmlString(
                            collect(self::VARIABLES[$record?->type] ?? [])
                                ->map(fn (string $v) => "<code>{{{$v}}}</code>")
                                ->implode(' ')
                        )),
                    self::richEditor('body', '이메일 본문')->required()->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('템플릿 이름'),
                TextColumn::make('type')->label('type')->badge(),
                TextColumn::make('locale')->label('언어')->badge(),
                IconColumn::make('is_active')->label('활성 상태')->boolean(),
                TextColumn::make('updated_at')->label('수정일')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('locale')->label('언어')
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('preview')
                    ->label('미리보기')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('이메일 미리보기')
                    ->modalContent(fn (EmailTemplate $record) => view('filament.pages.email-template-preview', [
                        'subject' => self::renderDummy($record->subject, $record->type),
                        'body' => app(HtmlSanitizerService::class)->clean(self::renderDummy($record->body, $record->type)),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('닫기'),
                Action::make('resetToDefault')
                    ->label('기본값으로 초기화')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('이 템플릿을 기본 제공 내용으로 되돌립니다. 현재 내용은 사라집니다.')
                    ->action(function (EmailTemplate $record): void {
                        $allDefaults = collect(EmailTemplateSeeder::definitions())
                            ->map(fn (array $d) => [...$d, 'locale' => $d['locale'] ?? 'ko'])
                            ->concat(EmailTemplateSeeder::localizedDefinitions());

                        $default = $allDefaults->first(fn (array $d) => $d['type'] === $record->type && $d['locale'] === $record->locale);

                        if ($default) {
                            $record->update([
                                'name' => $default['name'],
                                'subject' => $default['subject'],
                                'body' => $default['body'],
                            ]);
                        }

                        Notification::make()->title('기본값으로 초기화되었습니다.')->success()->send();
                    }),
            ]);
    }

    private static function renderDummy(string $template, string $type): string
    {
        $dummy = [
            'site_name' => 'LFboard',
            'user_name' => '홍길동',
            'user_email' => 'user@example.com',
            'verification_url' => url('/#verification-link'),
            'reset_url' => url('/#reset-link'),
            'unsubscribe_url' => url('/#unsubscribe-link'),
            'admin_url' => url('/admin'),
            'inquiry_name' => '홍길동',
            'inquiry_email' => 'user@example.com',
            'inquiry_phone' => '010-1234-5678',
            'inquiry_title' => '샘플 문의 제목',
            'inquiry_category' => '일반문의',
            'inquiry_type' => 'general',
            'inquiry_content' => '샘플 문의 내용입니다.',
            'reply_content' => '샘플 답변 내용입니다.',
            'content' => '샘플 본문 내용입니다.',
        ];

        $rendered = $template;
        foreach (self::VARIABLES[$type] ?? [] as $key) {
            $rendered = str_replace('{{'.$key.'}}', $dummy[$key] ?? '', $rendered);
        }

        return $rendered;
    }

    // 기본 템플릿은 삭제/추가 불가 (7종 고정) — CreateAction, DeleteAction을 등록하지 않는 것으로 비활성화
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
