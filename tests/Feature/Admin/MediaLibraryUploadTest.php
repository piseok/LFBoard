<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\MediaLibrary;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// 'local' 디스크의 root가 storage_path('app/private')인데(Laravel 11+ 기본값), 업로드 액션이
// 임시 파일 경로를 storage_path('app/'.$tempPath)로 찾아서 'private/'이 빠져 is_file()이 항상
// false가 되고, 파일을 올려도 조용히 "0개 업로드"로 실패하던 문제 — 회귀 테스트.
class MediaLibraryUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_file_actually_creates_a_media_file_record(): void
    {
        Storage::fake('local');
        Storage::fake('uploads');

        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        Livewire::actingAs($super)->test(MediaLibrary::class)
            ->callAction('upload', data: ['files' => [$file]])
            ->assertHasNoActionErrors();

        $this->assertSame(1, MediaFile::count());
    }
}
