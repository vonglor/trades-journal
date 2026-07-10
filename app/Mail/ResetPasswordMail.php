<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable 
{
    use Queueable, SerializesModels;

    public $user;
    public $token;

    public function __construct($user, $token)
    {
        // ຮັບຄ່າ User ແລະ Token ທີ່ສ້າງຂຶ້ນມາ
        $this->user = $user;
        $this->token = $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔒 ຕັ້ງຄ່າລະຫັດຜ່ານໃໝ່ຂອງທ່ານ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password', // ໄຟລ໌ໜ້າຕາອີເມວ
        );
    }
}