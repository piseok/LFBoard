<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailService;
use App\Services\SiteSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FindIdController extends Controller
{
    public function create(): View
    {
        return view('auth.find-id', ['pageTitle' => __('아이디 찾기')]);
    }

    // 일치하는 계정이 있는지 여부를 화면에 그대로 드러내면 이름+이메일 조합을 무작위로 넣어보는
    // 계정 열거(enumeration) 공격에 악용될 수 있어, 일치 여부와 무관하게 항상 같은 안내 문구만
    // 보여준다 — 실제 결과(아이디)는 오직 가입 시 등록된 이메일로만 확인 가능하다.
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('name', $data['name'])->where('email', $data['email'])->first();

        if ($user) {
            $loginType = app(SiteSettingService::class)->get('login_type', 'email');

            app(EmailService::class)->send('find_id', $user->email, [
                'user_name' => $user->name,
                // login_type이 email이면 로그인 아이디가 곧 가입 이메일이므로, 별도 username이
                // 없거나(nullable) 이메일 로그인 모드일 때는 이메일 자체를 아이디로 안내한다.
                'user_id' => ($loginType === 'username' && $user->username) ? $user->username : $user->email,
            ], $user->locale);
        }

        return back()->with('status', __('입력하신 정보와 일치하는 계정이 있다면 아이디 안내 메일을 발송해 드렸습니다.'));
    }
}
