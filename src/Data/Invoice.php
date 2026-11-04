<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Data;

use Carbon\CarbonImmutable;

/**
 * Invoice returned by wFirma.
 */
final readonly class Invoice
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public ?string $fullNumber,
        public ?string $type,
        public ?string $paymentState,
        public ?string $total,
        public ?string $netto,
        public ?string $tax,
        public ?string $currency,
        public ?CarbonImmutable $date,
        public ?CarbonImmutable $paymentDate,
        public ?string $contractorName,
        public ?string $ksefStatus,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn(array $source, string $key): ?string => is_scalar($source[$key] ?? null) && (string) $source[$key] !== '' ? (string) $source[$key] : null;
        $date = static function (string $key) use ($data, $string): ?CarbonImmutable {
            $value = $string($data, $key);

            return $value !== null && $value !== '0000-00-00' ? CarbonImmutable::parse($value)->startOfDay() : null;
        };
        $contractor = is_array($data['contractor'] ?? null) ? $data['contractor'] : [];

        return new self(
            id: is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            fullNumber: $string($data, 'fullnumber'),
            type: $string($data, 'type'),
            paymentState: $string($data, 'paymentstate'),
            total: $string($data, 'total'),
            netto: $string($data, 'netto'),
            tax: $string($data, 'tax'),
            currency: $string($data, 'currency'),
            date: $date('date'),
            paymentDate: $date('paymentdate'),
            contractorName: $string($contractor, 'name'),
            ksefStatus: $string($data, 'ksef_status'),
            raw: $data,
        );
    }

    public function isPaid(): bool
    {
        return $this->paymentState === 'paid';
    }
}
