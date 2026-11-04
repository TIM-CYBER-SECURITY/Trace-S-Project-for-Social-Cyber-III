<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/** Keluaran model tidak sesuai skema setelah retry terbatas. */
class AiInvalidOutputException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(string $message, public readonly array $errors = [], public readonly ?string $raw = null)
    {
        parent::__construct($message);
    }
}
