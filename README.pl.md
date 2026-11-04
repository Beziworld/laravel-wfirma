# Laravel wFirma

Klient API [wFirma](https://wfirma.pl) dla Laravela: faktury, PDF, wysyłka e-mail, kontrahenci i towary, z
uwierzytelnianiem kluczami API i formatem JSON. Pakiet sam buduje i rozpakowuje numerycznie kluczowany JSON wFirmy.

[English documentation](README.md)

## Instalacja

```bash
composer require beziworld/laravel-wfirma
```

```dotenv
WFIRMA_ACCESS_KEY=access-key        # Ustawienia > Bezpieczeństwo > Aplikacje > Klucze API
WFIRMA_SECRET_KEY=secret-key
WFIRMA_APP_KEY=app-key              # wydawany przez wFirmę dla integracji
WFIRMA_COMPANY_ID=123456            # gdy użytkownik ma kilka firm
```

## Wystawienie faktury

```php
use Beziworld\WFirma\Data\InvoiceContent;
use Beziworld\WFirma\Data\InvoiceRequest;
use Beziworld\WFirma\Facades\WFirma;

$faktura = WFirma::createInvoice(new InvoiceRequest(
    contractor: ['name' => 'ACME Sp. z o.o.', 'nip' => '5261040828', 'zip' => '00-001', 'city' => 'Warszawa'],
    contents: [new InvoiceContent('Konsultacje', price: 1000.00, count: 2)],
    paymentDate: now()->addDays(14),
));

WFirma::sendInvoice($faktura->id, 'faktury@acme.pl');
$pdf = WFirma::pdf($faktura->id);
```

## Wyszukiwanie

```php
WFirma::findInvoices(['Invoice.paymentstate' => 'unpaid', 'Invoice.date ge' => '2026-11-01']);
WFirma::findContractors(['Contractor.nip' => '5261040828']);
```

## Autor

Pakiet tworzy i rozwija **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu), [biuro@beziworld.eu](mailto:biuro@beziworld.eu).
Potrzebujesz integracji fakturowania? Zapraszam do kontaktu przez [beziworld.eu](https://beziworld.eu).

## Licencja

MIT. Pakiet nie jest powiązany z wFirma.
