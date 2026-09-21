<?php

namespace Edos\ReverbMarketplace;

enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';

    /**
     * Anything that is not explicitly "production" resolves to the sandbox,
     * so a typo or an unset variable cannot reach a live shop.
     */
    public static function fromConfig(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Sandbox;
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://api.reverb.com/api',
            self::Sandbox => 'https://sandbox.reverb.com/api',
        };
    }

    /**
     * The website that belongs to this environment, for building links to
     * dashboard pages the API cannot reach (shipping profiles, webhooks).
     */
    public function webUrl(): string
    {
        return match ($this) {
            self::Production => 'https://reverb.com',
            self::Sandbox => 'https://sandbox.reverb.com',
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
