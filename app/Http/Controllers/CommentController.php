<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CommentController extends Controller
{
    public function store(Request $request, string $slug, int $id): RedirectResponse
    {
        $board = Board::query()->where('slug', $slug)->where('locale', app()->getLocale())->where('is_active', true)->firstOrFail();
        $post = Post::query()->where('board_id', $board->id)->where('is_active', true)->where('is_draft', false)->findOrFail($id);

        if (! $board->allow_comment) {
            abort(403, __('댓글을 사용할 수 없는 게시판입니다.'));
        }

        $userLevel = $request->user()?->level ?? User::LEVEL_GUEST;
        if ($userLevel < $board->min_comment_level && ! $board->allow_anonymous) {
            abort(403, __('댓글 작성 권한이 없습니다.'));
        }

        $rules = [
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ];

        if (! $request->user()) {
            $rules['author_name'] = ['required', 'string', 'max:50'];
            $rules['author_password'] = ['required', 'string', 'min:4', 'max:20'];
        }

        $validated = $request->validate($rules);

        $parent = null;
        $depth = 0;

        if (! empty($validated['parent_id'])) {
            if (! $board->allow_reply) {
                abort(403, __('답글을 사용할 수 없는 게시판입니다.'));
            }

            $parent = Comment::query()->where('post_id', $post->id)->findOrFail($validated['parent_id']);

            if ($parent->parent_id !== null) {
                abort(422, __('답글에는 추가 답글을 작성할 수 없습니다.'));
            }

            $depth = 1;
        }

        $comment = new Comment();
        $comment->post_id = $post->id;
        $comment->parent_id = $parent?->id;
        $comment->depth = $depth;
        $comment->content = strip_tags($validated['content']);
        $comment->ip = $request->ip();

        if ($request->user()) {
            $comment->user_id = $request->user()->id;
        } else {
            $comment->author_name = $validated['author_name'];
            $comment->author_password = Hash::make($validated['author_password']);
        }

        $comment->save();

        return back()->with('status', __('댓글이 등록되었습니다.'))->withFragment('comment-'.$comment->id);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $comment = Comment::query()->findOrFail($id);

        if ($request->user()) {
            if ($comment->user_id !== $request->user()->id && $request->user()->level !== User::LEVEL_ADMIN) {
                abort(403, __('삭제 권한이 없습니다.'));
            }
        } else {
            $request->validate(['author_password' => ['required', 'string']]);

            if ($comment->user_id !== null
                || ! $comment->author_password
                || ! Hash::check((string) $request->input('author_password'), $comment->author_password)
            ) {
                return back()->withErrors(['author_password' => __('비밀번호가 일치하지 않습니다.')]);
            }
        }

        $comment->delete();

        return back()->with('status', __('댓글이 삭제되었습니다.'));
    }
}
