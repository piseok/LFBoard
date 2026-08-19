<?php

namespace Tests\Feature;

use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// PageResource와 동일한 content_type(editor/html_file) 규약을 Policy(약관/방침)에도 적용한 기능.
// content/pending_content 양쪽 다 지원하며, 예약 변경(applyPendingChange)이 타입/파일경로도
// 함께 승격하는지까지 검증한다.
class PolicyContentTypeTest extends TestCase
{
    use RefreshDatabase;

    private function policy(array $overrides = []): Policy
    {
        return Policy::create(array_merge([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '기본 내용',
        ], $overrides));
    }

    public function test_editor_type_renders_content_field_as_is(): void
    {
        $policy = $this->policy(['content_type' => 'editor', 'content' => '<p>에디터 내용</p>']);

        $this->assertSame('<p>에디터 내용</p>', $policy->renderedContent());
    }

    public function test_html_file_type_renders_the_uploaded_files_contents(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/policies/2026/07/terms.html', '<p>파일 내용</p>');

        $policy = $this->policy([
            'content_type' => 'html_file',
            'html_file_path' => 'uploads/policies/2026/07/terms.html',
        ]);

        $this->assertSame('<p>파일 내용</p>', $policy->renderedContent());
    }

    public function test_html_file_type_without_a_path_renders_null(): void
    {
        $policy = $this->policy(['content_type' => 'html_file', 'html_file_path' => null]);

        $this->assertNull($policy->renderedContent());
    }

    public function test_pending_content_falls_back_to_current_content_when_not_prepared(): void
    {
        $policy = $this->policy(['content' => '현재 내용', 'pending_content' => null]);

        $this->assertSame('현재 내용', $policy->renderedPendingContent());
    }

    public function test_pending_content_type_can_differ_from_current_content_type(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/policies/2026/07/pending.html', '<p>예정 파일 내용</p>');

        $policy = $this->policy([
            'content_type' => 'editor', 'content' => '현재 에디터 내용',
            'pending_content_type' => 'html_file', 'pending_html_file_path' => 'uploads/policies/2026/07/pending.html',
        ]);

        $this->assertSame('현재 에디터 내용', $policy->renderedContent());
        $this->assertSame('<p>예정 파일 내용</p>', $policy->renderedPendingContent());
    }

    public function test_applying_a_pending_html_file_change_promotes_type_and_path(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/policies/2026/07/new.html', '<p>새 내용</p>');

        $policy = $this->policy([
            'content_type' => 'editor', 'content' => '옛 내용', 'version' => '1',
            'pending_version' => '2',
            'pending_content_type' => 'html_file',
            'pending_html_file_path' => 'uploads/policies/2026/07/new.html',
            'effective_at' => now()->addDay(),
        ]);

        $policy->applyPendingChange();
        $policy->refresh();

        $this->assertSame('html_file', $policy->content_type);
        $this->assertSame('uploads/policies/2026/07/new.html', $policy->html_file_path);
        $this->assertSame('<p>새 내용</p>', $policy->renderedContent());
        $this->assertSame('editor', $policy->pending_content_type);
        $this->assertNull($policy->pending_html_file_path);
        $this->assertNull($policy->pending_version);
    }

    public function test_policy_show_page_renders_html_file_content(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/policies/2026/07/terms.html', '<p>파일로 올린 약관</p>');

        $this->policy([
            'content_type' => 'html_file',
            'html_file_path' => 'uploads/policies/2026/07/terms.html',
            'is_active' => true,
        ]);

        $this->get(front_route('policy.terms'))
            ->assertOk()
            ->assertSee('파일로 올린 약관', false);
    }
}
