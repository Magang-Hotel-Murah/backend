<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

class VerifyUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verifyUrl;

    public function __construct(User $user)
    {
        $this->user = $user;

        $temporarySignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $this->verifyUrl = $frontendUrl . '/verify-email/' . $user->getKey() . '/' . sha1($user->getEmailForVerification()) . '?' . parse_url($temporarySignedUrl, PHP_URL_QUERY);
    }

    public function build()
    {
        return $this->subject('Verifikasi Email Anda')
            ->view('emails.verify_user')
            ->with([
                'name' => $this->user->name,
                'verifyUrl' => $this->verifyUrl,
            ]);
    }
}
