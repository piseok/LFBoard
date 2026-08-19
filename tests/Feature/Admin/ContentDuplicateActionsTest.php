<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Resources\Boards\Pages\ListBoards;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Models\Banner;
use App\Models\Board;
use App\Models\Page;
use App\Models\Popup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// 배너/게시판/페이지/팝업의 "복제" 단일/일괄 액션 — 이미지 파일이 있는 리소스는 복제본이
// 원본과 물리적으로 다른 파일을 가리켜야(하나를 지워도 나머지가 안 깨짐), slug가 유니크해야
// 하는 리소스는 새 slug를 생성해야 한다.
class ContentDuplicateActionsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_duplicating_a_banner_copies_the_image_file_physically(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/images/2026/07/original.png', 'fake-image-bytes');

        $banner = Banner::create([
            'group_key' => 'main_top', 'locale' => 'ko', 'title' => '원본 배너',
            'image_path' => 'uploads/images/2026/07/original.png', 'is_active' => true, 'sort_order' => 0,
        ]);

        Livewire::actingAs($this->superAdmin())->test(ListBanners::class)
            ->callTableAction('duplicate', $banner);

        $this->assertSame(2, Banner::count());
        $copy = Banner::where('id', '!=', $banner->id)->first();
        $this->assertSame('[복사] 원본 배너', $copy->title);
        $this->assertNotSame($banner->image_path, $copy->image_path);
        Storage::disk('uploads')->assertExists($copy->image_path);

        // 원본 이미지를 지워도 복제본 이미지는 별도 파일이라 그대로 남아있어야 한다.
        Storage::disk('uploads')->delete($banner->image_path);
        Storage::disk('uploads')->assertExists($copy->image_path);
    }

    public function test_bulk_duplicating_banners_creates_one_copy_per_selected_record(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/images/2026/07/a.png', 'a');
        Storage::disk('uploads')->put('uploads/images/2026/07/b.png', 'b');

        $a = Banner::create(['group_key' => 'main_top', 'locale' => 'ko', 'title' => 'A', 'image_path' => 'uploads/images/2026/07/a.png', 'is_active' => true]);
        $b = Banner::create(['group_key' => 'main_top', 'locale' => 'ko', 'title' => 'B', 'image_path' => 'uploads/images/2026/07/b.png', 'is_active' => true]);

        Livewire::actingAs($this->superAdmin())->test(ListBanners::class)
            ->callTableBulkAction('bulkDuplicate', [$a->id, $b->id]);

        $this->assertSame(4, Banner::count());
    }

    public function test_duplicating_a_popup_only_copies_the_image_when_content_type_is_image(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/images/2026/07/popup.png', 'x');

        $popup = Popup::create([
            'title' => '원본 팝업', 'locale' => 'ko', 'content_type' => 'image',
            'image_path' => 'uploads/images/2026/07/popup.png', 'position' => 'center', 'is_active' => true,
        ]);

        Livewire::actingAs($this->superAdmin())->test(ListPopups::class)
            ->callTableAction('duplicate', $popup);

        $copy = Popup::where('id', '!=', $popup->id)->first();
        $this->assertNotSame($popup->image_path, $copy->image_path);
        Storage::disk('uploads')->assertExists($copy->image_path);
    }

    public function test_duplicating_a_page_generates_a_unique_slug_and_copies_og_image(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('uploads/images/2026/07/og.png', 'x');

        $page = Page::create([
            'title' => '원본 페이지', 'slug' => 'about', 'locale' => 'ko', 'content_type' => 'editor',
            'content' => '내용', 'og_image' => 'uploads/images/2026/07/og.png', 'min_level' => 0, 'is_active' => true,
        ]);

        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(ListPages::class)
            ->callTableAction('duplicate', $page);

        $copy = Page::where('id', '!=', $page->id)->first();
        $this->assertSame('about-copy', $copy->slug);
        $this->assertNotSame($page->og_image, $copy->og_image);
        Storage::disk('uploads')->assertExists($copy->og_image);

        // 같은 원본을 한 번 더 복제하면 "-copy"가 이미 있으니 "-copy-2"로 이어져야 한다.
        Livewire::actingAs($admin)->test(ListPages::class)
            ->callTableAction('duplicate', $page);

        $this->assertDatabaseHas('pages', ['slug' => 'about-copy-2', 'locale' => 'ko']);
    }

    public function test_duplicating_a_board_generates_a_unique_slug_and_does_not_copy_posts(): void
    {
        $board = Board::create([
            'name' => '원본 게시판', 'slug' => 'notice', 'locale' => 'ko', 'skin' => 'default',
            'layout' => 'list', 'is_active' => true,
        ]);

        Livewire::actingAs($this->superAdmin())->test(ListBoards::class)
            ->callTableAction('duplicate', $board);

        $copy = Board::where('id', '!=', $board->id)->first();
        $this->assertSame('notice-copy', $copy->slug);
        $this->assertSame('[복사] 원본 게시판', $copy->name);
        $this->assertSame(0, $copy->posts()->count());
    }
}
