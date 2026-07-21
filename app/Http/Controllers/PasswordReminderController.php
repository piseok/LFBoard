<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordReminderController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.password-reminder', [
            'pageTitle' => __('비밀번호 변경 안내'),
        ]);
    }

    // "나중에 하기" — 강제 차단이 아니므로 세션에만 남기고(DB 기록 없음) 이번 로그인 세션 동안만
    // 다시 묻지 않는다. 재로그인하면 다시 확인한다.
    public function dismiss(Request $request): RedirectResponse
    {
        $request->session()->put('password_reminder_dismissed', true);

        return redirect(front_route('home'));
    }
}
