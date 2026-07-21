<?php

use App\Http\Middleware\AdminDebugMode;
use App\Http\Middleware\CheckUserLevel;
use App\Http\Middleware\ConfigureSessionSecurity;
use App\Http\Middleware\DetectLocale;
use App\Http\Middleware\EnsurePasswordChangeReminder;
use App\Http\Middleware\EnsureRequiredPolicyConsent;
use App\Http\Middleware\RecordVisit;
use App\Http\Middleware\RequireInstallation;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SiteIpBlocklist;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.level' => CheckUserLevel::class,
            'record.visit' => RecordVisit::class,
            'locale' => DetectLocale::class,
            'policy.consent' => EnsureRequiredPolicyConsent::class,
            'password.reminder' => EnsurePasswordChangeReminder::class,
        ]);

        $middleware->web(prepend: [
            RequireInstallation::class,
            ConfigureSessionSecurity::class,
        ]);

        $middleware->web(append: [
            // 세션이 시작된 뒤(StartSession 이후)에 등록해야 $request->user()로 관리자 여부를
            // 정확히 판별할 수 있다 — prepend에 두면 세션 시작 전이라 항상 비로그인으로 오판된다.
            SiteIpBlocklist::class,
            AdminDebugMode::class,
            SecurityHeaders::class,
        ]);

        // cookie_consent는 순수 JS(document.cookie)로 직접 설정하는 평문 쿠키라, 암호화된
        // 쿠키로 취급하면 MAC 검증 실패로 항상 null 처리된다(쿠키 동의 배너, 15번 항목).
        $middleware->encryptCookies(except: ['cookie_consent']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419(CSRF 토큰 불일치)는 Laravel이 HttpException 계열을 "정상적인 접근 거부"로 취급해
        // report()가 shouldntReport()에서 걸려 기본적으로 로그에 안 남는다. report()/reportable()
        // 콜백도 같은 이유로 호출 자체가 안 되므로, 응답을 만들기 위해 항상 실행되는 render()
        // 콜백에 로깅만 얹고 null을 반환해(=Laravel 기본 419 응답 생성은 그대로 둠) 세션/쿠키
        // 문제를 서버 로그로 진단할 수 있게 한다.
        // Handler::prepareException()이 TokenMismatchException을 render 단계 전에 이미
        // HttpException(419, ...)으로 바꿔버려서, 타입을 TokenMismatchException으로 걸면
        // 절대 안 걸린다(실제로 겪은 문제) — 반드시 HttpException을 받아 상태코드로 걸러야 한다.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            Log::warning('CSRF 토큰 불일치(419)', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return null;
        });
    })->create();

// 공유호스팅에서 도큐먼트 루트를 public/ 하위로 지정할 수 없어 저장소 전체를 그대로 웹 루트에
// 올리는 배포 구조(main 브랜치)에서는 public/ 폴더 자체가 없다(index.php 등이 저장소 루트로
// 평탄화되어 있음). 이 경우에만 공개 경로를 저장소 루트로 전환한다 — public/이 실제로 존재하는
// 로컬 개발 환경에는 영향이 없다.
if (! is_dir($app->basePath('public'))) {
    $app->usePublicPath($app->basePath());
}

return $app;
