<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One behavior notice. Always stored in-app (database channel); e-mailed too when $email is set.
 * Staging sends mail to the log only, so the in-app copy is what families actually see there.
 * `student_id` is kept in the payload so a retention erasure can find and scrub it.
 */
class BehaviorNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $studentId,
        public string $title,
        public string $body,
        public string $url,
        public bool $email = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->email && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'student_id' => $this->studentId,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' — Yangon Adventist Seminary')
            ->greeting('Dear '.$notifiable->name.',')
            ->line($this->body)
            ->action('Open in the school portal', $this->url)
            ->line('This is an automated message from the Integrated School Management System.');
    }
}
