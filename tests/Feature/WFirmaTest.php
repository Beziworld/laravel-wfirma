<?php

declare(strict_types=1);

use Beziworld\WFirma\Data\InvoiceContent;
use Beziworld\WFirma\Data\InvoiceRequest;
use Beziworld\WFirma\Exceptions\ApiException;
use Beziworld\WFirma\Exceptions\InvalidConfiguration;
use Beziworld\WFirma\Facades\WFirma;
use Beziworld\WFirma\WFirmaConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const WFIRMA = 'https://api2.wfirma.pl';

const WF_INVOICE = [
    'id' => '1234', 'fullnumber' => 'FV 1/11/2026', 'type' => 'normal', 'paymentstate' => 'unpaid', 'total' => '246.00',
    'netto' => '200.00', 'tax' => '46.00', 'currency' => 'PLN', 'date' => '2026-11-04', 'paymentdate' => '2026-11-18',
    'contractor' => ['id' => '55', 'name' => 'ACME Sp. z o.o.'], 'ksef_status' => '',
];

it('creates an invoice with numerically keyed json objects', function (): void {
    Http::fake([WFIRMA . '/invoices/add*' => Http::response(['invoices' => ['0' => ['invoice' => WF_INVOICE]], 'status' => ['code' => 'OK']])]);

    $invoice = WFirma::createInvoice(new InvoiceRequest(
        contractor: ['name' => 'ACME Sp. z o.o.', 'nip' => '5261040828', 'zip' => '00-001', 'city' => 'Warszawa'],
        contents: [new InvoiceContent('Usługa', 100.0, 2), new InvoiceContent('Szkolenie', 50, vat: 'zw')],
        date: new DateTimeImmutable('2026-11-04'),
        paymentDate: new DateTimeImmutable('2026-11-18'),
        attributes: ['id_external' => 'order-1001'],
    ));

    expect($invoice->id)->toBe(1234)
        ->and($invoice->fullNumber)->toBe('FV 1/11/2026')
        ->and($invoice->contractorName)->toBe('ACME Sp. z o.o.')
        ->and($invoice->isPaid())->toBeFalse()
        ->and($invoice->ksefStatus)->toBeNull();

    Http::assertSent(fn(Request $request): bool => $request->url() === WFIRMA . '/invoices/add?inputFormat=json&outputFormat=json&company_id=777'
        && $request->hasHeader('accessKey', 'access')
        && $request->hasHeader('secretKey', 'secret')
        && $request->hasHeader('appKey', 'app')
        && $request->body() === '{"invoices":{"0":{"invoice":{"contractor":{"name":"ACME Sp. z o.o.","nip":"5261040828","zip":"00-001","city":"Warszawa"},"type":"normal","paymentmethod":"transfer","date":"2026-11-04","paymentdate":"2026-11-18","price_type":"netto","currency":"PLN","invoicecontents":{"0":{"invoicecontent":{"name":"Usługa","unit":"szt.","count":"2.00","price":"100.00","vat":"23"}},"1":{"invoicecontent":{"name":"Szkolenie","unit":"szt.","count":"1.00","price":"50.00","vat":"zw"}}},"id_external":"order-1001"}}}}');
});

it('reads, finds and deletes invoices', function (): void {
    Http::fake([
        WFIRMA . '/invoices/get/1234*' => Http::response(['invoices' => ['0' => ['invoice' => WF_INVOICE]], 'status' => ['code' => 'OK']]),
        WFIRMA . '/invoices/find*' => Http::response(['invoices' => ['0' => ['invoice' => WF_INVOICE], '1' => ['invoice' => ['id' => '1235'] + WF_INVOICE], 'parameters' => ['limit' => '20', 'page' => '1', 'total' => '42']], 'status' => ['code' => 'OK']]),
        WFIRMA . '/invoices/delete/1234*' => Http::response(['status' => ['code' => 'OK']]),
    ]);

    $found = WFirma::findInvoices(['Invoice.paymentstate' => 'unpaid', 'Invoice.date ge' => '2026-11-01']);

    expect(WFirma::invoice(1234)->paymentDate?->toDateString())->toBe('2026-11-18')
        ->and($found['total'])->toBe(42)
        ->and($found['items'])->toHaveCount(2);

    WFirma::deleteInvoice(1234);

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/invoices/find')
        && $request->body() === '{"invoices":{"parameters":{"page":1,"limit":20,"order":{"desc":"Invoice.id"},"conditions":{"0":{"condition":{"field":"Invoice.paymentstate","operator":"eq","value":"unpaid"}},"1":{"condition":{"field":"Invoice.date","operator":"ge","value":"2026-11-01"}}}}}}');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/invoices/get/1234') && $request->method() === 'GET');
});

it('downloads the pdf and sends the invoice by e-mail', function (): void {
    Http::fake([
        WFIRMA . '/invoices/download/1234*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        WFIRMA . '/invoices/send/1234*' => Http::response(['status' => ['code' => 'OK']]),
    ]);

    expect(WFirma::pdf(1234))->toBe('%PDF-1.4');

    WFirma::sendInvoice(1234, 'jan@example.com', 'Faktura');

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/invoices/send/1234')
        && $request->body() === '{"invoices":{"parameters":{"0":{"parameter":{"name":"email","value":"jan@example.com"}},"1":{"parameter":{"name":"subject","value":"Faktura"}},"2":{"parameter":{"name":"page","value":"invoice"}}}}}');
});

it('manages contractors and goods', function (): void {
    Http::fake([
        WFIRMA . '/contractors/add*' => Http::response(['contractors' => ['0' => ['contractor' => ['id' => '55', 'name' => 'ACME']]], 'status' => ['code' => 'OK']]),
        WFIRMA . '/contractors/find*' => Http::response(['contractors' => ['0' => ['contractor' => ['id' => '55']], 'parameters' => ['total' => 1]], 'status' => ['code' => 'OK']]),
        WFIRMA . '/goods/find*' => Http::response(['goods' => ['0' => ['good' => ['id' => '9', 'code' => 'SKU']]], 'status' => ['code' => 'OK']]),
    ]);

    expect(WFirma::createContractor(['name' => 'ACME', 'nip' => '5261040828'])['id'])->toBe('55')
        ->and(WFirma::findContractors(['Contractor.nip' => '5261040828'])[0]['id'])->toBe('55')
        ->and(WFirma::findGoods(['Good.code' => 'SKU'])[0]['code'])->toBe('SKU');
});

it('collects nested validation errors', function (): void {
    Http::fake(['*' => Http::response(['invoices' => ['0' => ['invoice' => [
        'errors' => ['0' => ['error' => ['field' => 'paymentdate', 'message' => 'Niepoprawna data']]],
        'contractor' => ['errors' => ['0' => ['error' => ['field' => 'zip', 'message' => 'Niepoprawny kod pocztowy']]]],
    ]]], 'status' => ['code' => 'ERROR']])]);

    try {
        WFirma::createInvoice(new InvoiceRequest(55, [new InvoiceContent('x', 1)]));
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->statusCode())->toBe('ERROR')
            ->and($exception->errors())->toBe([
                ['field' => 'paymentdate', 'message' => 'Niepoprawna data'],
                ['field' => 'zip', 'message' => 'Niepoprawny kod pocztowy'],
            ])
            ->and($exception->getMessage())->toBe('wFirma error ERROR: paymentdate: Niepoprawna data; zip: Niepoprawny kod pocztowy');
    }
});

it('maps fatal statuses', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['status' => ['code' => 'AUTH']], 401)
        ->push(['status' => ['code' => 'TOTAL REQUESTS LIMIT EXCEEDED']])
        ->push('<html>', 500),
    ]);

    expect(fn() => WFirma::invoice(1))->toThrow(ApiException::class, 'wFirma error AUTH')
        ->and(fn() => WFirma::invoice(1))->toThrow(fn(ApiException $e) => expect($e->isLimitExceeded())->toBeTrue())
        ->and(fn() => WFirma::invoice(1))->toThrow(ApiException::class, 'HTTP 500');
});

it('wraps connection errors', function (): void {
    Http::fake(fn() => throw new ConnectionException('timeout'));
    expect(fn() => WFirma::invoice(1))->toThrow(ApiException::class, 'Could not connect to wFirma');
});

it('validates input and configuration', function (): void {
    expect(fn() => new InvoiceRequest(1, []))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new InvoiceRequest(1, [new InvoiceContent('x', 1)], priceType: 'gross'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new InvoiceContent('x', 1, 0))->toThrow(InvalidArgumentException::class)
        ->and((new InvoiceRequest(5, [new InvoiceContent('x', 1)]))->toArray()['contractor'])->toBe(['id' => 5])
        ->and(fn() => WFirmaConfig::fromArray([]))->toThrow(InvalidConfiguration::class, 'WFIRMA_ACCESS_KEY')
        ->and(fn() => WFirmaConfig::fromArray(['access_key' => 'a', 'secret_key' => 's']))->toThrow(InvalidConfiguration::class, 'WFIRMA_APP_KEY');
});
