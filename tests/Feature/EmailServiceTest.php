<?php

namespace Tests\Feature;

use App\Mail\GenericMail;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    public function test_it_can_send_a_generic_email(): void
    {
        Mail::fake();

        $service = new EmailService();
        $sent = $service->send('recipient@example.com', 'Welcome to TaziJobs', 'Welcome message body');

        $this->assertTrue($sent);

        Mail::assertSent(GenericMail::class, function (GenericMail $mail) {
            return $mail->hasTo('recipient@example.com')
                && $mail->subject === 'Welcome to TaziJobs';
        });
    }
}
