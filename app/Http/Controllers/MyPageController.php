<?php

namespace App\Http\Controllers;

use App\Http\Requests\MyPageUpdateRequest;
use App\Services\BannedWordService;
use App\Services\IdentityVerificationService;
use App\Services\SiteSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MyPageController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()
                ->to('/'.config('app.admin_path', 'admin'))
                ->with('status', __('관리자 계정은 마이페이지에 접근할 수 없습니다. 관리자 페이지를 이용해 주세요.'));
        }

        $reminderMonths = (int) app(SiteSettingService::class)->get('password_change_reminder_months', '6');
        $passwordChangedAt = $user->password_changed_at;
        $monthsSincePasswordChange = $passwordChangedAt ? (int) $passwordChangedAt->diffInMonths(now()) : null;

        return view('mypage.index', [
            'user' => $user,
            'pageTitle' => __('마이페이지'),
            'monthsSincePasswordChange' => $monthsSincePasswordChange,
            'passwordChangeOverdue' => $monthsSincePasswordChange !== null && $monthsSincePasswordChange >= $reminderMonths,
        ]);
    }

    // 회원가입 때 관리자가 켜둔 필드별 필수/선택/숨김(signup_field_*)을 여기서도 그대로 적용한다
    // (RegisterRequest와 동일한 목록을 MyPageUpdateRequest에서 공유). 본인인증이 켜져 있으면
    // name/phone/gender/birthdate는 본인인증을 통해서만 갱신되므로 읽기 전용으로 표시한다.
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            abort(403);
        }

        return view('mypage.edit', [
            'user' => $request->user(),
            'pageTitle' => __('회원정보 수정'),
            'fieldModes' => $this->fieldModes(),
            'identityLocked' => app(IdentityVerificationService::class)->isEnabled(),
        ]);
    }

    public function update(MyPageUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validated();
        $identityLocked = app(IdentityVerificationService::class)->isEnabled();

        if (! empty($validated['nickname']) && app(BannedWordService::class)->check($validated['nickname'], 'nickname')) {
            throw ValidationException::withMessages(['nickname' => __('사용할 수 없는 닉네임입니다.')]);
        }

        $editableFields = $identityLocked
            ? array_diff(MyPageUpdateRequest::editableFields(), MyPageUpdateRequest::identityLockedFields())
            : MyPageUpdateRequest::editableFields();

        // signup_field_*가 'hidden'인 필드나 본인인증으로 잠긴 필드는 폼/$validated에 아예 없다 —
        // 기존 값을 그대로 유지한다. name도 본인인증이 켜져 있으면 폼에서 받지 않으므로 건드리지 않는다.
        $attributes = array_intersect_key($validated, array_flip($editableFields));

        if (! $identityLocked) {
            $attributes['name'] = $validated['name'];
        }

        $user->forceFill($attributes)->save();

        return redirect(front_route('mypage'))->with('status', __('회원정보가 수정되었습니다.'));
    }

    /**
     * @return array<string, string>
     */
    private function fieldModes(): array
    {
        $settings = app(SiteSettingService::class);

        return collect(MyPageUpdateRequest::editableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $settings->get("signup_field_{$field}", 'hidden')])
            ->all();
    }

    public function editPassword(Request $request): View|RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            abort(403);
        }

        return view('mypage.password', ['pageTitle' => __('비밀번호 변경')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('현재 비밀번호가 일치하지 않습니다.')]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        return redirect(front_route('mypage'))->with('status', __('비밀번호가 변경되었습니다.'));
    }

    // 소프트 삭제 + 개인정보 익명화 방식으로 처리한다(하드 삭제 아님) — 이미 작성된 게시글/댓글/문의가
    // 고아 데이터가 되거나 함께 지워지는 것을 막고, 탈퇴 후에도 일정 기간 보관해야 하는 법적 요건에도 대응 가능하다.
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403, __('관리자 계정은 이 화면에서 탈퇴할 수 없습니다.'));
        }

        // 소셜 로그인으로만 가입해 비밀번호가 의미 없는 계정도 있을 수 있으나(랜덤 비밀번호로 생성됨),
        // 일반 가입 회원은 본인 확인을 위해 현재 비밀번호를 반드시 입력하게 한다.
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('비밀번호가 일치하지 않습니다.')]);
        }

        $user->anonymizeAndWithdraw();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(front_route('home'))->with('status', __('탈퇴가 완료되었습니다. 그동안 이용해 주셔서 감사합니다.'));
    }
}
