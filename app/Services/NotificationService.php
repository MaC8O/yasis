<?php

namespace App\Services;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Thin wrapper over Laravel's mailer, per §4.3 (Service layer) and §3.6
 * ("email is implemented in the core release, with SMS/Viber/Telegram
 * delivery channels planned as configurable add-ons").
 */
class NotificationService
{
    public function sendEmail(string $to, Mailable $mailable): void
    {
        Mail::to($to)->send($mailable);
    }

    public function sendSms(string $to, string $message): void
    {
        Log::info('SMS channel not yet implemented (future enhancement).', compact('to', 'message'));
    }

    public function sendViber(string $to, string $message): void
    {
        Log::info('Viber channel not yet implemented (future enhancement).', compact('to', 'message'));
    }

    public function sendTelegram(string $to, string $message): void
    {
        Log::info('Telegram channel not yet implemented (future enhancement).', compact('to', 'message'));
    }
}
