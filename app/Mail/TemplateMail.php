<?php

namespace App\Mail;

use App\Services\SiteSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        $settings = app(SiteSettingService::class);

        $fromAddress = $settings->get('mail_from_address') ?: config('mail.from.address');
        $fromName = $settings->get('mail_from_name') ?: config('mail.from.name');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        $settings = app(SiteSettingService::class);

        return new Content(
            view: 'emails.template',
            with: [
                'body' => $this->mailBody,
                // Mailable::send()가 ->locale()로 지정된 언어로 app()->getLocale()을 감싸 build()를
                // 실행하므로(EmailService::send() 참고), 여기서는 그 시점의 현재 언어를 그대로 쓰면 된다.
                'siteName' => $settings->getLocalized('site_name', default: config('app.name')),
            ],
        );
    }
}
