<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\SmtpService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class SMTPTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Send an email to one or multiple recipients using the system SMTP configuration.';
    }

    /**
     * Send an email.
     */
    #[Sentinelable(
        name: 'SMTP Tool Send Email',
        description: 'Send an email to one or multiple recipients using the system SMTP configuration.',
        category: 'Communication'
    )]
    protected function send(Request $request): Stringable|string
    {
        if ($error = $this->checkForForbiddenSecurityTerms($request)) {
            return $error;
        }

        $to = $request->all()['to'] ?? null;
        $subject = (string) $request->string('subject');
        $body = (string) $request->string('body');
        $isHtml = true; // Force HTML to ensure system layout and branding are used.

        Log::debug('SMTPTool handle', [
            'to' => $to,
            'subject' => $subject,
            'is_html' => $isHtml,
        ]);

        return app(SmtpService::class)->send($to, $subject, $body, $isHtml);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['send'])
                ->required()
                ->description('The action to perform: send'),

            'to' => $schema->string()
                ->required()
                ->description('The recipient email address or an array of recipient email addresses.'),
            'subject' => $schema->string()
                ->required()
                ->description('The email subject.'),
            'body' => $schema->string()
                ->required()
                ->description('The email body content. You MUST use Markdown or HTML for better formatting and readability. Your response will be automatically converted to clean, professional HTML. Include clear headings, bullet points, and paragraphs to ensure the email is easy to read. Do not include the email subject line in the body as it is already handled by the subject field. Avoid sending large blocks of unformatted text.'),
        ];
    }
}
