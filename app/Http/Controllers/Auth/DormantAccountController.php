<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SiteSettingService;
use App\Services\SmsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

// 로그인 시도 시점에 비밀번호로 이미 1차 확인이 끝난 상태이므로, 버튼 클릭만으로 즉시 휴면 해제한다
// (딥리서치 결과 실제 서비스들도 이메일 링크 경유가 아니라 로그인 시점 즉시 해제 방식이 표준).
class DormantAccountController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.dormant-notice', [
            'pageTitle' => __('휴면 계정 안내'),
            'requiresSms' => $this->requiresSmsVerification($user),
        ]);
    }

    public function sendSmsCode(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        abort_unless($user && $this->requiresSmsVerification($user), 403);

        $code = (string) random_int(100000, 999999);
        Cache::put('dormant_reactivation_code_'.$user->id, $code, now()->addMinutes(5));

        app(SmsService::class)->send($user->phone, "[인증번호] {$code} (5분 이내 입력해 주세요)");

        return back()->with('status', __('인증번호를 발송했습니다.'));
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        abort_unless($user, 403);

        if ($this->requiresSmsVerification($user)) {
            $request->validate(['sms_code' => ['required', 'string']]);

            $cachedCode = Cache::get('dormant_reactivation_code_'.$user->id);

            if (! $cachedCode || $cachedCode !== $request->input('sms_code')) {
                throw ValidationException::withMessages(['sms_code' => __('인증번호가 일치하지 않거나 만료되었습니다.')]);
            }

            Cache::forget('dormant_reactivation_code_'.$user->id);
        }

        $user->forceFill([
            'dormant_at' => null,
            'dormant_notice_sent_at' => null,
            'last_login_at' => now(),
        ])->save();

        $request->session()->forget('dormant_reactivation_user_id');

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect(front_route('home'))->with('status', __('휴면 상태가 해제되었습니다. 다시 찾아주셔서 감사합니다.'));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('dormant_reactivation_user_id');

        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        if (! $user || ! $user->isDormant()) {
            $request->session()->forget('dormant_reactivation_user_id');

            return null;
        }

        // 이 컨트롤러의 라우트(/dormant/*)는 로그인 라우트와 달리 언어 접두사가 없어(공급사 OAuth
        // 콜백처럼 고정 URL이 필요한 건 아니지만, 로그인 실패 이전에는 아직 회원을 특정할 수 없어
        // 애초에 접두사를 붙일 수 없었음) app()->getLocale()이 이 회원이 실제로 로그인을 시도한
        // 언어와 무관하게 기본 언어로 고정돼 있었다 — 회원의 저장된 locale로 명시적으로 맞춘다.
        app()->setLocale($user->locale ?: \App\Models\Language::defaultCode());

        return $user;
    }

    private function requiresSmsVerification(User $user): bool
    {
        return app(SiteSettingService::class)->get('dormant_reactivation_requires_sms') === '1'
            && app(SmsService::class)->isEnabled()
            && filled($user->phone);
    }
}
