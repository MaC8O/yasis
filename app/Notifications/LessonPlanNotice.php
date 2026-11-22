<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A lesson-plan notice for staff. Always stored in-app; e-mailed too when $email is set. */
class LessonPlanNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $lessonPlanId,
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
            'lesson_plan_id' => $this->lessonPlanId,
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
            ->action('Open the lesson plan', $this->url)
            ->line('This is an automated message from the Integrated School Management System.');
    }
}
