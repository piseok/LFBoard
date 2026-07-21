<?php

namespace App\Filament\Resources\Policies;

use App\Filament\Concerns\HasLocaleScope;
use App\Filament\Concerns\HasRichEditorDefaults;
use App\Filament\Concerns\RequiresClientOrSuperAdmin;
use App\Filament\Resources\Policies\Pages\CreatePolicy;
use App\Filament\Resources\Policies\Pages\EditPolicy;
use App\Filament\Resources\Policies\Pages\ListPolicies;
use App\Models\Language;
use App\Models\Policy;
use App\Services\UploadService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class PolicyResource extends Resource
{
    use HasLocaleScope;
    use HasRichEditorDefaults;
    use RequiresClientOrSuperAdmin;

    protected static ?string $model = Policy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = '약관/방침';

    protected static string|UnitEnum|null $navigationGroup = '운영 관리';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = '약관/방침';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('유형')
                ->options(['terms' => '이용약관', 'privacy' => '개인정보처리방침', 'marketing' => '마케팅 수신동의', 'email_notice' => '이메일무단수집거부'])
                ->required()->native(false)
                ->disabled(fn (?Policy $record) => $record !== null)
                ->dehydrated(fn (?Policy $record) => $record === null)
                ->helperText('생성 후에는 유형을 변경할 수 없습니다.'),
            Select::make('locale')->label('언어')
                ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code'))
                ->required()->native(false)->default(fn () => Language::defaultCode())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, callable $get) => $rule->where('type', $get('type')))
                ->helperText('같은 유형(이용약관 등)이라도 언어별로 별도 문서를 둘 수 있습니다. 해당 언어 문서가 없으면 기본 언어(한국어) 문서로 자동 대체됩니다.'),
            TextInput::make('title')->label('제목')->required()->maxLength(100),
            TextInput::make('version')->label('버전')->placeholder('예: 2025.01.01'),
            Toggle::make('is_required')->label('필수 동의 여부'),
            Toggle::make('is_active')->label('활성 상태'),
            ...self::contentTypeFields('content_type', 'content', 'html_file_path', '내용'),

            Section::make('사전고지(예약 변경)')
                ->description(
                    '지금 즉시 위 내용을 바꾸는 대신, 정해진 시행일부터 적용되도록 예약합니다. 시행일 전까지는 '.
                    '현재 내용이 그대로 노출되고, 전체 페이지 상단에 예고 배너와 변경 전/후 비교 페이지가 자동으로 표시됩니다. '.
                    '시행일이 되면 자동으로 위 내용이 교체되고 재동의가 요구됩니다. '.
                    '개인정보보호법·정보통신망법 기준: 일반적인 변경은 시행 최소 7일 전, 이용자 권리에 불리한 중대한 변경은 '.
                    '최소 30일 전에 고지해야 하니 시행일을 그만큼 여유있게 잡으세요.'
                )
                ->schema([
                    DateTimePicker::make('effective_at')->label('시행 예정일시')->native(false),
                    TextInput::make('pending_version')->label('변경될 버전')->placeholder('예: 2026.08.01'),
                    TextInput::make('pending_title')->label('변경될 제목')->maxLength(100)
                        ->helperText('비워두면 제목은 그대로 유지됩니다.'),
                    ...self::contentTypeFields('pending_content_type', 'pending_content', 'pending_html_file_path', '변경될 내용'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('변경 안내 메일')
                ->description(
                    '켜면 저장과 동시에(관리자 승인 등 별도 절차 없이 즉시) 활성 회원 전체에게 안내 메일을 발송합니다. '.
                    '변경 전/후 내용 비교가 자동으로 메일에 포함됩니다. 실제 발송은 "사이트 설정 > 이메일 설정"에서 '.
                    'SMTP와 "약관/방침 변경 안내" 항목이 모두 켜져 있어야 동작합니다.'
                )
                ->schema([
                    // 세 필드 모두 Policy 모델의 실제 컬럼이 아니다 — EditPolicy::mutateFormDataBeforeSave()에서
                    // 값을 읽어 메일 발송에 사용한 뒤 $data에서 제거하고 저장한다(그대로 두면 존재하지 않는
                    // 컬럼이라 저장 시 SQL 오류가 난다).
                    Toggle::make('send_notice')->label('변경 안내 메일 발송')->live(),
                    TextInput::make('notice_subject')->label('메일 제목')->maxLength(255)
                        ->visible(fn (callable $get) => (bool) $get('send_notice'))
                        ->required(fn (callable $get) => (bool) $get('send_notice')),
                    self::richEditor('notice_message', '안내 메시지')
                        ->visible(fn (callable $get) => (bool) $get('send_notice'))
                        ->required(fn (callable $get) => (bool) $get('send_notice'))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    // PageResource의 content_type(editor/html_file) 패턴을 그대로 재사용 — 약관도 회사 법무팀에서
    // 이미 만들어둔 HTML/PDF-변환 문서를 그대로 올려야 하는 경우가 있어서 필요해졌다. content/pending_content
    // 양쪽에서 똑같은 필드 묶음을 써서 여기 하나로 뺐다.
    //
    // @return array<int, Component>
    private static function contentTypeFields(string $typeField, string $contentField, string $fileField, string $label): array
    {
        $inputModeField = "{$fileField}_input_mode";

        return [
            Select::make($typeField)->label("{$label} 타입")
                ->options(['editor' => '에디터', 'html_file' => 'HTML 파일 업로드'])
                ->required()->native(false)->default('editor')->live(),
            self::richEditor($contentField, $label)->columnSpanFull()
                ->visible(fn (callable $get) => $get($typeField) === 'editor'),

            Select::make($inputModeField)->label('입력 방식')
                ->options(['upload' => '파일 업로드', 'manual' => '서버 경로 직접 입력'])
                ->helperText('이미 서버에 올려둔 파일을 그대로 연결하려면 "서버 경로 직접 입력"을 선택하세요.')
                ->default('upload')->native(false)->live()->dehydrated(false)
                ->afterStateHydrated(function (Select $component, ?Policy $record) use ($typeField, $fileField) {
                    if ($record && $record->{$typeField} === 'html_file') {
                        $component->state(
                            str_starts_with((string) $record->{$fileField}, 'uploads/policies/') ? 'upload' : 'manual'
                        );
                    }
                })
                ->visible(fn (callable $get) => $get($typeField) === 'html_file'),

            FileUpload::make($fileField)->label('HTML 파일')->disk('uploads')
                ->acceptedFileTypes(['text/html'])
                ->visible(fn (callable $get) => $get($typeField) === 'html_file' && $get($inputModeField) !== 'manual')
                ->dehydrated(fn (callable $get) => $get($inputModeField) !== 'manual')
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'policies'))
                ->deleteUploadedFileUsing(fn (string $file) => app(UploadService::class)->delete($file)),
            TextInput::make($fileField)->label('서버 경로')
                ->placeholder('uploads/policies/custom.html')
                ->helperText('public/ 기준 상대 경로를 입력합니다.')
                ->visible(fn (callable $get) => $get($typeField) === 'html_file' && $get($inputModeField) === 'manual')
                ->dehydrated(fn (callable $get) => $get($inputModeField) === 'manual'),

            Placeholder::make("{$fileField}_preview")->label('현재 파일 경로')
                ->content(fn (?Policy $record) => $record?->{$fileField}
                    ? new HtmlString('<code>'.e($record->{$fileField}).'</code> — <a href="'.url($record->{$fileField}).'" target="_blank" rel="noopener" class="underline">새 창에서 열기</a>')
                    : '아직 파일이 없습니다.')
                ->columnSpanFull()
                ->visible(fn (callable $get) => $get($typeField) === 'html_file'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('유형')->badge(),
                TextColumn::make('locale')->label('언어')->badge(),
                TextColumn::make('title')->label('제목'),
                IconColumn::make('is_required')->label('필수여부')->boolean(),
                TextColumn::make('version')->label('버전'),
                IconColumn::make('is_active')->label('활성 상태')->boolean(),
                TextColumn::make('effective_at')->label('예약 시행일')->dateTime('Y-m-d H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('locale')->label('언어')
                    ->options(fn () => Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    // 유형(terms/privacy/marketing) 자체는 고정이라 자유 생성은 안 되지만, 언어별 버전은 추가할 수
    // 있어야 하므로(위 폼의 '유형' Select가 고정 3종만 허용) 생성 자체는 허용한다. 삭제는 여전히
    // 막아둔다 — 실수로 지우면 그 언어(또는 폴백 대상인 기본 언어)의 필수 동의 문서 자체가 없어짐.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicies::route('/'),
            'create' => CreatePolicy::route('/create'),
            'edit' => EditPolicy::route('/{record}/edit'),
        ];
    }
}
