# Laravel wFirma

[![tests](https://github.com/beziworld/laravel-wfirma/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-wfirma/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-wfirma.svg)](https://packagist.org/packages/beziworld/laravel-wfirma)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-wfirma.svg)](LICENSE.md)

[wFirma](https://wfirma.pl) API client for Laravel: invoices, PDF, e-mail delivery, contractors and goods, with API
key authentication and JSON. The client builds and unwraps wFirma's numerically keyed JSON for you.

[Polska wersja dokumentacji](README.pl.md)

## Installation

```bash
composer require beziworld/laravel-wfirma
php artisan vendor:publish --tag=wfirma-config   # optional
```

```dotenv
WFIRMA_ACCESS_KEY=your-access-key   # Settings > Security > Applications > API keys
WFIRMA_SECRET_KEY=your-secret-key
WFIRMA_APP_KEY=your-app-key         # issued by wFirma for your integration
WFIRMA_COMPANY_ID=123456            # required when the user has more than one company
```

## Issue an invoice

```php
use Beziworld\WFirma\Data\InvoiceContent;
use Beziworld\WFirma\Data\InvoiceRequest;
use Beziworld\WFirma\Facades\WFirma;

$invoice = WFirma::createInvoice(new InvoiceRequest(
    contractor: [
        'name' => 'ACME Sp. z o.o.', 'nip' => '5261040828',
        'street' => 'Prosta 1', 'zip' => '00-001', 'city' => 'Warszawa', 'email' => 'faktury@acme.pl',
    ],                                                // or an existing contractor id
    contents: [
        new InvoiceContent('Consulting', price: 1000.00, count: 2),
        new InvoiceContent('Training', price: 500.00, vat: 'zw'),
    ],
    paymentDate: now()->addDays(14),
    priceType: 'netto',                               // prices are net; "brutto" for gross prices
    attributes: ['id_external' => $order->number],
));

$invoice->fullNumber;   // "FV 1/11/2026"
$invoice->total;        // "2500.00"

WFirma::sendInvoice($invoice->id, 'faktury@acme.pl', 'Your invoice');
$pdf = WFirma::pdf($invoice->id);
```

## Search

```php
// conditions are combined with AND; "field operator" keys select other operators (ne, gt, lt, ge, le, like, in)
$result = WFirma::findInvoices(['Invoice.paymentstate' => 'unpaid', 'Invoice.date ge' => '2026-11-01'], page: 1, limit: 50);
$result['items'];   // list<Invoice>
$result['total'];

WFirma::findContractors(['Contractor.nip' => '5261040828']);
WFirma::findGoods(['Good.code' => 'SKU-1']);
WFirma::createContractor(['name' => 'ACME', 'nip' => '5261040828']);
```

Any other module is available through `WFirma::call($module, $action, $id, $body)`; the body uses wFirma's
structure, e.g. `['payments' => ['0' => ['payment' => [...]]]]`.

## Exceptions

`ApiException` exposes the wFirma status through `statusCode()` (`ERROR`, `AUTH`, `ACCESS DENIED`, `NOT FOUND`,
`COMPANY ID REQUIRED`, ...), the field validation messages through `errors()` (also from nested contractor and
lines) and `isLimitExceeded()` for request or time limits. wFirma does not publish exact limits and recommends
running large batches at night.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues: [GitHub issues](https://github.com/beziworld/laravel-wfirma/issues)

Need an invoicing integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

[laravel-fakturownia](https://github.com/beziworld/laravel-fakturownia) ·
[laravel-infakt](https://github.com/beziworld/laravel-infakt) ·
[laravel-company-data](https://github.com/beziworld/laravel-company-data) ·
[laravel-nbp](https://github.com/beziworld/laravel-nbp)

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with wFirma.
