<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Renders simple text templates using {{ key }} placeholders.
 */
final class TemplateRenderer
{
    public function render(string $template, array $values): string
    {
        $rendered = $template;

        foreach ($values as $key => $value) {
            $rendered = str_replace('{{ ' . $key . ' }}', (string) $value, $rendered);
            $rendered = str_replace('{{' . $key . '}}', (string) $value, $rendered);
        }

        return $rendered;
    }

    public function renderFile(string $path, array $values): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Template file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read template file: {$path}");
        }

        return $this->render($contents, $values);
    }
}

// End of file.
