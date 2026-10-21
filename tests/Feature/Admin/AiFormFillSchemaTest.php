<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Boards\Pages\CreateBoard;
use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Ai\AiFormFillSchema;
use App\Support\SafeMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// "열려 있는 작성·수정 화면에 채우기" 허용 필드 표(AiFormFillSchema) - 표에 적힌 모든 필드가 실제 Filament 폼에 있는지,
// 화면 판별이 경로로 되는지, 값 변환이 안전한지 고정한다.
class AiFormFillSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_every_field_in_the_allow_list_exists_in_the_real_form(): void
    {
        $this->actingAs($this->admin());
        $inquiry = Inquiry::create(['name' => '문의자', 'email' => 'q@test.local', 'title' => '문의', 'content' => '내용', 'locale' => 'ko']);

        $pages = [
            'posts' => Livewire::test(CreatePost::class),
            'boards' => Livewire::test(CreateBoard::class),
            'inquiries' => Livewire::test(EditInquiry::class, ['record' => $inquiry->getRouteKey()]),
            'popups' => Livewire::test(CreatePopup::class)->fillForm(['content_type' => 'html']),
            'banners' => Livewire::test(CreateBanner::class),
            'pages' => Livewire::test(CreatePage::class),
        ];

        $this->assertSame(array_keys(AiFormFillSchema::SCREENS), array_keys($pages), '표의 모든 화면을 시험해야 합니다');

        foreach (AiFormFillSchema::SCREENS as $screen => $definition) {
            $component = $pages[$screen];

            foreach (array_keys($definition['fields']) as $field) {
                $component->assertFormFieldExists($field);
            }

            if ($definition['image_field'] !== null) {
                $component->assertFormFieldExists($definition['image_field']);
            }
        }
    }

    public function test_screens_are_detected_from_the_original_page_path_and_nothing_else(): void
    {
        $this->assertSame('posts', AiFormFillSchema::detect('admin/posts/create')['screen']);
        $this->assertSame('posts', AiFormFillSchema::detect('admin/posts/12/edit')['screen']);
        $this->assertSame('popups', AiFormFillSchema::detect('/admin/popups/create/')['screen']);
        $this->assertSame('inquiries', AiFormFillSchema::detect('admin/inquiries/3/edit')['screen']);
        $this->assertSame('og_image', AiFormFillSchema::detect('admin/pages/2/edit')['image_field']);

        foreach (['admin/posts', 'admin/posts/12', 'admin/settings', 'admin', 'livewire/update', '', null, 'other/posts/create', 'admin/posts/create/extra'] as $path) {
            $this->assertNull(AiFormFillSchema::detect($path), "'{$path}' 는 대상 화면이 아닙니다");
        }

        // 관리자 경로를 바꿔도(ADMIN_PATH) 따라간다.
        config(['app.admin_path' => 'cms-secret']);
        $this->assertSame('posts', AiFormFillSchema::detect('cms-secret/posts/create')['screen']);
        $this->assertNull(AiFormFillSchema::detect('admin/posts/create'));
    }

    public function test_allow_list_checks_and_image_path_rules(): void
    {
        $this->assertTrue(AiFormFillSchema::allowsField('popups', 'title'));
        $this->assertTrue(AiFormFillSchema::allowsField('popups', 'image_path'));
        $this->assertFalse(AiFormFillSchema::allowsField('popups', 'is_active'));
        $this->assertFalse(AiFormFillSchema::allowsField('boards', 'image_path'));
        $this->assertFalse(AiFormFillSchema::allowsField('nope', 'title'));

        $this->assertTrue(AiFormFillSchema::isAllowedImagePath('uploads/ai_generated/2026/10/abc.png'));
        foreach (['uploads/images/x.png', '../uploads/ai_generated/x.png', 'uploads/ai_generated/../../.env', 'http://evil.test/x.png', 'uploads/ai_generated/a b.png', null, ''] as $bad) {
            $this->assertFalse(AiFormFillSchema::isAllowedImagePath($bad), (string) $bad);
        }
    }

    public function test_values_are_converted_to_each_fields_format_safely(): void
    {
        $markdown = "## 제목\n**굵게** 본문과 [링크](https://example.com) <script>alert(1)</script>\n\n- 하나\n- 둘";

        // 리치 에디터 칸: 안전한 HTML(링크·이미지·스크립트 없음).
        $rich = AiFormFillSchema::formatValue('posts', 'content', $markdown);
        $this->assertStringContainsString('<strong>굵게</strong>', $rich);
        $this->assertStringContainsString('<li>하나</li>', $rich);
        $this->assertStringNotContainsString('<script', $rich);
        $this->assertStringNotContainsString('<a ', $rich);

        // 여러 줄 평문 칸: 서식 기호 제거, 개행 유지.
        $text = AiFormFillSchema::formatValue('posts', 'content_text', $markdown);
        $this->assertStringNotContainsString('**', $text);
        $this->assertStringNotContainsString('<strong>', $text);
        $this->assertStringContainsString("\n", $text);
        $this->assertStringContainsString('- 하나', $text);

        // 한 줄 칸: 개행 없이 길이 제한.
        $title = AiFormFillSchema::formatValue('popups', 'title', str_repeat("가나다\n", 80));
        $this->assertStringNotContainsString("\n", $title);
        $this->assertLessThanOrEqual(100, mb_strlen($title));

        // 링크 URL 칸: http/https 만.
        $this->assertSame('https://example.com/a', AiFormFillSchema::formatValue('banners', 'link_url', 'https://example.com/a'));
        $this->assertSame('', AiFormFillSchema::formatValue('banners', 'link_url', 'javascript:alert(1)'));

        // 표에 없는 필드는 null.
        $this->assertNull(AiFormFillSchema::formatValue('popups', 'is_active', '1'));
        $this->assertNull(AiFormFillSchema::formatValue('nope', 'title', 'x'));
    }

    // ───────────── 마크다운 렌더 안전 ─────────────

    public function test_chat_markdown_escapes_html_drops_images_and_only_keeps_http_links(): void
    {
        $html = (string) SafeMarkdown::chatHtml(implode("\n\n", [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '![추적](https://evil.test/t.png?q=secret)',
            '[좋은 링크](https://example.com/guide)',
            '[나쁜 링크](javascript:alert(1))',
            '[데이터](data:text/html;base64,PHNjcmlwdD4=)',
            '[상대](/admin/secret)',
            '[대문자 스킴](JaVaScRiPt:alert(1))',
            '**굵게** *기울임* `코드`',
            '# 큰 제목',
            "- 목록\n- 둘째",
            '<a href="https://evil.test" onclick="x()">원문 HTML 링크</a>',
        ]));

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', strtolower(preg_replace('/&lt;[^&]*&gt;/', '', $html) ?? ''));
        $this->assertStringNotContainsString('evil.test/t.png', $html, '이미지 주소가 어디에도 남으면 안 된다(쿼리 유출 통로)');
        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringNotContainsString('data:text', $html);
        $this->assertStringNotContainsString('href="/admin/secret"', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('<a href="https://evil.test"', $html);

        // 허용되는 것들
        $this->assertStringContainsString('<a href="https://example.com/guide" target="_blank" rel="noopener noreferrer nofollow" title="example.com">좋은 링크</a>', $html);
        $this->assertStringContainsString('<strong>굵게</strong>', $html);
        $this->assertStringContainsString('<em>기울임</em>', $html);
        $this->assertStringContainsString('<code>코드</code>', $html);
        $this->assertStringContainsString('<li>목록</li>', $html);
        $this->assertStringContainsString('<p><strong>큰 제목</strong></p>', $html);
        $this->assertStringContainsString('[그림: 추적]', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_markdown_input_is_length_capped_and_empty_input_is_empty(): void
    {
        $this->assertSame('', (string) SafeMarkdown::chatHtml(null));
        $this->assertSame('', (string) SafeMarkdown::chatHtml("  \n "));

        $html = (string) SafeMarkdown::chatHtml(str_repeat('가', 100000));
        $this->assertLessThan(21000, mb_strlen($html));
    }

    public function test_plain_text_conversion_strips_formatting(): void
    {
        $plain = SafeMarkdown::plainText("**굵게** 그리고 [링크](https://example.com)\n\n1. 하나\n2. 둘\n\n`코드`");

        $this->assertStringNotContainsString('*', $plain);
        $this->assertStringNotContainsString('[', $plain);
        $this->assertStringNotContainsString('`', $plain);
        $this->assertStringContainsString('굵게 그리고 링크', $plain);
        $this->assertStringContainsString('- 하나', $plain);
    }
}
