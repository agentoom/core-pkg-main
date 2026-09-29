<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\SMTPTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Tools\Request;

class SMTPToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_tool_sends_email_with_config()
    {
        Mail::fake();

        Configuration::setValues([
            'mail_host' => 'smtp.test.com',
            'mail_port' => '587',
            'mail_username' => 'testuser',
            'mail_password' => 'testpass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'from@test.com',
            'mail_from_name' => 'Test Sender',
        ]);

        $tool = new SMTPTool;
        $request = new Request([
            'to' => 'to@test.com',
            'subject' => 'Test Subject',
            'body' => '<h1>Test Body</h1>',
            'is_html' => true,
        ]);

        $result = $tool->handle($request);

        $this->assertEquals('Email sent successfully to to@test.com.', $result);

        Mail::assertSent(Mailable::class, function ($mail) {
            $mail->build();

            return $mail->hasTo('to@test.com') && $mail->hasFrom('from@test.com');
        });

        $this->assertEquals('smtp.test.com', Config::get('mail.mailers.smtp_dynamic.host'));
    }

    public function test_smtp_tool_sends_email_to_multiple_recipients()
    {
        Mail::fake();

        Configuration::setValues([
            'mail_host' => 'smtp.test.com',
            'mail_port' => '587',
            'mail_username' => 'testuser',
            'mail_password' => 'testpass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'from@test.com',
            'mail_from_name' => 'Test Sender',
        ]);

        $tool = new SMTPTool;
        $request = new Request([
            'to' => ['to1@test.com', 'to2@test.com'],
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            'is_html' => false,
        ]);

        $result = $tool->handle($request);

        $this->assertEquals('Email sent successfully to to1@test.com, to2@test.com.', $result);

        Mail::assertSent(Mailable::class, function ($mail) {
            $mail->build();

            return $mail->hasTo('to1@test.com') && $mail->hasTo('to2@test.com');
        });
    }

    public function test_smtp_tool_fails_if_config_missing()
    {
        Configuration::where('key', 'mail_host')->delete();

        $tool = new SMTPTool;
        $request = new Request([
            'to' => 'to@test.com',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Error: SMTP configuration is missing', (string) $result);
    }
}
