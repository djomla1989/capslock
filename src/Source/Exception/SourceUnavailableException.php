<?php

declare(strict_types=1);

namespace App\Source\Exception;

final class SourceUnavailableException extends \RuntimeException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
