<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A one-time notice for a teacher whose gradebook was moved onto the fixed template: which items
 * moved, and to which category. Shown once when they next open the gradebook (then marked read),
 * and kept under My notifications.
 */
class GradebookNotice extends Notification
{
    use Queueable;

    /** @param  list<string>  $lines */
    public function __construct(
        public int $sectionId,
        public int $subjectId,
        public int $termId,
        public string $title,
        public array $lines,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'gradebook_template',
            'section_id' => $this->sectionId,
            'subject_id' => $this->subjectId,
            'term_id' => $this->termId,
            'title' => $this->title,
            'body' => implode(' · ', $this->lines),
            'lines' => $this->lines,
            'url' => $this->url,
        ];
    }
}
