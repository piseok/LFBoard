<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Language;
use App\Models\Policy;
use App\Models\User;
use App\Services\PhoneCountryService;
use App\Services\SiteSettingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('throttle:5,1');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->create($request->validated());

        event(new Registered($user));

        $settings = app(SiteSettingService::class);
        $approvalRequired = $settings->get('signup_approval_required') === '1';

        if ($approvalRequired) {
            $user->forceFill(['is_active' => false])->save();

            return redirect()->route(Language::routeNamePrefix().'login')
                ->with('status', __('가입 신청이 접수되었습니다. 관리자 승인 후 이용하실 수 있습니다.'));
        }

        $this->guard()->login($user);

        return $this->registered($request, $user) ?: redirect($this->redirectPath());
    }

    // 회원가입 라우트 자체가 언어별로 등록되어 있어(routes/web.php) app()->getLocale()이 가입 화면의
    // 언어를 정확히 반영한다 — 기본 경로('/') 대신 그 언어의 홈으로 돌아가도록 오버라이드.
    public function redirectPath(): string
    {
        return rtrim('/'.Language::routeNamePrefix(), '.');
    }

    protected function create(array $data): User
    {
        $settings = app(SiteSettingService::class);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'level' => User::LEVEL_MEMBER,
            'nickname' => $data['nickname'] ?? null,
            'phone' => app(PhoneCountryService::class)->normalize($data['phone_country'] ?? null, $data['phone'] ?? null),
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'homepage' => $data['homepage'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => true,
            'marketing_agreed' => (bool) ($data['policy_marketing'] ?? false),
            'marketing_agreed_at' => ! empty($data['policy_marketing']) ? now() : null,
            'unsubscribe_token' => Str::random(32),
            'locale' => app()->getLocale(),
        ]);

        // 실제로 체크한 약관만(필수는 검증상 항상 체크됨, 선택인 마케팅은 체크했을 때만) 동의 기록을
        // 남긴다 — 이후 약관 버전이 바뀌었을 때 재동의가 필요한지 판단하는 기준이 된다.
        foreach (Policy::activeForLocale(app()->getLocale()) as $policy) {
            if (! empty($data["policy_{$policy->type}"])) {
                $user->recordPolicyConsent($policy->type, app()->getLocale(), $policy->version);
            }
        }

        return $user;
    }
}
