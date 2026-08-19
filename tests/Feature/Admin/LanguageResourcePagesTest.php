<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Languages\LanguageResource;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// LanguageResource는 원래 CreateAction/EditAction이 모달로 열렸는데, FTP 전용(SSH 없는) 배포
// 환경에서 Filament 컴파일 자산이 설치된 패키지 버전과 어긋나면 모달이 오버레이가 아니라 페이지
// 안에 인라인으로 끼어들어가 배경 테이블을 짓누르는 문제가 있었다. 전용 create/edit 페이지로
// 바꾸면 이 모달 자체를 안 타므로 같은 문제가 재발하지 않는다 — 이 회귀를 막는 테스트.
class LanguageResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_create_page_is_a_dedicated_route_not_a_modal(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(LanguageResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_can_create_a_language_via_the_dedicated_page(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(\App\Filament\Resources\Languages\Pages\CreateLanguage::class)
            ->fillForm(['code' => 'fr', 'name' => 'Français', 'timezone' => 'Asia/Seoul'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('languages', ['code' => 'fr', 'name' => 'Français']);
    }

    public function test_edit_page_is_a_dedicated_route_not_a_modal(): void
    {
        $language = Language::create(['code' => 'fr', 'name' => 'Français', 'timezone' => 'Asia/Seoul', 'is_active' => true]);

        $this->actingAs($this->superAdmin())
            ->get(LanguageResource::getUrl('edit', ['record' => $language]))
            ->assertSuccessful();
    }

    public function test_default_language_delete_button_is_hidden_on_edit_page(): void
    {
        $default = Language::create(['code' => 'ko', 'name' => '한국어', 'timezone' => 'Asia/Seoul', 'is_active' => true, 'is_default' => true]);

        $this->actingAs($this->superAdmin());

        Livewire::test(\App\Filament\Resources\Languages\Pages\EditLanguage::class, ['record' => $default->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_non_default_language_delete_button_is_visible_on_edit_page(): void
    {
        Language::create(['code' => 'ko', 'name' => '한국어', 'timezone' => 'Asia/Seoul', 'is_active' => true, 'is_default' => true]);
        $other = Language::create(['code' => 'fr', 'name' => 'Français', 'timezone' => 'Asia/Seoul', 'is_active' => true]);

        $this->actingAs($this->superAdmin());

        Livewire::test(\App\Filament\Resources\Languages\Pages\EditLanguage::class, ['record' => $other->getKey()])
            ->assertActionVisible('delete');
    }
}
