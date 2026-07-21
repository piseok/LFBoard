<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CaptchaService;
use App\Services\LoginCountryAlertService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
        $this->middleware('throttle:5,1')->only('login');
    }

    // login_type 설정(email/username)에 따라 인증에 사용할 필드를 동적으로 결정한다.
    public function username(): string
    {
        return app(SiteSettingService::class)->get('login_type', 'email') === 'username'
            ? 'username'
            : 'email';
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
        ]);

        $settings = app(SiteSettingService::class);

        if ($settings->get('captcha_apply_login') === '1' && ! empty($settings->get('captcha_provider'))) {
            if (! app(CaptchaService::class)->verify((string) $request->input('captcha_token'))) {
                throw ValidationException::withMessages(['captcha_token' => __('보안 인증에 실패했습니다.')]);
            }
        }
    }

    // 비밀번호로 1차 인증까지 통과한 시점이라, 휴면 계정이면 즉시 로그아웃시키고
    // "휴면 해제" 안내 화면으로 보낸다(휴면계정 판단 기준인 last_login_at은 여기서 갱신하지 않고
    // 해제 완료 시점에 갱신한다 — 갱신해버리면 재로그인 즉시 휴면이 해제된 것처럼 보여 안내 화면을 건너뛰게 됨).
    protected function authenticated(Request $request, $user): ?RedirectResponse
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isDormant()) {
            $this->guard()->logout();
            $request->session()->put('dormant_reactivation_user_id', $user->id);

            return redirect()->route('dormant.notice');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        app(LoginCountryAlertService::class)->handleLogin($user, $request->ip());

        return null;
    }
}
