<?php

/**
 * SMTP email sender with optional TLS and authentication support.
 */

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Sends queued plain-text platform email through SMTP.
 *
 * Platform settings own the production mail configuration. Environment
 * variables remain a development/bootstrap fallback only.
 */
final class SmtpEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $fromEmail,
        private readonly string $fromName = 'ArtsFolio',
        private readonly int $timeoutSeconds = 10,
        private array $headers = [],
        ?array $extraHeaders = null,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $encryption = 'none',
    ) {
        if ($extraHeaders !== null) {
            $this->headers = array_merge($this->headers, $extraHeaders);
        }

        foreach ($this->headers as $name => $value) {
            $this->assertSafeHeader((string) $name, (string) $value);
        }

        if (($this->username === '') !== ($this->password === '')) {
            throw new \InvalidArgumentException('SMTP username and password must both be set or both be blank.');
        }

        if (!in_array($this->normalizedEncryption(), ['none', 'tls', 'ssl'], true)) {
            throw new \InvalidArgumentException('SMTP encryption must be none, tls, or ssl.');
        }
    }

    public function send(array $email): string
    {
        $socket = $this->connect();

        try {
            $this->expect($socket, [220]);
            $this->ehlo($socket);

            if ($this->normalizedEncryption() === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP STARTTLS negotiation failed.');
                }

                // RFC 3207 requires EHLO again after STARTTLS resets SMTP state.
                $this->ehlo($socket);
            }

            if ($this->username !== '') {
                $this->authenticate($socket);
            }

            $this->command($socket, "MAIL FROM:<{$this->fromEmail}>", [250]);
            $this->command($socket, "RCPT TO:<{$email['recipient_email']}>", [250, 251]);
            $this->command($socket, 'DATA', [354]);

            fwrite($socket, $this->buildMessage($email) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return json_encode(['smtp' => true, 'to' => $email['recipient_email']], JSON_THROW_ON_ERROR);
    }

    public function buildMessageForTest(array $email): string
    {
        return $this->buildMessage($email);
    }

    private function connect()
    {
        $target = $this->normalizedEncryption() === 'ssl'
            ? 'ssl://' . $this->host
            : $this->host;

        $socket = fsockopen($target, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed: {$errno} {$errstr}");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        return $socket;
    }

    private function ehlo($socket): void
    {
        $hostname = gethostname() ?: 'artsfol.io';
        $this->command($socket, 'EHLO ' . $hostname, [250]);
    }

    private function authenticate($socket): void
    {
        $auth = base64_encode("\0{$this->username}\0{$this->password}");
        $this->command($socket, 'AUTH PLAIN ' . $auth, [235]);
    }

    private function buildMessage(array $email): string
    {
        $hasHtml = isset($email['body_html']) && trim((string) $email['body_html']) !== '';
        $boundary = 'artsfolio-' . bin2hex(random_bytes(16));

        $headers = [
            'From' => $this->encodeHeader($this->fromName) . " <{$this->fromEmail}>",
            'To' => '<' . (string) $email['recipient_email'] . '>',
            'Subject' => $this->encodeHeader((string) $email['subject']),
            'MIME-Version' => '1.0',
        ];

        if ($hasHtml) {
            $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = '8bit';
        }

        foreach ($this->headers as $name => $value) {
            $headerName = (string) $name;
            $headerValue = (string) $value;

            $this->assertSafeHeader($headerName, $headerValue);

            $headers[$headerName] = $headerValue;
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $this->assertSafeHeader((string) $name, (string) $value);
            $lines[] = "{$name}: {$value}";
        }

        $bodyText = $this->normalizeMessageBody((string) ($email['body_text'] ?? ''));

        if (!$hasHtml) {
            return implode("\r\n", $lines) . "\r\n\r\n" . $bodyText;
        }

        $bodyHtml = $this->normalizeMessageBody((string) $email['body_html']);

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyText . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyHtml . "\r\n"
            . '--' . $boundary . '--';

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }

    private function normalizeMessageBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        return str_replace("\n", "\r\n", $body);
    }

    private function assertSafeHeader(string $name, string $value): void
    {
        if ($name === '' || preg_match('/[^A-Za-z0-9-]/', $name) === 1) {
            throw new \InvalidArgumentException("Unsafe SMTP header name: {$name}");
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException("Unsafe SMTP header value for {$name}.");
        }
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        $code = 0;

        do {
            $line = fgets($socket);

            if ($line === false) {
                throw new \RuntimeException('SMTP server did not respond.');
            }

            $response .= $line;
            $code = (int) substr($line, 0, 3);
            $continuation = strlen($line) >= 4 && $line[3] === '-';
        } while ($continuation);

        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }

    private function normalizedEncryption(): string
    {
        $value = strtolower(trim($this->encryption));

        return $value === '' ? 'none' : $value;
    }
}

// End of file.
