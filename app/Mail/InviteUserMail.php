<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invitation;

class InviteUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build()
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        return $this->subject('Undangan Bergabung ke Sistem Meeting Room')
            ->view('emails.invite_user')
            ->with([
                'inviteUrl' => $frontendUrl . '/activate-account?token=' . $this->invitation->token,
                'role' => ucfirst($this->invitation->role),
                'email' => $this->invitation->email,
            ]);
    }
}
