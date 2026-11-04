<?php

declare(strict_types=1);

namespace Beziworld\WFirma;

use Beziworld\WFirma\Data\Invoice;
use Beziworld\WFirma\Data\InvoiceRequest;
use Beziworld\WFirma\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Client of the wFirma API (api2.wfirma.pl) using API key authentication and JSON.
 *
 * wFirma wraps every record in a numerically keyed object, e.g.
 * {"invoices": {"0": {"invoice": {...}}}}; this client builds and unwraps
 * that structure so that callers work with plain arrays.
 */
class WFirmaClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly WFirmaConfig $config,
    ) {}

    public function config(): WFirmaConfig
    {
        return $this->config;
    }

    public function createInvoice(InvoiceRequest $request): Invoice
    {
        return Invoice::fromArray($this->records($this->call('invoices', 'add', body: $this->wrap('invoices', 'invoice', [$request->toArray()])), 'invoices', 'invoice')[0] ?? []);
    }

    public function invoice(int $id): Invoice
    {
        return Invoice::fromArray($this->records($this->call('invoices', 'get', $id), 'invoices', 'invoice')[0] ?? []);
    }

    /**
     * Finds invoices matching all conditions.
     *
     * @param array<string, scalar> $conditions Field to value (operator "eq"), e.g. ['Invoice.paymentstate' => 'unpaid'];
     *                                          use "field operator" keys for other operators, e.g. ['Invoice.date ge' => '2026-11-01'].
     * @return array{items: list<Invoice>, total: int}
     */
    public function findInvoices(array $conditions = [], int $page = 1, int $limit = 20, string $orderBy = 'Invoice.id', string $direction = 'desc'): array
    {
        $payload = $this->find('invoices', $conditions, $page, $limit, $orderBy, $direction);

        return [
            'items' => array_map(Invoice::fromArray(...), $this->records($payload, 'invoices', 'invoice')),
            'total' => $this->total($payload, 'invoices'),
        ];
    }

    public function deleteInvoice(int $id): void
    {
        $this->call('invoices', 'delete', $id);
    }

    /**
     * PDF of the invoice.
     *
     * @param string $page "invoice" (original), "invoicecopy" or "all".
     */
    public function pdf(int $id, string $page = 'invoice'): string
    {
        $response = $this->send('invoices', 'download', $id, $this->parameters('invoices', ['page' => $page]));

        if (str_contains(strtolower($response->header('Content-Type')), 'pdf') || str_starts_with($response->body(), '%PDF')) {
            return $response->body();
        }

        $this->guard($response);

        return $response->body();
    }

    /**
     * Sends the invoice by e-mail.
     */
    public function sendInvoice(int $id, string $email, ?string $subject = null, ?string $body = null): void
    {
        $this->call('invoices', 'send', $id, $this->parameters('invoices', array_filter([
            'email' => $email,
            'subject' => $subject,
            'body' => $body,
            'page' => 'invoice',
        ], static fn(?string $value): bool => $value !== null)));
    }

    /**
     * Creates a contractor and returns it, including its id.
     *
     * @param array<string, mixed> $contractor e.g. ['name' => 'ACME', 'nip' => '...', 'street' => '...', 'zip' => '00-001', 'city' => '...', 'email' => '...'].
     * @return array<array-key, mixed>
     */
    public function createContractor(array $contractor): array
    {
        return $this->records($this->call('contractors', 'add', body: $this->wrap('contractors', 'contractor', [$contractor])), 'contractors', 'contractor')[0] ?? [];
    }

    /**
     * @param array<string, scalar> $conditions e.g. ['Contractor.nip' => '5261040828'].
     * @return list<array<array-key, mixed>>
     */
    public function findContractors(array $conditions = [], int $page = 1, int $limit = 20): array
    {
        return $this->records($this->find('contractors', $conditions, $page, $limit, 'Contractor.id', 'desc'), 'contractors', 'contractor');
    }

    /**
     * @param array<string, scalar> $conditions e.g. ['Good.code' => 'SKU-1'].
     * @return list<array<array-key, mixed>>
     */
    public function findGoods(array $conditions = [], int $page = 1, int $limit = 20): array
    {
        return $this->records($this->find('goods', $conditions, $page, $limit, 'Good.id', 'desc'), 'goods', 'good');
    }

    /**
     * Sends any request to a module action, e.g. call('payments', 'add', body: [...]).
     *
     * @param array<array-key, mixed>|null $body
     * @return array<array-key, mixed>
     */
    public function call(string $module, string $action, ?int $id = null, ?array $body = null): array
    {
        $response = $this->send($module, $action, $id, $body);

        return $this->guard($response);
    }

    /**
     * @param array<string, scalar> $conditions
     * @return array<array-key, mixed>
     */
    private function find(string $module, array $conditions, int $page, int $limit, string $orderBy, string $direction): array
    {
        $list = [];

        foreach (array_keys($conditions) as $index => $key) {
            [$field, $operator] = array_pad(explode(' ', $key, 2), 2, 'eq');
            $list[(string) $index] = ['condition' => ['field' => $field, 'operator' => $operator, 'value' => $conditions[$key]]];
        }

        $parameters = [
            'page' => $page,
            'limit' => min(100, max(1, $limit)),
            'order' => [strtolower($direction) === 'asc' ? 'asc' : 'desc' => $orderBy],
        ];

        if ($list !== []) {
            $parameters['conditions'] = $list;
        }

        return $this->call($module, 'find', body: [$module => ['parameters' => $parameters]]);
    }

    /**
     * @param list<array<array-key, mixed>> $records
     * @return array<string, mixed>
     */
    private function wrap(string $module, string $record, array $records): array
    {
        $wrapped = [];

        foreach ($records as $index => $data) {
            $wrapped[(string) $index] = [$record => $data];
        }

        return [$module => $wrapped];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array<string, mixed>
     */
    private function parameters(string $module, array $parameters): array
    {
        $list = [];

        foreach (array_keys($parameters) as $index => $name) {
            $list[(string) $index] = ['parameter' => ['name' => $name, 'value' => $parameters[$name]]];
        }

        return [$module => ['parameters' => $list]];
    }

    /**
     * Extracts the records of a module from a response.
     *
     * @param array<array-key, mixed> $payload
     * @return list<array<array-key, mixed>>
     */
    private function records(array $payload, string $module, string $record): array
    {
        $records = [];

        foreach (is_array($payload[$module] ?? null) ? $payload[$module] : [] as $key => $item) {
            if (is_numeric($key) && is_array($item) && is_array($item[$record] ?? null)) {
                $records[] = $item[$record];
            }
        }

        return $records;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function total(array $payload, string $module): int
    {
        $parameters = is_array($payload[$module] ?? null) && is_array($payload[$module]['parameters'] ?? null) ? $payload[$module]['parameters'] : [];

        return is_numeric($parameters['total'] ?? null) ? (int) $parameters['total'] : 0;
    }

    /**
     * @param array<array-key, mixed>|null $body
     */
    private function send(string $module, string $action, ?int $id, ?array $body): Response
    {
        $path = '/' . $module . '/' . $action . ($id !== null ? '/' . $id : '');
        $query = array_filter([
            'inputFormat' => 'json',
            'outputFormat' => 'json',
            'company_id' => $this->config->companyId,
        ], static fn(?string $value): bool => $value !== null);

        $request = $this->http
            ->createPendingRequest()
            ->withHeaders([
                'accessKey' => $this->config->accessKey,
                'secretKey' => $this->config->secretKey,
                'appKey' => $this->config->appKey,
            ])
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->withUserAgent('beziworld-laravel-wfirma/1.0');

        if ($body !== null) {
            // JSON_FORCE_OBJECT keeps the numerically keyed records as objects, which wFirma requires.
            $request->withBody((string) json_encode($body, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json');
        }

        try {
            return $request->send($body === null ? 'GET' : 'POST', $this->config->baseUrl . $path . '?' . http_build_query($query));
        } catch (ConnectionException $exception) {
            throw ApiException::transport($exception);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function guard(Response $response): array
    {
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
        $code = is_string($status['code'] ?? null) ? $status['code'] : ($response->successful() ? 'UNKNOWN' : 'HTTP ' . $response->status());

        if ($code !== 'OK') {
            throw ApiException::status($code, self::errors($payload), is_string($status['message'] ?? null) ? $status['message'] : null, $response->status());
        }

        return $payload;
    }

    /**
     * Collects validation errors attached to records, including nested contractor and invoice contents.
     *
     * @param array<array-key, mixed> $data
     * @return list<array{field: string, message: string}>
     */
    private static function errors(array $data): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ($key === 'error' && is_string($value['message'] ?? null)) {
                $errors[] = ['field' => is_string($value['field'] ?? null) ? $value['field'] : '', 'message' => $value['message']];

                continue;
            }

            $errors = [...$errors, ...self::errors($value)];
        }

        return $errors;
    }
}
