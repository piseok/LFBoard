@extends('layouts.subpage')

@section('subcontent')
    <x-sub-header>
        {{ $post ? __('글 수정') : __('글쓰기') }}
        @if ($post && $post->is_draft)
            <span class="badge badge-warning">{{ __('임시저장') }}</span>
        @endif
    </x-sub-header>

    @if (! $post && $drafts->isNotEmpty())
        <details class="draft-loader">
            <summary>{{ __('임시저장글 불러오기') }} ({{ $drafts->count() }})</summary>
            <ul>
                @foreach ($drafts as $draft)
                    <li><a href="{{ front_route('board.edit', ['slug' => $board->slug, 'id' => $draft->id]) }}">{{ $draft->title !== '' ? $draft->title : __('(제목 없음)') }}</a></li>
                @endforeach
            </ul>
        </details>
    @endif

    <form method="POST"
          action="{{ $post ? front_route('board.update', ['slug' => $board->slug, 'id' => $post->id]) : front_route('board.store', $board->slug) }}"
          enctype="multipart/form-data">
        @csrf
        @if ($post)
            @method('PUT')
        @endif

        @if ($categories->isNotEmpty())
            <div class="form-group">
                <label for="category_id">{{ __('카테고리') }}</label>
                <select id="category_id" name="category_id">
                    <option value="">{{ __('선택 안 함') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $post->category_id ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @foreach ($board->customFieldSchema() as $field)
            @php
                $fieldKey = $field['key'];
                $fieldRequired = (bool) ($field['required'] ?? false);
                $fieldValue = old("custom_fields.{$fieldKey}", $post->custom_fields[$fieldKey] ?? null);
            @endphp
            <div class="form-group">
                <label for="custom_{{ $fieldKey }}">{{ $field['label'] }}</label>
                @switch($field['type'])
                    @case('textarea')
                        <textarea id="custom_{{ $fieldKey }}" name="custom_fields[{{ $fieldKey }}]" @if ($fieldRequired) required @endif>{{ $fieldValue }}</textarea>
                        @break
                    @case('number')
                        <input type="number" id="custom_{{ $fieldKey }}" name="custom_fields[{{ $fieldKey }}]" value="{{ $fieldValue }}" @if ($fieldRequired) required @endif>
                        @break
                    @case('date')
                        <input type="date" id="custom_{{ $fieldKey }}" name="custom_fields[{{ $fieldKey }}]" value="{{ $fieldValue }}" @if ($fieldRequired) required @endif>
                        @break
                    @case('select')
                        <select id="custom_{{ $fieldKey }}" name="custom_fields[{{ $fieldKey }}]" @if ($fieldRequired) required @endif>
                            <option value="">{{ __('선택 안 함') }}</option>
                            @foreach ($field['options'] ?? [] as $option)
                                <option value="{{ $option }}" @selected($fieldValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @break
                    @case('radio')
                        @foreach ($field['options'] ?? [] as $option)
                            <label style="display:block; font-weight:400;">
                                <input type="radio" name="custom_fields[{{ $fieldKey }}]" value="{{ $option }}" style="width:auto;" @checked($fieldValue === $option) @if ($fieldRequired) required @endif>
                                {{ $option }}
                            </label>
                        @endforeach
                        @break
                    @case('checkbox')
                        @php $fieldValues = (array) $fieldValue; @endphp
                        @foreach ($field['options'] ?? [] as $option)
                            <label style="display:block; font-weight:400;">
                                <input type="checkbox" name="custom_fields[{{ $fieldKey }}][]" value="{{ $option }}" style="width:auto;" @checked(in_array($option, $fieldValues))>
                                {{ $option }}
                            </label>
                        @endforeach
                        @break
                    @default
                        <input type="text" id="custom_{{ $fieldKey }}" name="custom_fields[{{ $fieldKey }}]" value="{{ $fieldValue }}" @if ($fieldRequired) required @endif>
                @endswitch
                @error("custom_fields.{$fieldKey}")<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="form-group">
            <label for="title">{{ __('제목') }}</label>
            <input type="text" id="title" name="title" maxlength="255" required value="{{ old('title', $post->title ?? '') }}">
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        @guest
            @if (! $post)
                <div class="form-row">
                    <div class="form-group">
                        <label for="author_name">{{ __('이름') }}</label>
                        <input type="text" id="author_name" name="author_name" maxlength="50" required value="{{ old('author_name') }}">
                    </div>
                    <div class="form-group">
                        <label for="author_password">{{ __('비밀번호') }}</label>
                        <input type="password" id="author_password" name="author_password" maxlength="20" required>
                        <p class="hint">{{ __('수정/삭제 시 필요합니다.') }}</p>
                    </div>
                </div>
            @endif
        @endguest

        <div class="form-group">
            <label for="content">{{ __('내용') }}</label>
            <textarea
                id="content"
                name="content"
                rows="14"
                required
                @if ($board->use_editor)
                    class="tinymce-editor"
                    data-tinymce-base="{{ asset('js/vendor/tinymce') }}"
                    data-image-upload-url="{{ $board->allow_image_upload ? front_route('board.upload-image', $board->slug) : '' }}"
                @endif
            >{{ old('content', $post->content ?? '') }}</textarea>
            @error('content')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_secret" value="1" style="width:auto;" @checked(old('is_secret', $post->is_secret ?? false))>
                {{ __('비밀글로 작성') }}
            </label>
        </div>

        @if ($board->allow_file)
            <div class="form-group">
                <label for="files">{{ __('첨부파일 (최대 :count개)', ['count' => $board->files_per_post]) }}</label>
                <input type="file" id="files" name="files[]" multiple>
                @error('files.*')<p class="field-error">{{ $message }}</p>@enderror

                @if ($post && $post->files->isNotEmpty())
                    <ul class="board-file-list">
                        @foreach ($post->files as $file)
                            <li>{{ $file->original_name }} ({{ number_format($file->file_size / 1024, 1) }} KB)</li>
                        @endforeach
                    </ul>
                    <p class="hint">{{ __('기존 첨부파일은 유지됩니다. 새 파일은 남은 개수만큼 추가됩니다.') }}</p>
                @endif
            </div>
        @endif

        @guest
            @if (! $post && $board->use_captcha)
                @include('partials.auth.captcha')
            @endif
        @endguest

        @if (! $post && $board->requires_identity_verification)
            <div class="form-group">
                <label>
                    <input type="checkbox" name="identity_consent" value="1" style="width:auto;" required @checked(old('identity_consent'))>
                    {{ $board->identity_verification_consent_text }}
                </label>
                @error('identity_consent')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endif

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $post ? __('수정 완료') : __('등록') }}</button>
            @auth
                <button type="submit" name="save_as_draft" value="1" class="btn">{{ __('임시저장') }}</button>
            @endauth
            @if ($post && $post->is_draft)
                {{-- 임시저장 글은 "취소"에 남겨둘 발행본이 없으므로, 취소 = 임시저장 글 삭제로 취급한다. --}}
            @else
                <a href="{{ $post ? front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) : front_route('board.index', $board->slug) }}" class="btn">{{ __('취소') }}</a>
            @endif
        </div>
    </form>

    @if ($post && $post->is_draft)
        <form method="POST" action="{{ front_route('board.destroy', ['slug' => $board->slug, 'id' => $post->id]) }}" data-confirm="{{ __('임시저장 글이 삭제됩니다. 취소하시겠습니까?') }}" style="margin-top: 12px; display:inline-block;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn">{{ __('취소') }}</button>
        </form>
    @elseif ($post)
        <form method="POST" action="{{ front_route('board.destroy', ['slug' => $board->slug, 'id' => $post->id]) }}" data-confirm="{{ __('삭제하시겠습니까?') }}" style="margin-top: 12px;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">{{ __('글 삭제') }}</button>
        </form>
    @endif

    @if ($board->use_editor)
    @push('scripts')
        <script src="{{ asset('js/vendor/tinymce/tinymce.min.js') }}" defer></script>
    @endpush
@endif
@endsection
