<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Configuration;
use Illuminate\Support\Facades\Log;

class ImapService
{
    /**
     * Get the dynamic IMAP configuration from system settings.
     */
    public function getSystemConfig(): array
    {
        $config = Configuration::getValues([
            'mail_imap_host', 'mail_imap_port', 'mail_imap_username',
            'mail_imap_password', 'mail_imap_encryption',
        ]);

        return [
            'host' => $config['mail_imap_host'] ?? '',
            'port' => $config['mail_imap_port'] ?? '993',
            'username' => $config['mail_imap_username'] ?? '',
            'password' => $config['mail_imap_password'] ?? '',
            'encryption' => $config['mail_imap_encryption'] ?? 'ssl',
        ];
    }

    /**
     * Open an IMAP stream.
     */
    protected function openStream(array $config): mixed
    {
        $host = $config['host'];
        $port = $config['port'];
        $username = $config['username'];
        $password = $config['password'];
        $encryption = $config['encryption'];

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        // We might need /novalidate-cert if certificates are self-signed
        $flags .= '/novalidate-cert';

        $mailbox = '{'.$host.':'.$port.$flags.'}';

        Log::debug('ImapService: Opening stream', ['mailbox' => $mailbox, 'username' => $username]);

        $stream = @imap_open($mailbox, $username, $password);

        if (! $stream) {
            $error = imap_last_error();
            Log::error('ImapService: Failed to open stream', ['error' => $error]);
            throw new \Exception('Failed to connect to IMAP server: '.$error);
        }

        return $stream;
    }

    /**
     * List mailboxes.
     */
    public function listMailboxes(array $config): array
    {
        $stream = $this->openStream($config);
        $list = imap_getmailboxes($stream, '{'.$config['host'].'}', '*');

        $result = [];
        if ($list) {
            foreach ($list as $mailbox) {
                $result[] = str_replace('{'.$config['host'].'}', '', $mailbox->name);
            }
        }

        imap_close($stream);

        return $result;
    }

    /**
     * List messages in a mailbox.
     */
    public function listMessages(array $config, string $mailbox = 'INBOX', int $limit = 10): array
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$mailbox;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);

        if (! $stream) {
            throw new \Exception("Failed to connect to mailbox {$mailbox}: ".imap_last_error());
        }

        $numMessages = imap_num_msg($stream);
        $result = [];

        $start = max(1, $numMessages - $limit + 1);
        $end = $numMessages;

        for ($i = $end; $i >= $start; $i--) {
            $header = imap_headerinfo($stream, $i);
            $result[] = [
                'uid' => imap_uid($stream, $i),
                'msg_no' => $i,
                'subject' => $header->subject ?? '(No Subject)',
                'from' => $header->fromaddress ?? '(Unknown Sender)',
                'date' => $header->date ?? '(Unknown Date)',
                'unseen' => $header->Unseen === 'U' || $header->Recent === 'N' ? 'U' : '',
            ];
        }

        imap_close($stream);

        return $result;
    }

    /**
     * Get message content.
     */
    public function getMessage(array $config, string $mailbox, int $uid): array
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$mailbox;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);

        if (! $stream) {
            throw new \Exception("Failed to connect to mailbox {$mailbox}: ".imap_last_error());
        }

        $msg_no = imap_msgno($stream, $uid);
        if ($msg_no == 0) {
            imap_close($stream);
            throw new \Exception("Message with UID {$uid} not found.");
        }

        $header = imap_headerinfo($stream, $msg_no);
        $structure = imap_fetchstructure($stream, $msg_no);

        $body = $this->getBody($stream, $msg_no);

        $result = [
            'uid' => $uid,
            'subject' => $header->subject ?? '(No Subject)',
            'from' => $header->fromaddress ?? '(Unknown Sender)',
            'to' => $header->toaddress ?? '(Unknown Recipient)',
            'date' => $header->date ?? '(Unknown Date)',
            'body' => $body,
        ];

        imap_close($stream);

        return $result;
    }

    /**
     * Create a draft.
     */
    public function createDraft(array $config, string $to, string $subject, string $body): bool
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        // Try to find Drafts folder
        $stream = $this->openStream($config);
        $list = imap_getmailboxes($stream, '{'.$config['host'].'}', '*');
        $draftsFolder = 'Drafts';
        if ($list) {
            foreach ($list as $mailbox) {
                $name = str_replace('{'.$config['host'].'}', '', $mailbox->name);
                if (stripos($name, 'Draft') !== false) {
                    $draftsFolder = $name;
                    break;
                }
            }
        }
        imap_close($stream);

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$draftsFolder;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);
        if (! $stream) {
            // If failed, try to create it or just use the default path
            $stream = $this->openStream($config);
        }

        $boundary = 'bound'.time();
        $htmlBody = Helpers::parseMarkdown($body);
        $fullHtml = view('agentoom-core::layouts.email', ['body' => $htmlBody])->render();

        $message = "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $fullHtml;

        $result = imap_append($stream, $mailboxPath, $message, '\\Draft');

        imap_close($stream);

        return $result;
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(array $config, string $mailbox, int $uid): bool
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$mailbox;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);

        if (! $stream) {
            throw new \Exception("Failed to connect to mailbox {$mailbox}: ".imap_last_error());
        }

        $result = imap_delete($stream, $uid, FT_UID);
        imap_expunge($stream);
        imap_close($stream);

        return $result;
    }

    /**
     * Move a message.
     */
    public function moveMessage(array $config, string $mailbox, int $uid, string $targetMailbox): bool
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$mailbox;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);

        if (! $stream) {
            throw new \Exception("Failed to connect to mailbox {$mailbox}: ".imap_last_error());
        }

        $result = imap_mail_move($stream, (string) $uid, $targetMailbox, CP_UID);
        imap_expunge($stream);
        imap_close($stream);

        return $result;
    }

    /**
     * Mark message as read/unread.
     */
    public function markMessage(array $config, string $mailbox, int $uid, bool $seen): bool
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }
        $flags .= '/novalidate-cert';

        $mailboxPath = '{'.$config['host'].':'.$config['port'].$flags.'}'.$mailbox;

        $stream = @imap_open($mailboxPath, $config['username'], $config['password']);

        if (! $stream) {
            throw new \Exception("Failed to connect to mailbox {$mailbox}: ".imap_last_error());
        }

        if ($seen) {
            $result = imap_setflag_full($stream, (string) $uid, '\\Seen', ST_UID);
        } else {
            $result = imap_clearflag_full($stream, (string) $uid, '\\Seen', ST_UID);
        }

        imap_close($stream);

        return $result;
    }

    /**
     * Helper to get message body.
     */
    protected function getBody($stream, $msg_no): string
    {
        $structure = imap_fetchstructure($stream, $msg_no);
        if (! isset($structure->parts)) {
            return $this->getPart($stream, $msg_no, $structure, 0);
        } else {
            foreach ($structure->parts as $part_no => $part) {
                $body = $this->getPart($stream, $msg_no, $part, $part_no + 1);
                if ($body) {
                    return $body;
                }
            }
        }

        return '';
    }

    protected function getPart($stream, $msg_no, $part, $part_no): string
    {
        $data = ($part_no == 0) ? imap_body($stream, $msg_no) : imap_fetchbody($stream, $msg_no, $part_no);

        // Decode
        if ($part->encoding == 3) {
            $data = base64_decode($data);
        } elseif ($part->encoding == 4) {
            $data = quoted_printable_decode($data);
        }

        // Parameters
        $params = [];
        if ($part->ifparameters) {
            foreach ($part->parameters as $p) {
                $params[strtolower($p->attribute)] = $p->value;
            }
        }
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $p) {
                $params[strtolower($p->attribute)] = $p->value;
            }
        }

        // Text part
        if ($part->type == 0 && $data) {
            return $data;
        }

        // Recursive for multipart
        if ($part->type == 1 && isset($part->parts)) {
            foreach ($part->parts as $sub_part_no => $sub_part) {
                $body = $this->getPart($stream, $msg_no, $sub_part, $part_no.'.'.($sub_part_no + 1));
                if ($body) {
                    return $body;
                }
            }
        }

        return '';
    }
}
