<?php

namespace Tests\Feature;

use App\Services\UploadService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

// SVG는 이미지 확장자지만 XML 안에 <script>를 담을 수 있어(embedded XSS) 이미지 업로드로
// 허용하면 안 된다 — 업로드된 파일은 public/uploads에 정적 파일로 그대로 서빙되고, 게시글
// 첨부파일 링크(<a download>)는 실제 다운로드를 강제하지 않아 직접 열면 스크립트가 그대로
// 실행된다. 로그인 회원/비회원 누구나 올릴 수 있는 경로라 실제로 악용 가능한 취약점이었다.
class UploadServiceTest extends TestCase
{
    public function test_svg_upload_is_rejected(): void
    {
        Storage::fake('uploads');

        $file = UploadedFile::fake()->createWithContent(
            'evil.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>'
        );

        $this->expectException(RuntimeException::class);

        app(UploadService::class)->upload($file, 'images');
    }

    public function test_legitimate_image_upload_still_works(): void
    {
        Storage::fake('uploads');

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $path = app(UploadService::class)->upload($file, 'images');

        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('uploads')->assertExists($path);
    }

    public function test_php_disguised_as_image_extension_is_rejected(): void
    {
        Storage::fake('uploads');

        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>');

        $this->expectException(RuntimeException::class);

        app(UploadService::class)->upload($file, 'images');
    }

    // AI가 생성해 로컬 임시경로에 내려받은 이미지를 저장하는 경로 — HTTP 업로드가 아니므로
    // upload()와 별도 메서드지만 같은 확장자/실제 MIME 검증을 통과해야 한다.
    public function test_upload_from_path_saves_a_real_image(): void
    {
        Storage::fake('uploads');

        $tmp = tempnam(sys_get_temp_dir(), 'ai_img_test_').'.png';
        // 1x1 투명 PNG의 실제 바이트 — mime_content_type()이 image/png로 인식해야 통과한다.
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $path = app(UploadService::class)->uploadFromPath($tmp, 'ai_generated', 'png');

        $this->assertStringEndsWith('.png', $path);
        Storage::disk('uploads')->assertExists($path);
        @unlink($tmp);
    }

    public function test_upload_from_path_rejects_disallowed_extension(): void
    {
        Storage::fake('uploads');

        $tmp = tempnam(sys_get_temp_dir(), 'ai_img_test_').'.svg';
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->expectException(RuntimeException::class);

        try {
            app(UploadService::class)->uploadFromPath($tmp, 'ai_generated', 'svg');
        } finally {
            @unlink($tmp);
        }
    }

    public function test_upload_from_path_rejects_content_that_is_not_really_an_image(): void
    {
        Storage::fake('uploads');

        $tmp = tempnam(sys_get_temp_dir(), 'ai_img_test_').'.png';
        file_put_contents($tmp, '<?php system($_GET["c"]); ?>');

        $this->expectException(RuntimeException::class);

        try {
            app(UploadService::class)->uploadFromPath($tmp, 'ai_generated', 'png');
        } finally {
            @unlink($tmp);
        }
    }

    // 공유호스팅에서 uploads/ 폴더 쓰기 권한이 없으면, 디스크 설정의 throw=>false 때문에
    // Flysystem이 예외 없이 false만 반환한다. 이 반환값을 확인하지 않으면 DB에는 경로가
    // 저장되는데 실제 파일은 서버에 생성되지 않는 채로 조용히 "성공" 처리되는 문제가 실제로
    // 있었다 — 반드시 예외를 던져 실패가 눈에 보이게 해야 한다.
    public function test_upload_throws_a_clear_error_when_the_disk_write_silently_fails(): void
    {
        $diskMock = Mockery::mock(Filesystem::class);
        $diskMock->shouldReceive('putFileAs')->andReturn(false);
        Storage::shouldReceive('disk')->with('uploads')->andReturn($diskMock);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('권한');

        app(UploadService::class)->upload($file, 'images');
    }

    public function test_upload_from_path_throws_a_clear_error_when_the_disk_write_silently_fails(): void
    {
        $diskMock = Mockery::mock(Filesystem::class);
        $diskMock->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('uploads')->andReturn($diskMock);

        $tmp = tempnam(sys_get_temp_dir(), 'ai_img_test_').'.png';
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('권한');

        try {
            app(UploadService::class)->uploadFromPath($tmp, 'ai_generated', 'png');
        } finally {
            @unlink($tmp);
        }
    }

    // 회귀 테스트: uploads 디스크의 root가 public_path()(웹 루트)와 일치해야, UploadService가
    // 반환하는 "uploads/xxx/..." 형태의 경로를 blade에서 plain url()로 렌더링한 것과
    // Storage::disk('uploads')->url()로 렌더링한 것이 항상 같은 URL을 가리킨다. 과거 root가
    // public_path('uploads')였을 때는 두 값이 "uploads/uploads/..."만큼 어긋났었다(로고 이미지가
    // 깨져 보였던 실제 버그).
    public function test_uploads_disk_root_matches_the_web_root_relative_paths_upload_service_returns(): void
    {
        $path = 'uploads/images/2026/07/example.png';

        $this->assertSame(url($path), Storage::disk('uploads')->url($path));
    }
}
