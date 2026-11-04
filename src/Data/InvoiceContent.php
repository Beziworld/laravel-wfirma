<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Data;

use InvalidArgumentException;

/**
 * Invoice line. The price is net or gross according to the price type of the invoice.
 */
final readonly class InvoiceContent
{
    /**
     * @param string $vat Rate such as "23", "8", "5", "0", "zw", "np".
     * @param array<string, mixed> $attributes Other fields, e.g. ['classification' => '62.01.11.0', 'discount_percent' => 10].
     */
    public function __construct(
        public string $name,
        public float $price,
        public float $count = 1,
        public string $vat = '23',
        public string $unit = 'szt.',
        public array $attributes = [],
    ) {
        if ($count <= 0) {
            throw new InvalidArgumentException('Count must be positive.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'unit' => $this->unit,
            'count' => self::decimal($this->count),
            'price' => self::decimal($this->price),
            'vat' => $this->vat,
        ] + $this->attributes;
    }

    private static function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
