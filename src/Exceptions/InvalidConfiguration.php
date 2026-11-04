<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Exceptions;

/**
 * Raised when the package configuration is incomplete.
 */
final class InvalidConfiguration extends WFirmaException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('wFirma "%s" is not configured. Set %s in the environment.', $key, $env));
    }
}
