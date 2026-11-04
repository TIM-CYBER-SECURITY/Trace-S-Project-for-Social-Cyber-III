<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/** AI Server offline / timeout / penuh / belum dikonfigurasi. Tidak pernah diganti hasil palsu. */
class AiUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly string $state = 'OFFLINE')
    {
        parent::__construct($message);
    }
}
