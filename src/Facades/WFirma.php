<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Facades;

use Beziworld\WFirma\WFirmaClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\WFirma\Data\Invoice createInvoice(\Beziworld\WFirma\Data\InvoiceRequest $request)
 * @method static \Beziworld\WFirma\Data\Invoice invoice(int $id)
 * @method static array{items: list<\Beziworld\WFirma\Data\Invoice>, total: int} findInvoices(array<string, scalar> $conditions = [], int $page = 1, int $limit = 20, string $orderBy = 'Invoice.id', string $direction = 'desc')
 * @method static void deleteInvoice(int $id)
 * @method static string pdf(int $id, string $page = 'invoice')
 * @method static void sendInvoice(int $id, string $email, ?string $subject = null, ?string $body = null)
 * @method static array<array-key, mixed> createContractor(array<string, mixed> $contractor)
 * @method static list<array<array-key, mixed>> findContractors(array<string, scalar> $conditions = [], int $page = 1, int $limit = 20)
 * @method static list<array<array-key, mixed>> findGoods(array<string, scalar> $conditions = [], int $page = 1, int $limit = 20)
 * @method static array<array-key, mixed> call(string $module, string $action, ?int $id = null, ?array<array-key, mixed> $body = null)
 * @method static \Beziworld\WFirma\WFirmaConfig config()
 *
 * @see WFirmaClient
 */
final class WFirma extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WFirmaClient::class;
    }
}
