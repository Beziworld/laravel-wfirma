<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Data;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Input of invoices/add.
 *
 * The contractor is either an existing id or inline data
 * (['name' => ..., 'nip' => ..., 'street' => ..., 'zip' => ..., 'city' => ..., 'country' => 'PL', 'email' => ...]).
 */
final readonly class InvoiceRequest
{
    /**
     * @param int|array<string, mixed> $contractor
     * @param list<InvoiceContent> $contents
     * @param string $type "normal" (VAT invoice), "proforma", "receipt_normal", "bill" (non-VAT payers), ...
     * @param string $paymentMethod "transfer", "cash", "compensation", "cod" or "payment_card".
     * @param array<string, mixed> $attributes Other fields, e.g. ['description' => '...', 'id_external' => 'order-1', 'schema' => 'split_payment'].
     */
    public function __construct(
        public int|array $contractor,
        public array $contents,
        public string $type = 'normal',
        public string $paymentMethod = 'transfer',
        public ?DateTimeInterface $date = null,
        public ?DateTimeInterface $disposalDate = null,
        public ?DateTimeInterface $paymentDate = null,
        public string $priceType = 'netto',
        public string $currency = 'PLN',
        public array $attributes = [],
    ) {
        if ($contents === []) {
            throw new InvalidArgumentException('An invoice needs at least one line.');
        }

        if (! in_array($priceType, ['netto', 'brutto'], true)) {
            throw new InvalidArgumentException('Price type must be "netto" or "brutto".');
        }
    }

    /**
     * Invoice record in the numerically keyed structure expected by wFirma.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $contents = [];

        foreach ($this->contents as $index => $content) {
            $contents[(string) $index] = ['invoicecontent' => $content->toArray()];
        }

        $invoice = array_filter([
            'contractor' => is_int($this->contractor) ? ['id' => $this->contractor] : $this->contractor,
            'type' => $this->type,
            'paymentmethod' => $this->paymentMethod,
            'date' => $this->date?->format('Y-m-d'),
            'disposaldate' => $this->disposalDate?->format('Y-m-d'),
            'paymentdate' => $this->paymentDate?->format('Y-m-d'),
            'price_type' => $this->priceType,
            'currency' => strtoupper($this->currency),
            'invoicecontents' => $contents,
        ], static fn(mixed $value): bool => $value !== null);

        return $invoice + $this->attributes;
    }
}
