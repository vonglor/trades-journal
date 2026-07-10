<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $fullname;
    public $url;

    // ຮັບຄ່າຊື່ ແລະ Link ຢືນຢັນຕົວຕົນມາຈາກ Controller
    public function __construct($fullname, $url)
    {
        $this->fullname = $fullname;
        $this->url = $url;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ກະລຸນາຢືນຢັນອີເມວຂອງທ່ານ (Trading Journal)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email', // 📢 ໄຟລ໌ໜ້າຕາອີເມວ (HTML)
        );
    }
}