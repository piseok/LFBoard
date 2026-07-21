<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSuperAdmin;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class SystemUpdate extends Page
{
    use RequiresSuperAdmin;

    protected string $view = 'filament.pages.system-update';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = '시스템 업데이트';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 80;

    protected static ?string $title = '시스템 업데이트';

    // SSH 없는 호스팅 환경에서 FTP로 새 파일을 올린 뒤,
    // 이 버튼만으로 마이그레이션을 웹에서 안전하게 실행할 수 있도록 한다.
    // exec()/shell_exec() 없이 같은 PHP 프로세스 안에서 Artisan::call()로 실행되므로
    // 셸 함수가 막힌 제한적인 공유호스팅에서도 동작한다.
    public ?string $lastOutput = null;

    public ?string $lastStatusOutput = null;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    protected function refreshStatus(): void
    {
        Artisan::call('migrate:status', ['--pending' => true]);
        $this->lastStatusOutput = Artisan::output();
    }

    // 대부분의 경우(대기 중인 마이그레이션이 없을 때) 이 안내를 매번 보여줄 필요가 없다 —
    // 실제로 새 마이그레이션이 있을 때만 눈에 띄면 된다.
    public function hasPendingMigrations(): bool
    {
        return ! str_contains($this->lastStatusOutput ?? '', 'No pending migrations');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runMigration')
                ->label('마이그레이션 실행')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('새로 추가된 마이그레이션을 지금 실행합니다. 배포 전 반드시 데이터베이스를 백업하세요.')
                ->action('runMigration'),
            // FTP로 파일만 덮어쓰는 배포 방식이라 Filament 패키지 버전이 바뀌면 컴파일된
            // CSS/JS(public/css/filament 등)가 새 버전 마크업과 안 맞아 모달이 깨지는 등의 문제가
            // 생길 수 있다(예: 모달이 오버레이가 아니라 페이지 안에 인라인으로 끼어들어가 배경
            // 콘텐츠를 짓누르는 현상). SSH 없이도 웹에서 캐시 초기화 + 에셋 재생성을 할 수 있게 한다.
            Action::make('clearCacheAndAssets')
                ->label('캐시 정리 및 에셋 재생성')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('설정/라우트/뷰 캐시와 PHP OPcache를 모두 비우고, Filament 관리자 화면의 CSS/JS를 다시 생성합니다. 파일을 새로 올린 뒤(배포) 화면이 깨지거나, PHP 코드를 새로 올렸는데도 예전 동작 그대로일 때 실행하세요(OPcache가 켜져 있으면 서버 재시작 없이는 새 코드가 반영 안 될 수 있습니다).')
                ->action('clearCacheAndAssets'),
            // 로고/파비콘/배너/팝업/OG 이미지 경로가 uploads/ 접두어 없이 저장돼 있던 과거 버그를
            // 1회성으로 보정한다(FixLegacyUploadPaths 참고). SSH 없이도 웹에서 실행 가능해야 한다.
            Action::make('fixLegacyUploadPaths')
                ->label('이미지 경로 보정(uploads/ 접두어)')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('로고/파비콘/배너/팝업/OG 이미지 경로에 uploads/ 접두어가 빠져 있으면(과거 업로드 방식 버그) 자동으로 붙여줍니다. 실제 파일 위치는 바뀌지 않고, 저장된 경로 문자열만 보정합니다. 이미 정상인 값은 건드리지 않습니다.')
                ->action('fixLegacyUploadPaths'),
            // SSH가 없어 서버 파일 구조를 직접 못 보는 환경 대응 — 업로드 경로가 엉뚱한 곳에
            // 생기는 문제(예: 예전 배포 방식의 public/ 폴더가 삭제 안 되고 남아있어서 평탄화
            // 자동판별이 틀어지는 경우)와 파일 업로드 용량 제한을 웹에서 바로 확인할 수 있게 한다.
            Action::make('checkUploadEnvironment')
                ->label('업로드 환경 점검')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('gray')
                ->action('checkUploadEnvironment'),
        ];
    }

    public function runMigration(): void
    {
        Artisan::call('migrate', ['--force' => true]);
        $this->lastOutput = Artisan::output();

        $this->refreshStatus();

        Notification::make()
            ->title('마이그레이션 실행 완료')
            ->success()
            ->send();
    }

    public function clearCacheAndAssets(): void
    {
        Artisan::call('optimize:clear');
        $output = Artisan::output();

        Artisan::call('filament:assets');
        $output .= Artisan::output();

        // FTP로 파일만 덮어써 배포하는 환경이라, opcache.validate_timestamps가 꺼져 있으면
        // 새로 올린 PHP 파일이 있어도 서버 재시작 전까지 예전 컴파일된 코드가 계속 실행된다
        // (SSH가 없어 재시작을 못 하는 환경이라 실제로 겪은 문제). opcache_reset()은 공유
        // 메모리에 캐시된 바이트코드를 모든 PHP-FPM 워커에 걸쳐 즉시 비워서, 재시작 없이도
        // 방금 올린 새 코드가 바로 반영되게 한다.
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $output .= "OPcache 초기화 완료\n";
        }

        $this->lastOutput = $output;

        Notification::make()
            ->title('캐시 정리 및 에셋 재생성 완료')
            ->success()
            ->send();
    }

    public function fixLegacyUploadPaths(): void
    {
        Artisan::call('uploads:fix-legacy-paths');
        $this->lastOutput = Artisan::output();

        Notification::make()
            ->title('이미지 경로 보정 완료')
            ->success()
            ->send();
    }

    public function checkUploadEnvironment(): void
    {
        $legacyPublicDir = base_path('public');
        $hasLegacyPublicDir = is_dir($legacyPublicDir);

        $lines = [
            'base_path(): '.base_path(),
            'public_path(): '.public_path(),
            'uploads 디스크 root: '.config('filesystems.disks.uploads.root'),
            '',
            $hasLegacyPublicDir
                ? '⚠ '.$legacyPublicDir.' 폴더가 아직 남아있습니다 — public_path()가 웹 루트가 아니라 이 폴더를 가리키게 되어, 업로드가 public/uploads 밑에 생기는 원인이 됩니다. 이 폴더가 예전 배포 방식의 잔재라면 FTP로 직접 삭제해야 합니다.'
                : '✓ 레거시 public/ 폴더 없음(정상— 업로드 경로가 웹 루트의 uploads/로 향합니다).',
            '',
        ];

        if ($hasLegacyPublicDir) {
            $lines[] = 'public/ 폴더 내용(최상위):';
            foreach (scandir($legacyPublicDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $lines[] = '  - '.$entry;
            }
            $lines[] = '';
        }

        $lines[] = 'upload_max_filesize: '.ini_get('upload_max_filesize');
        $lines[] = 'post_max_size: '.ini_get('post_max_size');
        $lines[] = '(배너/팝업 이미지 업로드가 "첨부파일 항목을 업로드하지 못했습니다" 오류로 실패하면, 보통 위 두 값이 실제 이미지 파일 크기보다 작아서입니다 — php.ini에서 늘려야 합니다.)';
        $lines[] = '';

        // js/css/fonts는 filament:assets가 Filament 패키지의 컴파일된 파일을 새로 쓰는
        // 대상이다 — FTP로 처음 올릴 때 소유자가 PHP 실행 계정(nobody 등)과 달라서 쓰기 권한이
        // 없으면 "캐시 정리 및 에셋 재생성" 버튼이 "Permission denied"로 실패한다(실제로 겪은
        // 문제 — storage/bootstrap/cache/uploads만 권한을 맞추고 이 셋을 빠뜨렸었다).
        foreach ([
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'uploads' => config('filesystems.disks.uploads.root').'/uploads',
            'js' => public_path('js'),
            'css' => public_path('css'),
            'fonts' => public_path('fonts'),
        ] as $label => $path) {
            $lines[] = $label.' ('.$path.'): '.(is_writable($path) ? '쓰기 가능' : '⚠ 쓰기 불가능(권한 확인 필요)');
        }

        $this->lastOutput = implode("\n", $lines);

        Notification::make()
            ->title('업로드 환경 점검 완료')
            ->success()
            ->send();
    }
}
