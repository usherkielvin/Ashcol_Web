<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build(): self
    {
        $subject = 'New Inquiry Request';

        // Map fields explicitly; avoid reserved $message variable in views
        $viewData = [
            'name' => $this->payload['name'] ?? '',
            'email' => $this->payload['email'] ?? '',
            'phone' => $this->payload['phone'] ?? '',
            'service' => $this->payload['service'] ?? '',
            'body' => $this->payload['message'] ?? '',
        ];

        $fromAddress = config('mail.from.address');
        $fromNameBase = config('mail.from.name');
        $displayName = trim(($viewData['name'] ? ($viewData['name'].' via ') : '').($fromNameBase ?: 'Ashcol Service Desk'));

        $mail = $this->from($fromAddress, $displayName)
            ->subject($subject)
            ->view('emails.contact_submitted')
            ->with($viewData);

        if (!empty($this->payload['email'])) {
            $mail->replyTo($this->payload['email'], $this->payload['name'] ?? null);
        }

        return $mail;
    }
}


