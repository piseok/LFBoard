<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;

trait HasRichEditorDefaults
{
    // 모든 리소스의 RichEditor에 공통 적용: 최소 높이 확보(입력창이 너무 작아 보이는 문제 개선),
    // 'uploads' 디스크에 파일첨부 기능 연결(문서/이미지 화이트리스트, UploadService의 문서 용량 제한과 동일하게 20MB로 통일),
    // 전체 제목 레벨(H1~H6, 기본값은 H2/H3뿐) + HTML 코드 삽입 버튼(디자이너가 피그마 등으로 작업한 결과물을
    // 붙여넣기만으로는 서식이 유실되는 경우, 직접 만든 HTML을 그대로 붙여넣어 서식 그대로 반영하기 위함).
    protected static function richEditor(string $name, string $label = '내용'): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->extraInputAttributes(['style' => 'min-height: 260px'])
            ->fileAttachmentsDisk('uploads')
            ->fileAttachmentsDirectory('uploads/editor')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes([
                'image/*',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
            ])
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
