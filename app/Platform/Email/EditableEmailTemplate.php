<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Loads an administrator-editable text email template.
 */
final class EditableEmailTemplate
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly string $templateRoot,
    ) {
    }

    /** @return array{subject:string,body:string} */
    public function render(string $relativePath, array $values): array
    {
        $rendered = $this->renderer->renderFile(
            rtrim($this->templateRoot, '/') . '/' . ltrim($relativePath, '/'),
            $values,
        );

        $subject = '';
        $bodyLines = [];

        foreach (preg_split('/\R/', $rendered) ?: [] as $line) {
            if ($subject === '' && preg_match('/^\s*Subject:\s*(.+?)\s*$/i', $line, $match) === 1) {
                $subject = trim((string) $match[1]);
                continue;
            }
            $bodyLines[] = $line;
        }

        return [
            'subject' => $subject !== '' ? $subject : 'ArtsFolio',
            'body' => trim(implode("\n", $bodyLines)),
        ];
    }
}

// End of file.
