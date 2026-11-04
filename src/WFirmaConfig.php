<?php

declare(strict_types=1);

namespace Beziworld\WFirma;

use Beziworld\WFirma\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable view of the "wfirma" configuration file.
 */
final readonly class WFirmaConfig
{
    public function __construct(
        #[\SensitiveParameter]
        public string $accessKey,
        #[\SensitiveParameter]
        public string $secretKey,
        public string $appKey,
        public ?string $companyId = null,
        public string $baseUrl = 'https://api2.wfirma.pl',
        public int $timeout = 30,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            accessKey: self::string($config['access_key'] ?? null) ?? throw InvalidConfiguration::missing('access_key', 'WFIRMA_ACCESS_KEY'),
            secretKey: self::string($config['secret_key'] ?? null) ?? throw InvalidConfiguration::missing('secret_key', 'WFIRMA_SECRET_KEY'),
            appKey: self::string($config['app_key'] ?? null) ?? throw InvalidConfiguration::missing('app_key', 'WFIRMA_APP_KEY'),
            companyId: self::string($config['company_id'] ?? null),
            baseUrl: rtrim(self::string($config['base_url'] ?? null) ?? 'https://api2.wfirma.pl', '/'),
            timeout: is_numeric($config['timeout'] ?? null) && (int) $config['timeout'] > 0 ? (int) $config['timeout'] : 30,
        );
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
