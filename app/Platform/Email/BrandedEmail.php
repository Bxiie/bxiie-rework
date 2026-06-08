<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Applies the shared ArtsFolio identity shell to outbound email bodies.
 *
 * The application sends mail from multiple places: auth, lifecycle jobs,
 * tenant notifications, platform invites, and tenant user invites. This helper
 * keeps those messages visibly ArtsFolio-branded without duplicating markup or
 * footer language in every controller and service.
 */
final class BrandedEmail
{
    private const BRAND_NAME = 'ArtsFolio';
    private const BRAND_TAGLINE = 'Artist portfolios, sites, and audience tools.';
    private const PLATFORM_URL = 'https://artsfol.io';
    private const LOGO_URL = 'https://artsfol.io/assets/logo_2.png';

    /**
     * Builds a plain-text message with consistent brand header and footer.
     */
    public static function text(string $heading, string $body): string
    {
        $cleanHeading = self::cleanLine($heading !== '' ? $heading : self::BRAND_NAME);
        $cleanBody = trim(str_replace(["\r\n", "\r"], "\n", $body));

        return implode("\n", [
            self::BRAND_NAME,
            self::BRAND_TAGLINE,
            self::PLATFORM_URL,
            str_repeat('=', 52),
            '',
            $cleanHeading,
            '',
            $cleanBody,
            '',
            str_repeat('-', 52),
            'Sent by ArtsFolio. Manage your site and audience at ' . self::PLATFORM_URL . '.',
            '© ' . date('Y') . ' ArtsFolio. Terracopia, LLC.',
        ]);
    }

    /**
     * Builds a simple HTML companion body for mail clients and admin previews.
     */
    public static function htmlFromText(string $heading, string $body): string
    {
        $cleanHeading = self::cleanLine($heading !== '' ? $heading : self::BRAND_NAME);
        $paragraphs = array_values(array_filter(
            preg_split('/\n{2,}/', trim(str_replace(["\r\n", "\r"], "\n", $body))) ?: [],
            static fn (string $paragraph): bool => trim($paragraph) !== ''
        ));

        $content = '';
        foreach ($paragraphs as $paragraph) {
            $content .= '<p style="margin:0 0 16px;">' . nl2br(self::escape($paragraph)) . '</p>';
        }

        return '<!doctype html><html><body style="margin:0;background:#f4f0e8;color:#171411;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f0e8;padding:28px 12px;">'
            . '<tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fffaf2;border:1px solid #d8cbb7;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="padding:24px 28px;background:#171411;color:#fffaf2;">'
            . '<img src="' . self::LOGO_URL . '" width="96" alt="ArtsFolio" style="display:block;width:96px;max-width:96px;height:auto;margin:0 0 14px;border:0;">'
            . '<div style="font-size:26px;font-weight:800;letter-spacing:-0.02em;">ArtsFolio</div>'
            . '<div style="margin-top:6px;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#d9c9af;">Artist portfolios, sites, and audience tools.</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px;">'
            . '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.2;color:#171411;">' . self::escape($cleanHeading) . '</h1>'
            . $content
            . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#efe6d8;color:#5a5047;font-size:12px;line-height:1.5;">'
            . 'Sent by ArtsFolio. Manage your site and audience at <a href="https://artsfol.io" style="color:#171411;">artsfol.io</a>.<br>'
            . '© ' . date('Y') . ' ArtsFolio. Terracopia, LLC.'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    /**
     * Brands an existing HTML fragment without trying to parse or rewrite it.
     */
    public static function html(string $heading, string $html): string
    {
        $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        return self::htmlFromText($heading, $text !== '' ? $text : $heading);
    }

    private static function cleanLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
