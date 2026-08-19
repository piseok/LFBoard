<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 사이트 설정(보안 탭) "전체 사이트 로그인 필수(인트라넷 모드)" — site_login_required_enabled.
// 기본값(미설정/0)일 때는 기존 동작(비회원 자유 열람 + 게시판별 등급 체계)이 전혀 바뀌지
// 않아야 하고, 켜졌을 때만 프론트 전체가 로그인 화면으로 리다이렉트되어야 한다. 단, 로그인/
// 회원가입/비밀번호 재설정 등 인증 라우트 자체는 예외로 계속 열려 있어야 한다(안 그러면
// 아무도 로그인할 수 없는 자물쇠 상태가 됨).
class RequireSiteLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_browse_freely_by_default_unchanged_behavior(): void
    {
        $this->get('/')->assertOk();
        $this->get('/search')->assertOk();
    }

    public function test_guests_are_redirected_to_login_when_enabled(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/search')->assertRedirect(route('login'));
    }

    public function test_auth_routes_stay_accessible_to_guests_when_enabled(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_logged_in_users_pass_through_when_enabled(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $user = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_intended_url_is_preserved_after_redirect_to_login(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $this->get('/search');

        $this->assertSame(url('/search'), session('url.intended'));
    }

    // 배너 클릭/파일 다운로드는 이메일·카톡 등 사이트 밖 채널로 공유되는 링크라 인트라넷 모드에서도
    // 비회원이 열 수 있어야 한다(2026-08-08 사용자 확인) — routes/web.php의 $siteLoginExemptRoutes 참고.
    public function test_banner_click_stays_accessible_to_guests_when_enabled(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $banner = Banner::create([
            'group_key' => 'main', 'content_type' => 'image', 'locale' => 'ko',
            'title' => 'Test banner', 'link_url' => 'https://example.com/landing',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->get(route('banner.click', $banner))->assertRedirect('https://example.com/landing');
    }

    public function test_media_download_stays_accessible_to_guests_when_enabled(): void
    {
        app(SiteSettingService::class)->set('site_login_required_enabled', '1', 'security');

        $uploader = User::create([
            'name' => 'Uploader', 'email' => 'uploader@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $media = MediaFile::create([
            'user_id' => $uploader->id,
            'original_name' => 'test.txt', 'stored_name' => 'test.txt',
            'file_path' => 'uploads/nonexistent-test-file.txt', 'file_size' => 0, 'mime_type' => 'text/plain',
        ]);

        // 실제 파일은 없어 컨트롤러가 404를 던지지만, 핵심은 site.login 미들웨어에 302로 가로채이지
        // 않고 컨트롤러까지 도달했다는 것(예외 그룹이 정상 동작함을 증명).
        $this->get(route('media.download', $media))->assertNotFound();
    }
}
