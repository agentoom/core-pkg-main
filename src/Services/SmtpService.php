<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Models\Configuration;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[GroupVisibility('agentoom-core')]
class SmtpService
{
    /**
     * Send an email using the system SMTP configuration.
     */
    #[Actionable(
        name: 'Send Email via SMTP',
        description: 'Send an email to one or multiple recipients using the system SMTP configuration.',
        category: 'Email'
    )]
    public function send(
        string|array $to,
        string $subject,
        string $body,
        bool $isHtml = true
    ): string {
        Log::debug('SmtpService: Sending email', [
            'to' => $to,
            'subject' => $subject,
            'is_html' => $isHtml,
        ]);

        $config = $this->getDynamicConfig();

        if (empty($config['host'])) {
            Log::error('SmtpService: SMTP host is missing in configuration. Email sending failed.');

            return 'Error: SMTP configuration is missing. Please configure it in the global configuration panel.';
        }

        // Dynamically configure mailer
        Config::set('mail.mailers.smtp_dynamic', $config);

        $fromAddress = Configuration::getValue('mail_from_address') ?: config('mail.from.address');
        $fromName = Configuration::getValue('mail_from_name') ?: config('mail.from.name');

        try {
            Mail::mailer('smtp_dynamic')->to($to)->send(new class($subject, $body, $isHtml, $fromAddress, $fromName) extends Mailable
            {
                public function __construct(public $subject, public $body, public $isHtml, public $fromAddress, public $fromName) {}

                public function build()
                {
                    $this->from($this->fromAddress, $this->fromName)
                        ->subject($this->subject);

                    return $this->view('agentoom-core::layouts.email', [
                        'body' => $this->isHtml
                            ? Helpers::parseMarkdown($this->body)
                            : nl2br(e($this->body)),
                    ]);
                }
            });

            Log::debug('SmtpService: Email sent successfully', ['to' => $to]);

            $recipients = is_array($to) ? implode(', ', $to) : $to;

            return "Email sent successfully to {$recipients}.";
        } catch (\Throwable $e) {
            Log::error('SmtpService error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return 'Error sending email: '.$e->getMessage();
        }
    }

    /**
     * Send a mailable using the dynamic SMTP configuration.
     */
    public function sendMailable(string|array $to, Mailable $mailable): void
    {
        $config = $this->getDynamicConfig();

        try {
            if (empty($config['host'])) {
                // Fallback to default mailer
                Mail::to($to)->send($mailable);

                return;
            }

            Config::set('mail.mailers.smtp_dynamic', $config);

            $fromAddress = Configuration::getValue('mail_from_address') ?: config('mail.from.address');
            $fromName = Configuration::getValue('mail_from_name') ?: config('mail.from.name');

            if ($fromAddress) {
                $mailable->from($fromAddress, $fromName);
            }

            Mail::mailer('smtp_dynamic')->to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('SmtpService: Failed to send mailable: '.$e->getMessage(), [
                'exception' => $e,
                'to' => $to,
            ]);

            throw $e;
        }
    }

    /**
     * Get the dynamic SMTP configuration.
     */
    protected function getDynamicConfig(): array
    {
        $config = Configuration::getValues([
            'mail_host', 'mail_port', 'mail_username', 'mail_password',
            'mail_encryption',
        ]);

        return [
            'transport' => 'smtp',
            'host' => $config['mail_host'] ?? null,
            'port' => $config['mail_port'] ?? null,
            'encryption' => (($config['mail_encryption'] ?? '') === 'null' || empty($config['mail_encryption'] ?? '')) ? null : $config['mail_encryption'],
            'username' => $config['mail_username'] ?? null,
            'password' => $config['mail_password'] ?? null,
            'timeout' => null,
            'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
        ];
    }
}
