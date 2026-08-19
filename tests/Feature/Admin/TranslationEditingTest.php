<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteSettings;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 사이트 설정 > 번역 관리 탭에서 lang/{코드}.json 파일을 직접 읽고 쓴다(site_settings 테이블이
// 아니라 파일 자체가 진짜 저장 대상). 실제 저장소의 lang/ 파일을 건드리면 안 되므로,
// app()->useLangPath()로 매 테스트마다 임시 디렉토리로 리다이렉트해서 검증한다.
class TranslationEditingTest extends TestCase
{
    use RefreshDatabase;

    private string $tempLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLangPath = sys_get_temp_dir().'/lfboard_lang_test_'.uniqid();
        mkdir($this->tempLangPath);
        app()->useLangPath($this->tempLangPath);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->tempLangPath}/*") ?: []);
        @rmdir($this->tempLangPath);

        parent::tearDown();
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_translation_tab_loads_existing_json_file_content(): void
    {
        Language::create(['code' => 'en', 'name' => 'English', 'timezone' => 'Asia/Seoul', 'is_active' => true]);
        file_put_contents("{$this->tempLangPath}/en.json", json_encode(['Save' => 'Custom Save Label']));

        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->assertFormSet(['translations__en' => ['Save' => 'Custom Save Label']]);
    }

    public function test_saving_the_translation_tab_writes_the_json_file(): void
    {
        Language::create(['code' => 'en', 'name' => 'English', 'timezone' => 'Asia/Seoul', 'is_active' => true]);
        file_put_contents("{$this->tempLangPath}/en.json", '{}');

        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->fillForm(['translations__en' => ['Save' => 'Keep'], 'admin_email' => 'admin@test.local'])
            ->call('save');

        $this->assertSame(
            ['Save' => 'Keep'],
            json_decode(file_get_contents("{$this->tempLangPath}/en.json"), true)
        );
    }

    // KeyValue 필드에서 관리자가 원문(키)을 지우고 저장할 수도 있는데, 빈 키가 그대로
    // json_encode되면 파일이 깨진 상태로 남으므로 저장 시점에 걸러내야 한다.
    public function test_saving_translations_drops_entries_with_an_empty_key(): void
    {
        Language::create(['code' => 'en', 'name' => 'English', 'timezone' => 'Asia/Seoul', 'is_active' => true]);
        file_put_contents("{$this->tempLangPath}/en.json", '{}');

        $this->actingAs($this->superAdmin());

        Livewire::test(SiteSettings::class)
            ->fillForm(['translations__en' => ['' => 'orphaned value', 'Save' => 'Keep'], 'admin_email' => 'admin@test.local'])
            ->call('save');

        $this->assertSame(
            ['Save' => 'Keep'],
            json_decode(file_get_contents("{$this->tempLangPath}/en.json"), true)
        );
    }
}
