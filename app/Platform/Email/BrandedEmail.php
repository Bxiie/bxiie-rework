<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Builds plain-text and HTML ArtsFolio email bodies.
 *
 * All HTML bodies rendered through this helper include the ArtsFolio logo.
 * The public text(), html(), htmlFromText(), and render() methods are retained
 * because older mailer and outbox code may call any of them.
 */
final class BrandedEmail
{
    /**
     * Returns plain-text email content with a stable ArtsFolio footer.
     */
    public static function text(string $bodyText): string
    {
        $bodyText = trim($bodyText);

        if ($bodyText === '') {
            return "ArtsFolio\n";
        }

        if (str_contains($bodyText, 'ArtsFolio')) {
            return $bodyText;
        }

        return $bodyText . "\n\nArtsFolio";
    }

    /**
     * Backward-compatible HTML renderer.
     */
    public static function html(string $bodyText, string $subject = 'ArtsFolio'): string
    {
        return self::htmlFromText($subject, $bodyText);
    }

    /**
     * Wraps plain text in a branded HTML email shell.
     */
    public static function htmlFromText(string $subject, string $bodyText): string
    {
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $safeSubject . '</title>'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#f6f3ee;color:#1f1f1f;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:680px;margin:0 auto;padding:32px 20px;">'
            . '<div style="background:#ffffff;border:1px solid #e6dfd5;border-radius:18px;padding:28px;box-shadow:0 12px 40px rgba(0,0,0,0.06);">'
            . self::logoHtml()
            . '<h1 style="font-size:24px;line-height:1.25;margin:0 0 18px 0;color:#141414;">' . $safeSubject . '</h1>'
            . '<div style="font-size:16px;line-height:1.6;color:#2c2c2c;">' . self::paragraphsFromText($bodyText) . '</div>'
            . '</div>'
            . '<p style="font-size:12px;line-height:1.5;color:#746f67;margin:18px 4px 0 4px;">ArtsFolio</p>'
            . '</div>'
            . '</body>'
            . '</html>';
    }

    /**
     * Returns a payload compatible with email_outbox storage.
     *
     * @return array{body_text:string,body_html:string}
     */
    public static function render(string $subject, string $bodyText): array
    {
        return [
            'body_text' => self::text($bodyText),
            'body_html' => self::htmlFromText($subject, $bodyText),
        ];
    }

    /**
     * Returns the public absolute logo URL used by email clients.
     */
    private static function artsfolioLogoUrl(): string
    {
        $baseUrl = (string) ($_ENV['ARTSFOLIO_PUBLIC_URL'] ?? getenv('ARTSFOLIO_PUBLIC_URL') ?: 'https://artsfol.io');

        return rtrim($baseUrl, '/') . '/assets/logo_2.png';
    }

    /**
     * Returns the logo block injected into every HTML email.
     */
    private static function logoHtml(): string
    {
        $logoUrl = htmlspecialchars(self::artsfolioLogoUrl(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div style="margin:0 0 24px 0;text-align:left;">'
            . '<img src="' . $logoUrl . '" alt="ArtsFolio" width="180" style="display:block;width:180px;max-width:60%;height:auto;border:0;">'
            . '</div>';
    }

    /**
     * Converts plain text paragraphs and URLs to safe HTML.
     */
    private static function paragraphsFromText(string $bodyText): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($bodyText));
        if ($normalized === '') {
            return '';
        }

        $blocks = preg_split("/\n{2,}/", $normalized) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $safe = htmlspecialchars(trim($block), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safe = nl2br($safe, false);
            $safe = preg_replace(
                '~(https?://[^\s<]+)~',
                '<a href="$1" style="color:#7656d6;text-decoration:underline;">$1</a>',
                $safe
            ) ?? $safe;

            $html[] = '<p style="margin:0 0 16px 0;">' . $safe . '</p>';
        }

        return implode('', $html);
    }
}

// End of file.
