<?php

namespace Agentoom\Core\Services\Api;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Helpers\Helpers;
use Google\Service\Gmail as GmailServiceResource;
use Google\Service\Gmail\Message as GmailMessage;
use Google\Service\Gmail\ModifyMessageRequest;

#[GroupVisibility('agentoom-core')]
class GmailService extends BaseApiService
{
    /**
     * Get the fields required for Gmail API.
     */
    public function requiredMappingFields(): array
    {
        return [
            'client_id' => 'OAuth2 Client ID',
            'client_secret' => 'OAuth2 Client Secret',
            'refresh_token' => 'OAuth2 Refresh Token',
        ];
    }

    /**
     * List messages from Gmail.
     */
    #[Actionable(
        name: 'List Gmail Messages',
        description: 'List messages from the authorized Gmail account. You can filter by query (e.g., "after:2023/01/01") and limit the results.',
        category: 'Email'
    )]
    public function listMessages(
        ?string $query = null,
        int $limit = 10,
        ?string $resourceId = null,
        array $resourceMapping = []
    ): string {
        $mapped = $this->resolveResourceData($resourceId, $resourceMapping, 'gmail');

        try {
            $this->refreshAccessToken('gmail', $mapped, $resourceId);
            $client = $this->getGoogleClient($mapped, static::getScopes('gmail'));
            $service = new GmailServiceResource($client);

            $results = $service->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $limit,
            ]);

            $messages = $results->getMessages();
            $result = [];

            if ($messages) {
                foreach ($messages as $msg) {
                    $details = $service->users_messages->get('me', $msg->getId(), [
                        'format' => 'metadata',
                        'metadataHeaders' => ['Subject', 'From', 'Date'],
                    ]);

                    $payload = $details->getPayload();
                    $headers = $payload->getHeaders();

                    $subject = '(No Subject)';
                    $from = '(Unknown Sender)';
                    $date = '(Unknown Date)';

                    foreach ($headers as $header) {
                        if ($header->getName() === 'Subject') {
                            $subject = $header->getValue();
                        }
                        if ($header->getName() === 'From') {
                            $from = $header->getValue();
                        }
                        if ($header->getName() === 'Date') {
                            $date = $header->getValue();
                        }
                    }

                    $result[] = [
                        'id' => $msg->getId(),
                        'subject' => $subject,
                        'from' => $from,
                        'date' => $date,
                    ];
                }
            }

            return json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get a specific message content.
     */
    #[Actionable(
        name: 'Get Gmail Message Content',
        description: 'Get the full content of a specific Gmail message by ID.',
        category: 'Email'
    )]
    public function getMessage(
        string $messageId,
        ?string $resourceId = null,
        array $resourceMapping = []
    ): string {
        $mapped = $this->resolveResourceData($resourceId, $resourceMapping, 'gmail');

        try {
            $this->refreshAccessToken('gmail', $mapped, $resourceId);
            $client = $this->getGoogleClient($mapped, static::getScopes('gmail'));
            $service = new GmailServiceResource($client);

            $data = $service->users_messages->get('me', $messageId);

            $payload = $data->getPayload();
            $parts = $payload->getParts();
            $body = '';

            if ($payload->getBody() && $payload->getBody()->getData()) {
                $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload->getBody()->getData()));
            } else {
                foreach ($parts as $part) {
                    if ($part->getMimeType() === 'text/plain' && $part->getBody() && $part->getBody()->getData()) {
                        $body .= base64_decode(str_replace(['-', '_'], ['+', '/'], $part->getBody()->getData()));
                    }
                }
            }

            $headers = $payload->getHeaders();
            $subject = '(No Subject)';
            $from = '(Unknown Sender)';

            foreach ($headers as $header) {
                if ($header->getName() === 'Subject') {
                    $subject = $header->getValue();
                }
                if ($header->getName() === 'From') {
                    $from = $header->getValue();
                }
            }

            return json_encode([
                'id' => $data->getId(),
                'subject' => $subject,
                'from' => $from,
                'snippet' => $data->getSnippet() ?? '',
                'body' => $body,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Send an email.
     */
    #[Actionable(
        name: 'Send Gmail Email',
        description: 'Send an email through the authorized Gmail account.',
        category: 'Email'
    )]
    public function sendMessage(
        string $to,
        string $subject,
        string $body,
        ?string $resourceId = null,
        array $resourceMapping = []
    ): string {
        $mapped = $this->resolveResourceData($resourceId, $resourceMapping, 'gmail');

        try {
            $this->refreshAccessToken('gmail', $mapped, $resourceId);
            $client = $this->getGoogleClient($mapped, static::getScopes('gmail'));
            $service = new GmailServiceResource($client);

            $htmlBody = Helpers::parseMarkdown($body);
            $fullHtml = view('agentoom-core::layouts.email', ['body' => $htmlBody])->render();

            $rawMessage = "To: {$to}\r\n";
            $rawMessage .= "Subject: {$subject}\r\n";
            $rawMessage .= "MIME-Version: 1.0\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
            $rawMessage .= $fullHtml;

            $safeRawMessage = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

            $message = new GmailMessage;
            $message->setRaw($safeRawMessage);

            $response = $service->users_messages->send('me', $message);

            return 'Email sent successfully. Message ID: '.$response->getId();
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Move a message to trash.
     */
    #[Actionable(
        name: 'Trash Gmail Message',
        description: 'Move a specific message to the trash.',
        category: 'Email'
    )]
    public function trashMessage(
        string $messageId,
        ?string $resourceId = null,
        array $resourceMapping = []
    ): string {
        $mapped = $this->resolveResourceData($resourceId, $resourceMapping, 'gmail');

        try {
            $this->refreshAccessToken('gmail', $mapped, $resourceId);
            $client = $this->getGoogleClient($mapped, static::getScopes('gmail'));
            $service = new GmailServiceResource($client);

            $service->users_messages->trash('me', $messageId);

            return 'Message moved to trash successfully.';
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Mark a message as spam.
     */
    #[Actionable(
        name: 'Mark Gmail Message as Spam',
        description: 'Move a specific message to the spam folder.',
        category: 'Email'
    )]
    public function markAsSpam(
        string $messageId,
        ?string $resourceId = null,
        array $resourceMapping = []
    ): string {
        $mapped = $this->resolveResourceData($resourceId, $resourceMapping, 'gmail');

        try {
            $this->refreshAccessToken('gmail', $mapped, $resourceId);
            $client = $this->getGoogleClient($mapped, static::getScopes('gmail'));
            $service = new GmailServiceResource($client);

            $mods = new ModifyMessageRequest;
            $mods->setAddLabelIds(['SPAM']);
            $mods->setRemoveLabelIds(['INBOX']);

            $service->users_messages->modify('me', $messageId, $mods);

            return 'Message marked as spam successfully.';
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }
}
