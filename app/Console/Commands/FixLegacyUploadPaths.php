<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

// uploads 디스크의 root를 public_path('uploads')에서 public_path()(웹 루트)로 바꾸기 전까지,
// site_logo/site_favicon/배너/팝업/OG 이미지는 Filament FileUpload 기본 저장 방식을 그대로 써서
// "uploads/" 접두어 없이(예: images/xxx.png) 저장돼 있었다. 이제는 UploadService를 거쳐 항상
// "uploads/"로 시작하는 값이 저장되므로, 그 전에 저장된 값만 한 번 접두어를 붙여 맞춰준다.
// (실제 파일 위치는 바뀐 적이 없다 — 저장된 문자열 표기만 다시 맞추는 데이터 보정.)
class FixLegacyUploadPaths extends Command
{
    protected $signature = 'uploads:fix-legacy-paths {--dry-run : 변경 없이 대상 건수만 확인}';

    protected $description = '로고/파비콘/배너/팝업/OG 이미지 경로에 uploads/ 접두어를 소급 적용합니다(1회성 보정)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        $total += $this->fixSiteSettings($dryRun);
        $total += $this->fixColumn(Banner::query(), 'image_path', $dryRun);
        $total += $this->fixColumn(Popup::query(), 'image_path', $dryRun);
        $total += $this->fixColumn(Page::query(), 'og_image', $dryRun);

        $this->info($dryRun
            ? "{$total}건이 변경 대상입니다(--dry-run이라 실제로 바꾸지 않았습니다)."
            : "{$total}건을 보정했습니다.");

        return self::SUCCESS;
    }

    private function fixSiteSettings(bool $dryRun): int
    {
        $count = 0;

        foreach (SiteSetting::query()->whereIn('key', ['site_logo', 'site_favicon'])->get() as $setting) {
            if (! $this->needsFix($setting->value)) {
                continue;
            }

            $this->line("site_settings.{$setting->key}: {$setting->value} → uploads/{$setting->value}");
            $count++;

            if (! $dryRun) {
                $setting->update(['value' => 'uploads/'.$setting->value]);
            }
        }

        return $count;
    }

    private function fixColumn($query, string $column, bool $dryRun): int
    {
        $count = 0;

        foreach ($query->get() as $record) {
            $value = $record->{$column};

            if (! $this->needsFix($value)) {
                continue;
            }

            $this->line(class_basename($record).'#'.$record->id.".{$column}: {$value} → uploads/{$value}");
            $count++;

            if (! $dryRun) {
                $record->update([$column => 'uploads/'.$value]);
            }
        }

        return $count;
    }

    private function needsFix(?string $value): bool
    {
        return filled($value) && ! str_starts_with($value, 'uploads/');
    }
}
