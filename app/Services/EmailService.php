<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly HtmlSanitizerService $sanitizer,
    ) {}

    /**
     * 템플릿 변수 치환 후 Mail 발송.
     *
     * @param  string|array<int, string>  $to
     * @param  array<string, string>  $variables
     * @param  string|null  $locale  수신자의 선호 언어. 해당 언어 템플릿이 없으면 기본 언어로 자동 폴백(EmailTemplate::findByType 참고).
     */
    public function send(string $type, string|array $to, array $variables = [], ?string $locale = null): bool
    {
        try {
            // 1. site_settings에서 email_{type}_enabled 확인.
            // email_verification 타입은 이름 자체가 "email_"로 시작해 일반 규칙(email_{type}_enabled)을
            // 그대로 적용하면 email_email_verification_enabled가 되어 실제 설정 키(email_verification_enabled)와
            // 어긋나므로 이 타입만 예외 처리한다.
            $enabledKey = $type === 'email_verification' ? 'email_verification_enabled' : "email_{$type}_enabled";
            if ($this->siteSettings->get($enabledKey) !== '1') {
                return false;
            }

            // 2. 템플릿 조회 및 활성 여부 확인
            $template = EmailTemplate::findByType($type, $locale);
            if (! $template || ! $template->is_active) {
                return false;
            }

            $this->applySmtpConfig();

            $variables = array_merge(['site_name' => $this->siteSettings->getLocalized('site_name', $template->locale, config('app.name'))], $variables);

            // 4. subject / body 치환
            $subject = $this->sanitizeSubject($this->render($template->subject, $variables));
            $body = $this->sanitizer->clean($this->render($template->body, $variables));

            $recipients = is_array($to) ? $to : [$to];
            $recipients = array_filter($recipients);

            if (empty($recipients)) {
                return false;
            }

            // 실제로 찾은 템플릿의 언어(요청 언어가 아니라 폴백 후 최종 언어)로 메일 래퍼(emails/template.blade.php의
            // "본 메일은 발신 전용입니다" 등 고정 문구)를 렌더링한다. 큐 워커에서 발송되는 경우
            // app()->getLocale()이 요청 시점과 다를 수 있어 Mailable::locale()로 렌더링 시점에만 명시적으로 고정한다.
            Mail::to($recipients)->send((new TemplateMail($subject, $body))->locale($template->locale));

            return true;
        } catch (Throwable $e) {
            // 5. 발송 실패 시 예외를 catch하고 false 반환 (사이트 동작에 영향 없도록)
            Log::error("EmailService::send failed for type [{$type}]: ".$e->getMessage());

            return false;
        }
    }

    /**
     * {{변수명}} 치환 처리.
     *
     * @param  array<string, string>  $variables
     */
    private function render(string $template, array $variables): string
    {
        $rendered = $template;

        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', (string) $value, $rendered);
        }

        if (preg_match_all('/\{\{(\w+)\}\}/', $rendered, $matches)) {
            Log::warning('EmailService: unresolved template variables: '.implode(', ', $matches[1]));
        }

        return $rendered;
    }

    // 이메일 헤더 인젝션 방지: subject에 개행 문자 포함 시 제거
    private function sanitizeSubject(string $subject): string
    {
        return str_replace(["\r", "\n"], '', $subject);
    }

    // site_settings에 SMTP 정보가 입력되어 있으면 .env 대신 그 값을 사용한다.
    // 관리자가 서버 파일(.env)을 직접 건드리지 않고도 관리자 화면에서 SMTP를 구성할 수 있게 한다.
    private function applySmtpConfig(): void
    {
        $host = $this->siteSettings->get('mail_host');

        if (empty($host)) {
            return;
        }

        $scheme = match ($this->siteSettings->get('mail_encryption')) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) ($this->siteSettings->get('mail_port') ?: 587),
            'mail.mailers.smtp.username' => $this->siteSettings->get('mail_username') ?: null,
            'mail.mailers.smtp.password' => $this->siteSettings->get('mail_password') ?: null,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $this->siteSettings->get('mail_from_address') ?: config('mail.from.address'),
            'mail.from.name' => $this->siteSettings->get('mail_from_name') ?: config('mail.from.name'),
        ]);
    }
}
