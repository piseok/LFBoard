<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\EmailService;
use App\Services\SiteSettingService;
use App\Services\SmsService;
use App\Services\UploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $inquiries = $request->user()
            ? Inquiry::query()
                ->where('user_id', $request->user()->id)
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString()
            : null;

        return view('inquiry.index', [
            'inquiries' => $inquiries,
            'pageTitle' => __('1:1 상담'),
        ]);
    }

    public function create(Request $request): View
    {
        $categories = app(SiteSettingService::class)->getInquiryCategories();

        return view('inquiry.write', [
            'categories' => $categories,
            'defaultType' => $request->string('type', 'general')->toString(),
            'pageTitle' => __('1:1 상담 작성'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'type' => ['nullable', 'string', 'in:general,quick,footer'],
            'category' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
        ];

        if (! $request->user()) {
            $rules['author_password'] = ['required', 'string', 'min:4', 'max:20'];
        }

        $settings = app(SiteSettingService::class);
        $captchaEnabled = ! $request->user()
            && $settings->get('captcha_apply_inquiry') === '1'
            && ! empty($settings->get('captcha_provider'));

        if ($captchaEnabled) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        if ($captchaEnabled && ! app(CaptchaService::class)->verify((string) $request->input('captcha_token'))) {
            throw ValidationException::withMessages(['captcha_token' => __('보안 인증에 실패했습니다.')]);
        }

        $inquiry = new Inquiry();
        $inquiry->user_id = $request->user()?->id;
        $inquiry->type = $validated['type'] ?? 'general';
        $inquiry->locale = app()->getLocale();
        $inquiry->category = $validated['category'] ?? null;
        $inquiry->name = $validated['name'];
        $inquiry->email = $validated['email'] ?? $request->user()?->email;
        $inquiry->phone = $validated['phone'] ?? null;
        $inquiry->title = $validated['title'];
        $inquiry->content = $validated['content'];
        $inquiry->status = 'pending';
        $inquiry->is_active = true;

        if (! $request->user()) {
            $inquiry->author_password = Hash::make($validated['author_password']);
        }

        if ($request->hasFile('file')) {
            $inquiry->file_path = app(UploadService::class)->upload($request->file('file'), 'files');
        }

        $inquiry->save();

        // 비회원이 방금 작성한 문의는 별도 인증 없이 바로 조회 가능하도록 세션에 인증 플래그를 남긴다.
        if (! $request->user()) {
            session(['inquiry_verified_'.$inquiry->id => true]);
        }

        $this->notify($inquiry);

        return redirect(front_route('inquiry.show', $inquiry->id))->with('status', __('상담 신청이 접수되었습니다.'));
    }

    public function show(Request $request, int $id): View
    {
        $inquiry = Inquiry::query()->findOrFail($id);

        if (! $this->canView($request, $inquiry)) {
            return view('inquiry.verify-password', ['id' => $id]);
        }

        return view('inquiry.show', ['inquiry' => $inquiry, 'pageTitle' => $inquiry->title]);
    }

    public function verifyPassword(Request $request, int $id): RedirectResponse
    {
        $inquiry = Inquiry::query()->findOrFail($id);

        $request->validate(['author_password' => ['required', 'string']]);

        if ($inquiry->user_id !== null
            || ! $inquiry->author_password
            || ! Hash::check((string) $request->input('author_password'), $inquiry->author_password)
        ) {
            return back()->withErrors(['author_password' => __('비밀번호가 일치하지 않습니다.')]);
        }

        session(['inquiry_verified_'.$inquiry->id => true]);

        return redirect(front_route('inquiry.show', $id));
    }

    private function canView(Request $request, Inquiry $inquiry): bool
    {
        if ($request->user()) {
            return $inquiry->user_id === $request->user()->id || $request->user()->level === User::LEVEL_ADMIN;
        }

        return $inquiry->user_id === null && session()->get('inquiry_verified_'.$inquiry->id) === true;
    }

    private function notify(Inquiry $inquiry): void
    {
        $settings = app(SiteSettingService::class);
        $emailService = app(EmailService::class);

        $variables = [
            'inquiry_name' => $inquiry->name,
            'inquiry_email' => $inquiry->email ?: '-',
            'inquiry_phone' => $inquiry->phone ?: '-',
            'inquiry_category' => $inquiry->category ?: '-',
            'inquiry_type' => $inquiry->type,
            'inquiry_title' => $inquiry->title,
            'inquiry_content' => $inquiry->content,
            'admin_url' => url('/admin/inquiries/'.$inquiry->id.'/edit'),
        ];

        // 관리자 알림은 관리자가 어느 언어로 접속하든 항상 한국어로 발송한다(관리자 패널 자체가
        // 언어 라우팅과 무관한 한국어 전용 도구라서, 방문자 locale과 분리해서 다뤄야 함).
        $adminEmail = $settings->get('admin_email');
        if ($adminEmail) {
            $emailService->send('inquiry_received_admin', $adminEmail, $variables);
        }

        if ($inquiry->email) {
            $emailService->send('inquiry_received_user', $inquiry->email, $variables, $inquiry->locale);
        }

        $adminPhone = $settings->get('admin_phone');
        if ($adminPhone && $settings->get('sms_enabled') === '1') {
            app(SmsService::class)->send(
                $adminPhone,
                "[{$variables['inquiry_type']}] 새 1:1 문의: {$inquiry->title} ({$inquiry->name})"
            );
        }
    }
}
