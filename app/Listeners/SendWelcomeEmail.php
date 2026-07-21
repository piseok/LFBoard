<?php

namespace App\Listeners;

use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;

class SendWelcomeEmail
{
    public function __construct(private readonly EmailService $emailService) {}

    public function handle(Registered $event): void
    {
        $this->emailService->send('welcome', $event->user->email, [
            'user_name' => $event->user->name,
            'user_email' => $event->user->email,
        ], $event->user->locale);
    }
}
