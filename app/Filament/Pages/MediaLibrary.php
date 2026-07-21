<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPermissionCheck;
use App\Models\Banner;
use App\Models\EmailTemplate;
use App\Models\Inquiry;
use App\Models\MaintenanceReport;
use App\Models\MarketingMailLog;
use App\Models\MediaFile;
use App\Models\Page as PageModel;
use App\Models\Policy;
use App\Models\Popup;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Services\UploadService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MediaLibrary extends Page implements HasTable
{
    use HasPermissionCheck;
    use InteractsWithTable;

    protected static string $permissionKey = 'media';

    protected string $view = 'filament.pages.media-library';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = '미디어 라이브러리';

    protected static string|UnitEnum|null $navigationGroup = '콘텐츠 관리';

    protected static ?string $title = '미디어 라이브러리';

    public function table(Table $table): Table
    {
        return $table
            ->query(MediaFile::query())
            ->defaultSort('created_at', 'desc')
            // Filament의 컬럼 visible()은 레코드별이 아니라 컬럼 전체 단위로(레코드 바인딩 전에)
            // 한 번 평가되는 것으로 보인다 — ->visible(fn (?MediaFile $record) => ...)로 이미지 파일에서만
            // 보이게 하려던 컬럼(썸네일, 다운로드수)이 항상 안 보이는 원인이었다. 행마다 다르게
            // 보여야 하는 값은 visible()이 아니라 state 자체를 null로 만들어 표현해야 한다.
            // contentGrid()도 recordActions(삭제 버튼)가 아예 렌더링되지 않게 만드는 원인이었어서 제거.
            ->columns([
                ImageColumn::make('file_path')->label('')->disk('uploads')
                    ->state(fn (MediaFile $record) => str_starts_with($record->mime_type, 'image/') ? $record->file_path : null)
                    ->height(120),
                TextColumn::make('original_name')->label('파일명')->limit(30)->weight('bold'),
                TextColumn::make('file_size')->label('크기')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 1024, 1).' KB' : '-'),
                TextColumn::make('created_at')->label('업로드일')->date('Y-m-d'),
                TextColumn::make('url')->label('URL')
                    ->state(fn (?MediaFile $record) => $record ? url($record->file_path) : null)
                    ->copyable()
                    // copyable()은 지정 안 하면 getCopyableState() ?? formatState()로 복사값을
                    // 정하는데, formatState()는 아래 limit(30)이 적용된 잘린 텍스트라서 그냥
                    // 두면 "...으로 잘린 URL"이 그대로 복사된다 — 화면 표시와 별개로 복사값은
                    // 항상 전체 URL이어야 하므로 명시적으로 지정한다.
                    ->copyableState(fn (?MediaFile $record) => $record ? url($record->file_path) : null)
                    ->copyMessage('URL이 복사되었습니다.')
                    ->limit(30),
                TextColumn::make('download_count')->label('다운로드수')
                    ->formatStateUsing(fn (MediaFile $record, ?int $state) => str_starts_with($record->mime_type, 'image/') ? '-' : ($state ?: 0)),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->action(function (MediaFile $record) {
                        app(UploadService::class)->delete($record->file_path);
                        $record->delete();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cleanup')
                ->label('미사용 이미지 정리')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('업로드된 지 24시간이 지났고, 게시글·페이지·배너·팝업·사이트 로고/파비콘·약관·1:1상담 답변·점검안내·이메일템플릿·마케팅메일 어디에서도 더 이상 참조되지 않는 이미지/첨부파일만 삭제합니다(리치에디터로 본문에 삽입한 파일 포함). 이 미디어 라이브러리 목록의 파일은 대상이 아닙니다.')
                ->action(function (): void {
                    $count = $this->cleanupOrphanedImages();

                    Notification::make()->title($count.'개의 미사용 이미지를 정리했습니다.')->success()->send();
                }),
            Action::make('upload')
                ->label('파일 업로드')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('files')
                        ->label('파일')
                        ->multiple()
                        ->disk('local')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $uploadService = app(UploadService::class);
                    $count = 0;

                    foreach ($data['files'] as $tempPath) {
                        // 'local' 디스크의 실제 root를 storage_path('app/...')로 직접 하드코딩하면
                        // Laravel 버전에 따른 기본 root 변경(app/ → app/private, Laravel 11+)이나
                        // 테스트에서 Storage::fake('local')로 디스크 root가 바뀌는 경우 둘 다에서
                        // 어긋난다 — is_file()이 항상 false가 되어 파일을 올려도 "0개 업로드"로
                        // 조용히 실패했다(실제로 겪은 문제). 디스크 자신에게 물어보면 항상 맞다.
                        $fullPath = Storage::disk('local')->path($tempPath);

                        if (! is_file($fullPath)) {
                            continue;
                        }

                        $uploadedFile = new \Illuminate\Http\UploadedFile($fullPath, basename($fullPath), null, null, true);
                        $originalName = $uploadedFile->getClientOriginalName();

                        try {
                            $path = $uploadService->upload($uploadedFile, 'media');

                            MediaFile::create([
                                'user_id' => auth()->id(),
                                'original_name' => $originalName,
                                'stored_name' => basename($path),
                                'file_path' => $path,
                                'file_size' => $uploadedFile->getSize(),
                                'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
                            ]);
                            $count++;
                        } catch (\Throwable $e) {
                            Notification::make()->title($originalName.': '.$e->getMessage())->danger()->send();
                        }
                    }

                    Notification::make()->title($count.'개 파일이 업로드되었습니다.')->success()->send();
                }),
        ];
    }

    // uploads/images/(단순 FileUpload) + uploads/editor/(리치에디터 첨부) 아래 파일 중,
    // 업로드된 지 24시간(작성 중인 글의 이미지 오삭제 방지)이 지났고 이 두 디렉터리에 실제로
    // 쓰기 작업을 하는 전체 리소스(게시글/페이지/배너/팝업/사이트 로고·파비콘/약관/1:1상담 답변/
    // 점검안내/이메일템플릿/마케팅메일) 어디에도 참조되지 않는 파일만 삭제.
    private function cleanupOrphanedImages(): int
    {
        $disk = Storage::disk('uploads');
        $cutoff = now()->subDay()->timestamp;

        $files = collect($disk->allFiles('uploads/images'))
            ->merge($disk->allFiles('uploads/editor'))
            ->filter(fn (string $file) => $disk->lastModified($file) < $cutoff);

        if ($files->isEmpty()) {
            return 0;
        }

        $referencedText = collect()
            ->merge(Post::query()->pluck('content'))
            ->merge(PageModel::query()->pluck('content'))
            ->merge(PageModel::query()->pluck('og_image'))
            ->merge(Banner::query()->pluck('image_path'))
            ->merge(Popup::query()->pluck('image_path'))
            ->merge(Popup::query()->pluck('html_content'))
            ->merge(SiteSetting::query()->whereIn('key', ['site_logo', 'site_favicon'])->pluck('value'))
            ->merge(Policy::query()->pluck('content'))
            ->merge(Inquiry::query()->pluck('admin_reply'))
            ->merge(MaintenanceReport::query()->pluck('content'))
            ->merge(EmailTemplate::query()->pluck('body'))
            ->merge(MarketingMailLog::query()->pluck('content'))
            ->filter()
            ->implode(' ');

        $deleted = 0;

        foreach ($files as $file) {
            if (str_contains($referencedText, basename($file))) {
                continue;
            }

            $disk->delete($file);
            $deleted++;
        }

        return $deleted;
    }
}
