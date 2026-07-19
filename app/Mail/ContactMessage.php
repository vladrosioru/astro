<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build(): self
    {
        return $this->subject($this->data['subject'] ?: 'New contact message — '.config('app.name'))
            ->replyTo($this->data['email'], $this->data['name'])
            ->view('emails.contact');
    }
}
