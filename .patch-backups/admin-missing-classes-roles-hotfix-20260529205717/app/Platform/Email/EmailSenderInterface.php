<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Defines the contract for email sender implementations.
 */
interface EmailSenderInterface
{
    public function send(array $email): string;
}

// End of file.
