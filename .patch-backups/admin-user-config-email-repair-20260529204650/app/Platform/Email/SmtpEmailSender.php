<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Basic SMTP sender for local Mailhog-style development.
 *
 * This intentionally supports simple, unauthenticated SMTP first.
 * Production transactional email should use a hardened provider adapter.
 */
final class SmtpEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $fromEmail,
        private readonly string $fromName = 'ArtsFolio',
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function send(array $email): string
    {
        $socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed: {$errno} {$errstr}");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, "HELO artsfol.io", [250]);
            $this->command($socket, "MAIL FROM:<{$this->fromEmail}>", [250]);
            $this->command($socket, "RCPT TO:<{$email['recipient_email']}>", [250, 251]);
            $this->command($socket, "DATA", [354]);

            $message = $this->buildMessage($email);
            fwrite($socket, $message . "\r\n.\r\n");
            $this->expect($socket, [250]);

            $this->command($socket, "QUIT", [221]);
        } finally {
            fclose($socket);
        }

        return json_encode([
            'smtp' => true,
            'host' => $this->host,
            'port' => $this->port,
            'id' => (int) $email['id'],
            'to' => $email['recipient_email'],
            'subject' => $email['subject'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function buildMessage(array $email): string
    {
        $fromName = $this->encodeHeader($this->fromName);
        $subject = $this->encodeHeader((string) $email['subject']);
        $to = (string) $email['recipient_email'];
        $body = str_replace(["\r\n", "\r"], "\n", (string) $email['body_text']);
        $body = str_replace("\n", "\r\n", $body);

        return implode("\r\n", [
            "From: {$fromName} <{$this->fromEmail}>",
            "To: <{$to}>",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "",
            $body,
        ]);
    }

    private function encodeHeader(string $value): string
    {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    /**
     * @param resource $socket
     */
    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     */
    private function expect($socket, array $expectedCodes): string
    {
        $line = fgets($socket);

        if ($line === false) {
            throw new \RuntimeException('SMTP server did not respond.');
        }

        $code = (int) substr($line, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException("Unexpected SMTP response: {$line}");
        }

        return $line;
    }
}

// End of file.
