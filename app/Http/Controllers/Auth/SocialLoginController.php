<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\LoginCountryAlertService;
use App\Services\SiteSettingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    private const PROVIDERS = ['google', 'kakao', 'naver'];

    public function __construct()
    {
        $this->middleware('guest');
    }

    // 소셜 로그인 라우트 자체는 언어별로 등록되어 있지 않아(OAuth 콜백 URL은 공급사에 고정으로 등록해야
    // 하므로 언어 접두사를 붙일 수 없음), 시작 시점의 언어를 쿼리스트링으로 받아 세션에 저장해뒀다가
    // 콜백 완료 후 그 언어로 복귀시킨다(안 하면 어떤 언어에서 로그인해도 항상 기본 언어 홈으로 이동해버림).
    public function redirect(string $provider, Request $request): RedirectResponse
    {
        $this->abortIfNotConfigured($provider);
        $this->configureDriver($provider);

        session()->put('social_login_locale', $this->resolveRequestedLocale($request));

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->abortIfNotConfigured($provider);
        $this->configureDriver($provider);

        $locale = session()->pull('social_login_locale', Language::defaultCode());
        $routePrefix = Language::routeNamePrefix($locale);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            // 이 라우트는 언어 접두사가 없어(위 주석 참고) app()->getLocale()이 이 회원의 실제 언어와
            // 다를 수 있다 — 플래시 메시지는 지금 바로 문자열로 굳어지므로, __()의 세 번째 인자로
            // 위에서 복원한 $locale을 명시해 콜백 시점의 우연한 로케일이 아니라 이 회원의 언어로 번역한다.
            return redirect()->route("{$routePrefix}login")->with('status', __('소셜 로그인 인증에 실패했습니다. 다시 시도해 주세요.', [], $locale));
        }

        $user = $this->findOrCreateUser($provider, $socialUser, $locale);

        if (! $user->is_active) {
            return redirect()->route("{$routePrefix}login")->with('status', __('가입 신청이 접수되었습니다. 관리자 승인 후 이용하실 수 있습니다.', [], $locale));
        }

        if ($user->isDormant()) {
            session()->put('dormant_reactivation_user_id', $user->id);

            return redirect()->route('dormant.notice');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        app(LoginCountryAlertService::class)->handleLogin($user, $request->ip());
        Auth::login($user, remember: true);

        return redirect(rtrim("/{$routePrefix}", '.'));
    }

    private function resolveRequestedLocale(Request $request): string
    {
        $requested = (string) $request->query('locale', '');
        $activeCodes = Language::query()->where('is_active', true)->pluck('code')->all();

        return in_array($requested, $activeCodes, true) ? $requested : Language::defaultCode();
    }

    private function abortIfNotConfigured(string $provider): void
    {
        $settings = app(SiteSettingService::class);

        abort_unless(
            in_array($provider, self::PROVIDERS, true)
                && $settings->get("social_{$provider}_enabled") === '1'
                && filled($settings->get("social_{$provider}_client_id")),
            404
        );
    }

    // Socialite/드라이버 확장 패키지는 관례상 config('services.{provider}.*')를 읽으므로,
    // 요청 시점에 DB(SiteSettings)에 저장된 값을 그 자리에 주입해 사용한다(.env 대신 관리자 화면에서 바로 변경 가능).
    private function configureDriver(string $provider): void
    {
        $settings = app(SiteSettingService::class);

        config([
            "services.{$provider}.client_id" => $settings->get("social_{$provider}_client_id"),
            "services.{$provider}.client_secret" => $settings->get("social_{$provider}_client_secret"),
            "services.{$provider}.redirect" => route('social.callback', $provider),
        ]);
    }

    private function findOrCreateUser(string $provider, SocialiteUser $socialUser, string $locale): User
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account) {
            $account->update([
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);

            return $account->user;
        }

        // 공급사가 이메일을 내려주면(대부분 인증된 이메일) 기존 계정과 자동으로 연결한다.
        // 이메일을 아예 제공하지 않는 경우(카카오 이메일 동의 미획득 등)에는 유실 없이
        // 새 계정을 만들 수 있도록 provider+id 기반의 대체 이메일을 사용한다.
        $email = $socialUser->getEmail() ?: "{$provider}_{$socialUser->getId()}@social.local";

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $settings = app(SiteSettingService::class);
            $approvalRequired = $settings->get('signup_approval_required') === '1';

            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: __('회원', [], $locale),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'level' => User::LEVEL_MEMBER,
                'is_active' => ! $approvalRequired,
                'unsubscribe_token' => Str::random(32),
                'email_verified_at' => now(),
                'locale' => $locale,
            ]);

            event(new Registered($user));
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
        ]);

        return $user;
    }
}
