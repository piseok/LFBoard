<?php

namespace App\Http\Controllers;

use App\Services\IdentityVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class IdentityVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // 게시판 글쓰기 등에서 "본인인증하기" 버튼을 누르면 여기로 온다.
    public function start(Request $request): View|RedirectResponse
    {
        $service = app(IdentityVerificationService::class);

        if (! $service->isEnabled()) {
            return redirect()->back()->with('status', __('본인인증 기능이 아직 설정되지 않았습니다. 관리자에게 문의해 주세요.'));
        }

        session(['identity_verification_return_to' => url()->previous()]);

        try {
            $identityRequest = $service->provider()->buildRequest(route('identity-verification.callback'));
        } catch (Throwable $e) {
            return redirect()->back()->with('status', __('본인인증을 진행할 수 없습니다: :error', ['error' => $e->getMessage()]));
        }

        return view('auth.identity-verification-redirect', ['identityRequest' => $identityRequest]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $service = app(IdentityVerificationService::class);
        $returnTo = session()->pull('identity_verification_return_to', '/');

        if (! $service->isEnabled()) {
            return redirect($returnTo)->with('status', __('본인인증 기능이 아직 설정되지 않았습니다.'));
        }

        try {
            $result = $service->provider()->parseCallback($request->all());
        } catch (Throwable $e) {
            return redirect($returnTo)->with('status', __('본인인증에 실패했습니다: :error', ['error' => $e->getMessage()]));
        }

        $user = $request->user();
        $user->forceFill([
            'ci' => $result->ci,
            'di' => $result->di,
            'name' => $result->name ?: $user->name,
            'phone' => $result->phone ?: $user->phone,
            'birthdate' => $result->birthdate ?: $user->birthdate,
            'gender' => $result->gender ?: $user->gender,
            'phone_verified_at' => now(),
        ])->save();

        return redirect($returnTo)->with('status', __('본인인증이 완료되었습니다.'));
    }
}
