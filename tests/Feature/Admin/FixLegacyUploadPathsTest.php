<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// site_logo/배너/팝업/OG 이미지가 uploads/ 접두어 없이(Filament FileUpload 기본 저장 방식으로)
// 저장돼 있던 과거 데이터를 접두어를 붙여 보정하는 명령어. 실제 파일 위치는 바뀌지 않으므로
// 문자열만 보정하면 된다 — 이미 uploads/로 시작하는 값은 건드리지 않아야 한다.
class FixLegacyUploadPathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefixes_legacy_site_logo_and_favicon_paths(): void
    {
        SiteSetting::create(['key' => 'site_logo', 'value' => 'images/legacy-logo.png']);
        SiteSetting::create(['key' => 'site_favicon', 'value' => 'uploads/images/already-fine.png']);

        $this->artisan('uploads:fix-legacy-paths')->assertSuccessful();

        $this->assertSame('uploads/images/legacy-logo.png', SiteSetting::where('key', 'site_logo')->value('value'));
        $this->assertSame('uploads/images/already-fine.png', SiteSetting::where('key', 'site_favicon')->value('value'));
    }

    public function test_prefixes_legacy_banner_popup_and_og_image_paths(): void
    {
        $banner = Banner::create([
            'title' => 'b', 'image_path' => 'images/banner.png', 'locale' => 'ko',
            'group_key' => 'main', 'sort_order' => 0, 'is_active' => true,
        ]);
        $popup = Popup::create([
            'title' => 'p', 'content_type' => 'image', 'image_path' => 'images/popup.png',
            'locale' => 'ko', 'position' => 'center', 'is_active' => true,
        ]);
        $page = Page::create([
            'title' => 't', 'slug' => 'legacy-og-test', 'locale' => 'ko', 'content_type' => 'editor',
            'og_image' => 'images/og.png', 'min_level' => 0, 'is_active' => true,
        ]);

        $this->artisan('uploads:fix-legacy-paths')->assertSuccessful();

        $this->assertSame('uploads/images/banner.png', $banner->fresh()->image_path);
        $this->assertSame('uploads/images/popup.png', $popup->fresh()->image_path);
        $this->assertSame('uploads/images/og.png', $page->fresh()->og_image);
    }

    public function test_dry_run_reports_but_does_not_change_anything(): void
    {
        SiteSetting::create(['key' => 'site_logo', 'value' => 'images/legacy-logo.png']);

        $this->artisan('uploads:fix-legacy-paths', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('images/legacy-logo.png', SiteSetting::where('key', 'site_logo')->value('value'));
    }
}
