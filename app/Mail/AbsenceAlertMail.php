<?php

namespace App\Mail;

use App\Models\AttendanceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AbsenceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AttendanceRecord $record)
    {
    }

    public function build()
    {
        return $this->subject('Attendance alert — '.$this->record->student->first_name.' '.$this->record->student->last_name)
            ->view('emails.absence-alert');
    }
}
