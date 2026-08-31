<?php

namespace App\Filament\Concerns;

use App\Services\UploadService;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HasRichEditorDefaults
{
    // 모든 리소스의 RichEditor에 공통 적용: 최소 높이 확보(입력창이 너무 작아 보이는 문제 개선),
    // 'uploads' 디스크에 파일첨부 기능 연결(문서/이미지 화이트리스트, UploadService의 문서 용량 제한과 동일하게 20MB로 통일),
    // 전체 제목 레벨(H1~H6, 기본값은 H2/H3뿐) + HTML 코드 삽입 버튼(디자이너가 피그마 등으로 작업한 결과물을
    // 붙여넣기만으로는 서식이 유실되는 경우, 직접 만든 HTML을 그대로 붙여넣어 서식 그대로 반영하기 위함).
    //
    // saveUploadedFileAttachmentUsing()로 UploadService를 거치게 한다 — 배너/팝업/페이지 등 다른 모든
    // FileUpload 필드는 이미 61a5bc73에서 UploadService로 라우팅했지만(디스크 쓰기 실패 시 예외를
    // 던지도록 고침, uploads 디스크가 throw=>false라 원래는 실패해도 조용히 무시됐다), 그 커밋이
    // 리치에디터 자체의 파일첨부(fileAttachmentsDisk의 기본 저장 경로, $file->store()를 그대로 씀)는
    // 빠뜨렸다 — 관리자 에디터에서 이미지 첨부가 실패해도 아무 에러 없이 그냥 안 올라가는 원인이었다
    // (프론트 TinyMCE 업로드는 이미 UploadService를 거쳐 실패 시 에러를 보여준다). fileAttachmentsDirectory/
    // fileAttachmentsVisibility는 이 콜백을 쓰면 저장 경로 결정에는 더 이상 관여하지 않지만(UploadService가
    // 자체적으로 uploads/editor/{Y}/{m}/ 규칙으로 저장), getFileAttachmentUrl() 기본 구현이 여전히
    // fileAttachmentsDisk를 통해 URL을 만들어 남겨둔다.
    //
    // fileAttachmentsAcceptedFileTypes([])로 빈 배열을 명시한다(2026-08-31, 사용자 실측 재현) —
    // Filament의 FilePond 클라이언트 검증(vendor/filament/forms/resources/js/components/
    // file-upload.js의 fileValidateTypeDetectType)이 드래그앤드롭으로 들어온 파일은 브라우저가
    // MIME 타입을 못 채워주는 경우가 있어 "찾아보기"로 넣은 동일 파일은 통과하는데 드래그만
    // "Uploaded files must be of type..."로 오탐 거부했다. FilePond의 file-validate-type
    // 플러그인은 acceptedFileTypes 배열이 비어있으면 타입 감지 자체를 건너뛰고 무조건 통과시킨다
    // (vendor 번들의 `d=(u,g,f)=>{if(g.length===0)return!0;...}` 확인) — 그냥 이 옵션을 안
    // 불러도 컴포넌트 기본값(image/png 등 4종, 빈 배열 아님)이 그대로 적용돼 문제가 재현되므로
    // 반드시 빈 배열을 명시해야 한다. 프론트 TinyMCE 업로드(BoardFrontController::uploadImage())는
    // 애초에 이런 클라이언트 사전검사가 없고 서버 검증만 신뢰한다 — 관리자도 똑같이 클라이언트
    // 사전검사를 없애고 서버 검증(위 saveUploadedFileAttachmentUsing이 호출하는
    // UploadService::upload(), 확장자+MIME+크기를 실제로 검증하고 실패 시 명확한 에러를 던짐)에만
    // 의존하도록 맞춘다. 파일 크기(maxSize)는 브라우저가 드래그 이벤트에서도 안정적으로 읽는
    // 값이라 계속 클라이언트에서 확인한다.
    protected static function richEditor(string $name, string $label = '내용'): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->extraInputAttributes(['style' => 'min-height: 260px'])
            ->fileAttachmentsDisk('uploads')
            ->fileAttachmentsDirectory('uploads/editor')
            ->fileAttachmentsVisibility('public')
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file): string => app(UploadService::class)->upload($file, 'editor'))
            ->fileAttachmentsAcceptedFileTypes([])
            ->fileAttachmentsMaxSize(20 * 1024)
            ->tools([
                RichEditorTool::make('insertHtml')
                    ->label('HTML 코드 삽입')
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->jsHandler(<<<'JS'
                        (() => {
                            let dialog = document.getElementById('rich-editor-html-insert-dialog');

                            if (!dialog) {
                                dialog = document.createElement('dialog');
                                dialog.id = 'rich-editor-html-insert-dialog';
                                dialog.style.cssText = 'padding:0;border:none;border-radius:8px;max-width:640px;width:90vw;';
                                dialog.innerHTML = `
                                    <form method='dialog' style='padding:20px;'>
                                        <p style='margin:0 0 12px;font-weight:600;'>HTML 코드 삽입</p>
                                        <textarea id='rich-editor-html-insert-textarea' rows='14'
                                            style='width:100%;font-family:monospace;font-size:0.85rem;box-sizing:border-box;'
                                            placeholder='여기에 HTML 코드를 붙여넣으세요'></textarea>
                                        <div style='margin-top:12px;display:flex;gap:8px;justify-content:flex-end;'>
                                            <button value='cancel' type='submit' style='padding:6px 14px;'>취소</button>
                                            <button value='insert' type='submit' style='padding:6px 14px;font-weight:600;'>삽입</button>
                                        </div>
                                    </form>
                                `;
                                document.body.appendChild(dialog);
                            }

                            const textarea = document.getElementById('rich-editor-html-insert-textarea');
                            textarea.value = '';
                            dialog.showModal();

                            dialog.onclose = () => {
                                if (dialog.returnValue === 'insert' && textarea.value.trim() !== '') {
                                    $getEditor()?.commands.insertContent(textarea.value);
                                }
                            };
                        })()
                        JS),
            ])
            ->enableToolbarButtons(['h1', 'h4', 'h5', 'h6', 'insertHtml']);
    }
}
