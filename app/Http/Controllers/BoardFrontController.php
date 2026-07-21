<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use App\Services\BannedWordService;
use App\Services\CaptchaService;
use App\Services\HtmlSanitizerService;
use App\Services\UploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BoardFrontController extends Controller
{
    public function index(Request $request, string $slug): View|RedirectResponse
    {
        $board = $this->resolveBoard($slug);

        if ($redirect = $this->denyIfBelowLevel($board->min_read_level)) {
            return $redirect;
        }

        $query = Post::query()
            ->withCount(['comments' => fn ($q) => $q->where('is_active', true)])
            ->with(['files', 'category'])
            ->where('board_id', $board->id)
            ->where('is_active', true)
            ->where('is_draft', false)
            ->where('is_notice', false)
            ->where('is_global_notice', false);

        $this->restrictSecretPosts($query, $request);

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('q')) {
            $keyword = $request->string('q')->toString();
            $type = $request->string('search_type', 'title')->toString();

            if (str_starts_with($type, 'custom:')) {
                $this->applyCustomFieldSearch($query, $board, substr($type, 7), $keyword);
            } else {
                $query->where(function ($q) use ($type, $keyword) {
                    match ($type) {
                        'content' => $q->where('content', 'like', "%{$keyword}%"),
                        'author' => $q->where('author_name', 'like', "%{$keyword}%")
                            ->orWhereHas('user', fn ($u) => $u->where('nickname', 'like', "%{$keyword}%")),
                        default => $q->where('title', 'like', "%{$keyword}%"),
                    };
                });
            }
        }

        // 모집 기간이 있는 게시판(채용공고 등)에서 접수중/접수마감/접수예정으로 필터링할 수
        // 있게 한다. 게시판마다 있을 수도 없을 수도 있는 기능이라, 이 게시판에 모집 기간이
        // 설정된 글이 하나라도 있을 때만 화면에 필터를 보여준다(hasRecruitmentPosts).
        $hasRecruitmentPosts = Post::query()
            ->where('board_id', $board->id)
            ->where(fn ($q) => $q->whereNotNull('recruitment_start_at')->orWhereNotNull('recruitment_end_at'))
            ->exists();

        if ($request->filled('recruitment_status')) {
            $query->recruitmentStatus($request->string('recruitment_status')->toString());
        }

        // 정렬 기준(작성일/조회수)이 같은 값으로 여러 건 묶일 수 있어(글 복사 등으로 작성일이
        // 완전히 같아지는 경우 포함) id를 2차 정렬 기준으로 둬서 순서를 항상 고정한다.
        $query->orderBy($board->order_by === 'views' ? 'views' : 'created_at', 'desc')->orderBy('id', 'desc');

        $posts = $query->paginate($board->per_page)->withQueryString();

        $globalNoticeQuery = Post::query()
            ->withCount(['comments' => fn ($q) => $q->where('is_active', true)])
            ->with(['files', 'category', 'board'])
            ->where('is_active', true)->where('is_draft', false)->where('is_global_notice', true)
            ->whereHas('board', fn ($q) => $q->where('locale', app()->getLocale()));
        $this->restrictSecretPosts($globalNoticeQuery, $request);
        $globalNotices = $globalNoticeQuery->orderByDesc('created_at')->get();

        $boardNoticeQuery = Post::query()
            ->withCount(['comments' => fn ($q) => $q->where('is_active', true)])
            ->with(['files', 'category', 'board'])
            ->where('board_id', $board->id)->where('is_active', true)->where('is_draft', false)
            ->where('is_notice', true)->where('is_global_notice', false);
        $this->restrictSecretPosts($boardNoticeQuery, $request);
        $boardNotices = $boardNoticeQuery->orderByDesc('created_at')->get();

        $notices = $globalNotices->concat($boardNotices);

        $categories = $board->categories()->orderBy('sort_order')->get();

        // 게시판 레이아웃 설정(list/gallery)을 따른다. 단, 전체공지(다른 게시판의 공지)는
        // 갤러리 스킨 안에서도 목록(표) 형태로 노출한다 — gallery.blade.php에서 처리.
        $viewType = $board->layout === 'gallery' ? 'gallery' : 'list';

        return view($this->resolveSkinView($board, $viewType), [
            'board' => $board,
            'posts' => $posts,
            'notices' => $notices,
            'categories' => $categories,
            'hasRecruitmentPosts' => $hasRecruitmentPosts,
            'pageTitle' => $board->name,
        ]);
    }

    // 커스텀필드 검색 — 텍스트 계열(text/textarea/number/date)은 LIKE, select/radio는 정확히
    // 일치하는 값만, checkbox(다중 선택 배열)는 선택지 중 하나라도 포함되면 매치되도록 처리한다.
    // 정의되지 않은 필드 키가 넘어오면(스키마가 바뀐 경우 등) 조용히 무시한다.
    private function applyCustomFieldSearch(Builder $query, Board $board, string $fieldKey, string $keyword): void
    {
        $field = $board->customField($fieldKey);

        if (! $field) {
            return;
        }

        match ($field['type']) {
            'checkbox' => $query->whereJsonContains("custom_fields->{$fieldKey}", $keyword),
            'select', 'radio' => $query->where("custom_fields->{$fieldKey}", $keyword),
            default => $query->where("custom_fields->{$fieldKey}", 'like', "%{$keyword}%"),
        };
    }

    // 비밀글은 작성자 본인과 관리자에게만 목록에 노출한다(제목조차 보이지 않도록 - 상세페이지 접근 차단과 별개로 목록 단계에서 걸러냄).
    private function restrictSecretPosts(Builder $query, Request $request): void
    {
        if ($request->user()?->level === User::LEVEL_ADMIN) {
            return;
        }

        $userId = $request->user()?->id;

        $query->where(function ($q) use ($userId) {
            $q->where('is_secret', false);

            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }

    public function show(Request $request, string $slug, int $id): View|RedirectResponse
    {
        $board = $this->resolveBoard($slug);

        if ($redirect = $this->denyIfBelowLevel($board->min_read_level)) {
            return $redirect;
        }

        $post = Post::query()->where('board_id', $board->id)->findOrFail($id);

        $isOwner = $request->user() && $post->user_id === $request->user()->id;
        $isAdmin = $request->user()?->level === User::LEVEL_ADMIN;

        // 비활성/임시저장 글은 원래 목록 쿼리 단계에서 걸러지지만, 상세페이지는 URL로 직접
        // 접근할 수 있으므로 여기서도 같은 기준으로 막는다. 임시저장은 작성자 본인만 "이어쓰기 전
        // 미리보기"로 열어볼 수 있다 — 관리자에게도 공개하지 않는다(비밀글과 달리 관리자 예외 없음).
        if (! $post->is_active || ($post->is_draft && ! $isOwner)) {
            abort(404);
        }

        if ($post->is_secret && ! $isOwner && ! $isAdmin) {
            abort(403, __('비밀글은 작성자와 관리자만 볼 수 있습니다.'));
        }

        if (! $request->user() || $request->user()->id !== $post->user_id) {
            $post->increment('views');
        }

        $content = $board->use_editor
            ? app(HtmlSanitizerService::class)->clean((string) $post->content)
            : nl2br(e((string) $post->content));

        $comments = $board->allow_comment
            ? $post->comments()
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with(['replies' => fn ($q) => $q->where('is_active', true)->orderBy('created_at')])
                ->orderBy('created_at')
                ->get()
            : collect();

        $canModify = $this->canModify($request, $post);

        // 이전글/다음글은 작성일(created_at) 기준으로 찾는다 — 관리자가 작성일을 직접 바꿀 수
        // 있어서(PostResource) id 순서와 작성일 순서가 어긋날 수 있으므로, 목록 정렬과 마찬가지로
        // created_at을 기준으로 삼는다. 공지(전체공지/게시판공지)는 목록에서도 별도로 상단
        // 고정되고 일반 흐름에 안 섞이므로 이전글/다음글 탐색에서도 제외한다.
        //
        // 글 복사(PostResource::duplicatePost)는 replicate()가 created_at까지 그대로 복사해서
        // 원본과 복사본이 완전히 같은 작성일을 갖게 된다 — created_at만 비교하면 이 둘은 서로에게
        // "이전/다음"이 아닌 것으로 취급되어(같음은 <, > 어느 쪽에도 안 걸림) 탐색에서 통째로
        // 건너뛰어진다. created_at이 같을 때는 id로 한 번 더 비교해 순서를 확정한다.
        $previousQuery = Post::query()
            ->where('board_id', $board->id)
            ->where('is_active', true)
            ->where('is_draft', false)
            ->where('is_notice', false)
            ->where('is_global_notice', false)
            ->where(function ($q) use ($post) {
                $q->where('created_at', '<', $post->created_at)
                    ->orWhere(function ($q2) use ($post) {
                        $q2->where('created_at', $post->created_at)->where('id', '<', $post->id);
                    });
            });
        $this->restrictSecretPosts($previousQuery, $request);
        $previousPost = $previousQuery->orderByDesc('created_at')->orderByDesc('id')->first();

        $nextQuery = Post::query()
            ->where('board_id', $board->id)
            ->where('is_active', true)
            ->where('is_draft', false)
            ->where('is_notice', false)
            ->where('is_global_notice', false)
            ->where(function ($q) use ($post) {
                $q->where('created_at', '>', $post->created_at)
                    ->orWhere(function ($q2) use ($post) {
                        $q2->where('created_at', $post->created_at)->where('id', '>', $post->id);
                    });
            });
        $this->restrictSecretPosts($nextQuery, $request);
        $nextPost = $nextQuery->orderBy('created_at')->orderBy('id')->first();

        return view($this->resolveSkinView($board, 'show'), [
            'board' => $board,
            'post' => $post,
            'content' => $content,
            'comments' => $comments,
            'canModify' => $canModify,
            'previousPost' => $previousPost,
            'nextPost' => $nextPost,
            'pageTitle' => $post->title,
        ]);
    }

    public function create(Request $request, string $slug): View|RedirectResponse
    {
        $board = $this->resolveBoard($slug);

        if ($redirect = $this->denyIfCannotWrite($board, $request)) {
            return $redirect;
        }

        $categories = $board->categories()->orderBy('sort_order')->get();

        // "불러오기" 버튼에서 제목만 보여주면 되므로 필요한 컬럼만 가져온다(글쓰기 화면 자체에서
        // 바로 펼쳐 보여주는 용도라 별도 목록 페이지는 따로 두지 않는다).
        $drafts = $request->user()
            ? Post::query()
                ->where('board_id', $board->id)
                ->where('user_id', $request->user()->id)
                ->where('is_draft', true)
                ->orderByDesc('updated_at')
                ->get(['id', 'title'])
            : collect();

        return view('board.write', [
            'board' => $board,
            'post' => null,
            'categories' => $categories,
            'drafts' => $drafts,
            'pageTitle' => __('글쓰기'),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $board = $this->resolveBoard($slug);

        if ($redirect = $this->denyIfCannotWrite($board, $request)) {
            return $redirect;
        }

        $isDraft = $this->wantsDraftSave($request);

        $validated = $this->validatePost($request, $board, isDraft: $isDraft);

        if (app(BannedWordService::class)->check($validated['title'], 'all')) {
            throw ValidationException::withMessages(['title' => __('제목에 금칙어가 포함되어 있습니다.')]);
        }

        $post = new Post;
        $post->board_id = $board->id;
        $post->category_id = $validated['category_id'] ?? null;
        $post->title = $validated['title'];
        $post->content = $board->use_editor
            ? app(HtmlSanitizerService::class)->clean((string) ($validated['content'] ?? ''))
            : (string) ($validated['content'] ?? '');
        $post->is_secret = (bool) ($validated['is_secret'] ?? false);
        $post->is_draft = $isDraft;
        $post->custom_fields = $validated['custom_fields'] ?? [];
        $post->ip = $request->ip();

        if ($request->user()) {
            $post->user_id = $request->user()->id;
        } else {
            $post->author_name = $validated['author_name'];
            $post->author_password = Hash::make($validated['author_password']);
        }

        $post->save();

        $this->storeFiles($request, $board, $post);

        if ($isDraft) {
            return redirect(front_route('board.edit', ['slug' => $slug, 'id' => $post->id]))->with('status', __('임시저장되었습니다.'));
        }

        return redirect(front_route('board.show', ['slug' => $slug, 'id' => $post->id]))->with('status', __('작성되었습니다.'));
    }

    // 비회원은 계정이 없어 임시저장한 글을 나중에 다시 찾아올 방법이 없으므로, 로그인 회원만
    // 임시저장을 쓸 수 있다(글쓰기 화면에서도 로그인했을 때만 버튼을 보여주지만, 여기서도
    // 서버 단에서 다시 한번 강제한다).
    private function wantsDraftSave(Request $request): bool
    {
        return $request->user() && $request->boolean('save_as_draft');
    }

    // TinyMCE 에디터의 이미지 삽입 버튼에서 호출하는 업로드 엔드포인트.
    // 응답 형식은 TinyMCE의 images_upload_url 규격({"location": "..."})을 따른다.
    public function uploadImage(Request $request, string $slug): JsonResponse
    {
        $board = $this->resolveBoard($slug);

        if (! $board->use_editor || ! $board->allow_image_upload) {
            return response()->json(['error' => __('이 게시판에서는 에디터 이미지 업로드를 사용할 수 없습니다.')], 403);
        }

        $userLevel = $request->user()?->level ?? User::LEVEL_GUEST;
        $canWrite = $request->user() ? $userLevel >= $board->min_write_level : $board->allow_anonymous;

        if (! $canWrite) {
            return response()->json(['error' => __('이미지를 업로드할 권한이 없습니다.')], 403);
        }

        // TinyMCE는 XHR로 업로드하며 Accept 헤더에 의존할 수 없으므로, 검증 실패도 항상 JSON으로 응답한다.
        try {
            $request->validate([
                'file' => ['required', 'image', 'max:5120'],
            ]);

            $path = app(UploadService::class)->upload($request->file('file'), 'images');
        } catch (ValidationException|\RuntimeException $e) {
            $message = $e instanceof ValidationException
                ? implode(' ', $e->validator->errors()->all())
                : $e->getMessage();

            return response()->json(['error' => $message], 422);
        }

        return response()->json(['location' => url($path)]);
    }

    public function edit(Request $request, string $slug, int $id): View|RedirectResponse
    {
        $board = $this->resolveBoard($slug);
        $post = Post::query()->where('board_id', $board->id)->findOrFail($id);

        $this->abortIfHiddenDraft($request, $post);

        if (! $this->canModify($request, $post)) {
            return view('board.verify-password', ['slug' => $slug, 'id' => $id, 'mode' => 'edit']);
        }

        $categories = $board->categories()->orderBy('sort_order')->get();

        return view('board.write', [
            'board' => $board,
            'post' => $post,
            'categories' => $categories,
            'drafts' => collect(), // "불러오기"는 새 글쓰기 화면에서만 보여준다.
            'pageTitle' => __('글 수정'),
        ]);
    }

    public function update(Request $request, string $slug, int $id): RedirectResponse
    {
        $board = $this->resolveBoard($slug);
        $post = Post::query()->where('board_id', $board->id)->findOrFail($id);

        $this->abortIfHiddenDraft($request, $post);

        if (! $this->canModify($request, $post)) {
            abort(403, __('수정 권한이 없습니다.'));
        }

        // 이미 임시저장된 글은 계속 임시저장 상태로 둘 수도, 이번에 "등록"을 눌러 정식 게시로
        // 전환할 수도 있다 — 한 번 정식 게시된 글을 다시 임시저장으로 되돌리지는 않는다
        // (목록에 이미 노출됐던 글이 갑자기 사라지면 혼란스러우므로).
        $isDraft = $post->is_draft && $this->wantsDraftSave($request);

        $validated = $this->validatePost($request, $board, isUpdate: true, isDraft: $isDraft);

        $post->category_id = $validated['category_id'] ?? null;
        $post->title = $validated['title'];
        $post->content = $board->use_editor
            ? app(HtmlSanitizerService::class)->clean((string) ($validated['content'] ?? ''))
            : (string) ($validated['content'] ?? '');
        $post->is_secret = (bool) ($validated['is_secret'] ?? false);
        $post->is_draft = $isDraft;
        $post->custom_fields = $validated['custom_fields'] ?? [];
        $post->save();

        $this->storeFiles($request, $board, $post);

        if ($isDraft) {
            return redirect(front_route('board.edit', ['slug' => $slug, 'id' => $post->id]))->with('status', __('임시저장되었습니다.'));
        }

        return redirect(front_route('board.show', ['slug' => $slug, 'id' => $post->id]))->with('status', __('수정되었습니다.'));
    }

    public function destroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $board = $this->resolveBoard($slug);
        $post = Post::query()->where('board_id', $board->id)->findOrFail($id);

        $this->abortIfHiddenDraft($request, $post);

        if (! $this->canModify($request, $post)) {
            abort(403, __('삭제 권한이 없습니다.'));
        }

        return $this->performDestroy($post, $slug);
    }

    public function verifyPassword(Request $request, string $slug, int $id): RedirectResponse
    {
        $board = $this->resolveBoard($slug);
        $post = Post::query()->where('board_id', $board->id)->findOrFail($id);

        $request->validate(['author_password' => ['required', 'string']]);

        if ($post->user_id !== null
            || ! $post->author_password
            || ! Hash::check((string) $request->input('author_password'), $post->author_password)
        ) {
            return back()->withErrors(['author_password' => __('비밀번호가 일치하지 않습니다.')]);
        }

        session(['post_verified_'.$post->id => true]);

        if ($request->input('mode') === 'delete') {
            return $this->performDestroy($post, $slug);
        }

        return redirect(front_route('board.edit', ['slug' => $slug, 'id' => $id]));
    }

    private function resolveBoard(string $slug): Board
    {
        return Board::query()
            ->where('slug', $slug)
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveSkinView(Board $board, string $type): string
    {
        $skin = $board->skin ?: 'default';
        $view = "board.skins.{$skin}.{$type}";

        return ViewFacade::exists($view) ? $view : "board.skins.default.{$type}";
    }

    private function denyIfBelowLevel(int $minLevel): ?RedirectResponse
    {
        $userLevel = auth()->user()?->level ?? User::LEVEL_GUEST;

        if ($userLevel < $minLevel) {
            return redirect(front_route('login'))->with('status', __('접근 권한이 없습니다. 로그인 후 이용해 주세요.'));
        }

        return null;
    }

    private function denyIfCannotWrite(Board $board, Request $request): ?RedirectResponse
    {
        if ($request->user()) {
            if ($request->user()->level < $board->min_write_level) {
                abort(403, __('글쓰기 권한이 없습니다.'));
            }

            if ($board->requires_identity_verification && ! $request->user()->isIdentityVerified()) {
                return redirect()->route('identity-verification.start')->with('status', __('본인인증 후 글쓰기가 가능합니다.'));
            }

            return null;
        }

        // 본인인증이 필요한 게시판은 비회원 작성 허용 여부와 무관하게 로그인 회원만 쓸 수 있다.
        if ($board->requires_identity_verification) {
            return redirect(front_route('login'))->with('status', __('로그인 후 본인인증을 거쳐 글쓰기가 가능합니다.'));
        }

        if ($board->allow_anonymous) {
            return null;
        }

        return redirect(front_route('login'))->with('status', __('로그인 후 글쓰기가 가능합니다.'));
    }

    private function canModify(Request $request, Post $post): bool
    {
        if ($request->user()) {
            return $post->user_id === $request->user()->id || $request->user()->level === User::LEVEL_ADMIN;
        }

        return $post->user_id === null && session()->get('post_verified_'.$post->id) === true;
    }

    // 임시저장은 작성자 본인만 볼 수 있다 — canModify()의 관리자 예외와 달리, 관리자라도 다른
    // 사람의 임시저장 글은 존재 자체를 몰라야 하므로(403이 아니라 404) 편집/수정/삭제 진입점마다
    // canModify() 검사보다 먼저 호출한다.
    private function abortIfHiddenDraft(Request $request, Post $post): void
    {
        if ($post->is_draft && (! $request->user() || $post->user_id !== $request->user()->id)) {
            abort(404);
        }
    }

    private function validatePost(Request $request, Board $board, bool $isUpdate = false, bool $isDraft = false): array
    {
        $rules = [
            'category_id' => ['nullable', Rule::exists('board_categories', 'id')->where('board_id', $board->id)],
            'title' => ['required', 'string', 'max:255'],
            // 임시저장은 "쓰다 만" 상태를 저장하는 게 목적이라 본문까지 다 채우도록 강제하지 않는다.
            'content' => [$isDraft ? 'nullable' : 'required', 'string'],
            'is_secret' => ['nullable', 'boolean'],
        ];

        if (! $isUpdate && ! $request->user()) {
            $rules['author_name'] = ['required', 'string', 'max:50'];
            $rules['author_password'] = ['required', 'string', 'min:4', 'max:20'];
        }

        if ($board->allow_file) {
            $rules['files'] = ['nullable', 'array', 'max:'.$board->files_per_post];
            $rules['files.*'] = ['file', 'max:20480'];
        }

        if (! $isUpdate && ! $request->user() && $board->use_captcha) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        // 임시저장은 아직 실제로 게시되는 게 아니므로, 정식 게시 시에만 요구되는 본인인증 동의를
        // 여기서 강제하지 않는다(등록/발행 시점에 다시 검증됨 — update()에서 $isDraft=false로
        // 전환할 때는 이 메서드가 $isUpdate=true라서 애초에 이 규칙 자체가 적용되지 않는 기존 동작과 동일).
        if (! $isUpdate && ! $isDraft && $board->requires_identity_verification) {
            $rules['identity_consent'] = ['accepted'];
        }

        // 커스텀필드는 게시판마다 자유롭게 정의되므로(Board::customFieldSchema()), 스키마를 그대로
        // 검증 규칙으로 변환한다. 임시저장은 content와 마찬가지로 필수 여부를 강제하지 않는다.
        foreach ($board->customFieldSchema() as $field) {
            $key = $field['key'];
            $required = ! $isDraft && ($field['required'] ?? false);

            $typeRule = match ($field['type']) {
                'checkbox' => 'array',
                'number' => 'numeric',
                'date' => 'date',
                default => 'string',
            };

            $fieldRules = [$required ? 'required' : 'nullable', $typeRule];

            if (in_array($field['type'], ['select', 'radio'], true)) {
                $fieldRules[] = Rule::in($field['options'] ?? []);
            }

            $rules["custom_fields.{$key}"] = $fieldRules;

            if ($field['type'] === 'checkbox') {
                $rules["custom_fields.{$key}.*"] = [Rule::in($field['options'] ?? [])];
            }
        }

        $validated = $request->validate($rules, [
            'identity_consent.accepted' => __('개인정보 이용 동의가 필요합니다.'),
        ]);

        if (! $isUpdate && ! $request->user() && $board->use_captcha) {
            if (! app(CaptchaService::class)->verify((string) $request->input('captcha_token'))) {
                throw ValidationException::withMessages(['captcha_token' => __('보안 인증에 실패했습니다.')]);
            }
        }

        return $validated;
    }

    private function storeFiles(Request $request, Board $board, Post $post): void
    {
        if (! $board->allow_file || ! $request->hasFile('files')) {
            return;
        }

        $uploadService = app(UploadService::class);
        $existingCount = $post->files()->count();

        foreach ($request->file('files') as $file) {
            if ($existingCount >= $board->files_per_post) {
                break;
            }

            $path = $uploadService->upload($file, 'files');

            $post->files()->create([
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'sort_order' => $existingCount,
            ]);

            $existingCount++;
        }
    }

    private function performDestroy(Post $post, string $slug): RedirectResponse
    {
        session()->forget('post_verified_'.$post->id);
        $post->delete();

        return redirect(front_route('board.index', ['slug' => $slug]))->with('status', __('삭제되었습니다.'));
    }
}
