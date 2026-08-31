<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\PostResource;
use App\Services\UploadService;
use Closure;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

// 61a5bc73에서 배너/팝업/페이지 등 다른 모든 FileUpload 필드는 UploadService를 거치도록 고쳐
// 디스크 쓰기 실패(uploads 디스크는 throw=>false)가 조용히 무시되지 않게 했지만, 리치에디터
// 자체의 파일첨부(fileAttachmentsDisk 기본 저장 경로 — $file->store()를 그대로 씀)는 그 수정에서
// 빠져 있었다 — 관리자 에디터에서 이미지 첨부가 실패해도 아무 표시 없이 그냥 안 올라가는 원인이었다.
// saveUploadedFileAttachment()는 Filament 스키마 컨테이너 밖에서 단독 호출하면 evaluate() 내부의
// getContainer()가 초기화 전 접근으로 에러를 내서(Livewire 폼 마운트 전체가 필요) 여기서는 직접
// 실행 대신 배선(클로저가 실제로 등록됐고 UploadService를 참조하는지)만 확인한다.
class RichEditorFileAttachmentUploadTest extends TestCase
{
    public function test_rich_editor_wires_file_attachments_through_upload_service(): void
    {
        $richEditor = (new ReflectionMethod(PostResource::class, 'richEditor'))->invoke(null, 'content');

        $callback = (new ReflectionProperty($richEditor, 'saveUploadedFileAttachmentUsing'))->getValue($richEditor);

        $this->assertInstanceOf(Closure::class, $callback);

        $source = file_get_contents((new \ReflectionFunction($callback))->getFileName());
        $this->assertStringContainsString(UploadService::class, $source);
    }
}
