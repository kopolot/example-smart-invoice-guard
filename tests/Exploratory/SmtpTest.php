<?php

namespace Tests\Exploratory;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmtpTest extends TestCase
{
    public function test_smtp_connection(): void
    {
        config(['mail.default' => 'smtp']);
        try {
            $message = Mail::raw('SMTP test', function ($message) {
                $message->to('test@example.com')
                    ->subject('SMTP Test');
            });

            $this->assertNotNull($message);
        } catch (\Throwable $e) {
            $this->fail('SMTP error: '.$e->getMessage());
        }
    }
}
