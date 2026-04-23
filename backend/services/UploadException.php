<?php

declare(strict_types=1);

namespace App\Services;

final class UploadException extends \RuntimeException
{
    /**
     * @param array<string,mixed>|null $details
     */
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
        private readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCodeString(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }
}

