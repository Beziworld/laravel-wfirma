<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Exceptions;

use Throwable;

/**
 * Raised when wFirma reports a status other than OK or cannot be reached.
 *
 * statusCode() returns the wFirma status, e.g. "ERROR" (validation), "AUTH",
 * "ACCESS DENIED", "NOT FOUND", "COMPANY ID REQUIRED" or
 * "TOTAL REQUESTS LIMIT EXCEEDED"; errors() the field validation messages.
 */
final class ApiException extends WFirmaException
{
    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public function __construct(
        string $message,
        private readonly ?string $statusCode = null,
        private readonly array $errors = [],
        ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public static function status(string $statusCode, array $errors, ?string $message, int $httpStatus): self
    {
        $details = $errors === []
            ? ($message ?? '')
            : implode('; ', array_map(static fn(array $e): string => trim($e['field'] . ': ' . $e['message'], ': '), $errors));

        return new self(sprintf('wFirma error %s%s', $statusCode, $details !== '' ? ': ' . $details : ''), $statusCode, $errors, $httpStatus);
    }

    public static function transport(Throwable $previous): self
    {
        return new self('Could not connect to wFirma: ' . $previous->getMessage(), previous: $previous);
    }

    public function statusCode(): ?string
    {
        return $this->statusCode;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Request or execution time limits were exceeded; retry later (wFirma recommends running batches at night).
     */
    public function isLimitExceeded(): bool
    {
        return in_array($this->statusCode, ['TOTAL REQUESTS LIMIT EXCEEDED', 'TOTAL EXECUTION TIME LIMIT EXCEEDED', 'AUTH FAILED LIMIT WAIT 5 MINUTES'], true);
    }
}
