<?php

namespace App\Filament\Concerns;

use App\Services\UploadService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
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
    //
    // **2026-09-07 후속 버그 발견·수정**: 위 빈 배열이 "파일 첨부"(에디터 툴바의 첨부파일 삽입,
    // Filament\Forms\Components\RichEditor\Actions\AttachFilesAction) 쪽은 전혀 다른 방식으로
    // 깨뜨렸다 — 그 액션은 내부에서 `FileUpload::make('file')->acceptedFileTypes($component->
    // getFileAttachmentsAcceptedFileTypes())`를 호출하는데, `BaseFileUpload::acceptedFileTypes()`는
    // (HasFileAttachments::getUploadedFileAttachment()와 달리) 빈 배열을 falsy로 걸러내는 가드가
    // 없어 `mimetypes:`.implode(',', [])`, 즉 값이 하나도 없는 `mimetypes:` 규칙을 그대로
    // 등록해버린다 — 어떤 파일도 빈 허용목록과 매치될 수 없으니 항상 거부됨(사용자 실측:
    // "파일 항목은 다음 형식의 파일이어야 합니다: ." — Laravel mimetypes 메시지의 :values가
    // 비어서 나온 것). 이미지 드래그삽입은 `getUploadedFileAttachment()`(가드 있음)를 타서 영향이
    // 없지만, 툴바의 "파일 첨부" 액션은 100% 항상 실패하는 상태였다.
    //
    // 수정: 아래 attachFilesAction()으로 Filament 기본 'attachFiles' 액션을
    // registerActions()로 덮어쓴다(HasActions::cacheActions()가 이름이 같으면 나중에 등록된
    // 쪽으로 덮어씀 — vendor AttachFilesAction과 동일 로직이되 acceptedFileTypes() 호출만
    // 제거). acceptedFileTypes()를 아예 호출하지 않으면 rule() 자체가 안 붙어 Filament 단의
    // mimetypes 검증이 없어지고(빈 배열 문제도 자연히 사라짐), 실제 검증은 여전히
    // saveUploadedFileAttachmentUsing()의 UploadService가 전담 — 보안 저하 없음(위 문단과 동일 논리).
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
            ->registerActions([self::attachFilesAction()])
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

    // vendor Filament\Forms\Components\RichEditor\Actions\AttachFilesAction의 복사본 —
    // 유일한 차이는 FileUpload('file')에 ->acceptedFileTypes(...)를 안 건다는 것뿐(위 richEditor()
    // 주석의 "2026-09-07 후속 버그" 참고). 나머지 동작(모달 필드 구성, 삽입/수정 커맨드 로직)은
    // vendor 원본과 동일하게 유지 — Filament 업그레이드로 원본이 바뀌면 이 복사본도 다시 맞출 것.
    private static function attachFilesAction(): Action
    {
        return Action::make('attachFiles')
            ->label(__('filament-forms::components.rich_editor.actions.attach_files.label'))
            ->modalHeading(__('filament-forms::components.rich_editor.actions.attach_files.modal.heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'alt' => $arguments['alt'] ?? null,
            ])
            ->schema(fn (array $arguments, RichEditor $component): array => [
                FileUpload::make('file')
                    ->label(filled($arguments['src'] ?? null)
                        ? __('filament-forms::components.rich_editor.actions.attach_files.modal.form.file.label.existing')
                        : __('filament-forms::components.rich_editor.actions.attach_files.modal.form.file.label.new'))
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    ->storeFiles(false)
                    ->required(blank($arguments['src'] ?? null))
                    ->hiddenLabel(blank($arguments['src'] ?? null)),
                TextInput::make('alt')
                    ->label(filled($arguments['src'] ?? null)
                        ? __('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.existing')
                        : __('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.new'))
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component, LivewireComponent $livewire): void {
                if ($data['file'] ?? null) {
                    $id = (string) Str::orderedUuid();

                    data_set($livewire, "componentFileAttachments.{$component->getStatePath()}.{$id}", $data['file']);
                    $src = $component->getUploadedFileAttachmentTemporaryUrl($data['file']);
                }

                if (filled($arguments['src'] ?? null)) {
                    if ($arguments['editorSelection']['type'] !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor']--;

                        unset($arguments['editorSelection']['head']);
                    }

                    $id ??= $arguments['id'] ?? null;
                    $src ??= $arguments['src'];

                    $component->runCommands(
                        [
                            EditorCommand::make('updateAttributes', arguments: [
                                'image',
                                [
                                    'alt' => $data['alt'] ?? null,
                                    'id' => $id,
                                    'src' => $src,
                                ],
                            ]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                if (blank($id ?? null)) {
                    return;
                }

                if (blank($src ?? null)) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => [
                                'alt' => $data['alt'] ?? null,
                                'id' => $id,
                                'src' => $src,
                            ],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
