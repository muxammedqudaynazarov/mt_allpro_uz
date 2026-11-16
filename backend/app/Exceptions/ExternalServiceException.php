<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Tashqi xizmat (Zoom, HEMIS, Telegram) bilan bog'liq xato.
 * Foydalanuvchiga tushunarli xabar bilan 502 javob qaytaradi; texnik
 * tafsilotlar faqat logga yoziladi.
 */
class ExternalServiceException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $userMessage, public readonly array $context = [], int $status = 502)
    {
        parent::__construct($userMessage, $status);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->getCode() ?: 502);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
