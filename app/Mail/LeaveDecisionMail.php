<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }

    public function build()
    {
        return $this->subject('Leave request '.strtolower($this->leaveRequest->status).' — '.$this->leaveRequest->leaveType->name)
            ->view('emails.leave-decision');
    }
}
