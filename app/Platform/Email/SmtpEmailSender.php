<?php

declare(strict_types=1);

namespace App\Platform\Email;

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
    ) {
        if ($extraHeaders !== null) {
            $this->headers = array_merge($this->headers, $extraHeaders);
        }

        foreach ($this->headers as $name => $value) {
            $this->assertSafeHeader((string) $name, (string) $value);
        }
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
            $this->command($socket, 'HELO artsfol.io', [250]);
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

    private function buildMessage(array $email): string
    {
        $headers = [
            'From' => $this->encodeHeader($this->fromName) . " <{$this->fromEmail}>",
            'To' => '<' . (string) $email['recipient_email'] . '>',
            'Subject' => $this->encodeHeader((string) $email['subject']),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];

        foreach ($this->headers as $name => $value) {
            $headerName = (string) $name;
            $headerValue = (string) $value;

            $this->assertSafeHeader($headerName, $headerValue);

            $headers[$headerName] = $headerValue;
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $body = str_replace(["\r\n", "\r"], "\n", (string) $email['body_text']);
        $body = str_replace("\n", "\r\n", $body);

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
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
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->expect($socket, $expectedCodes);
    }

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
