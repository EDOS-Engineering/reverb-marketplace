<?php

namespace Edos\ReverbMarketplace\Support;

/**
 * Reverb writes money as a decimal string beside a currency code and reads
 * it back with amount_cents added. These helpers cross that boundary
 * without floating-point arithmetic on the way out.
 */
final class Money
{
    /**
     * @return array{amount: string, currency: string}
     */
    public static function fromCents(int $cents, string $currency = 'USD'): array
    {
        return [
            'amount' => sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100),
            'currency' => strtoupper($currency),
        ];
    }

    /**
     * Cents from a money object in a response. Prefers amount_cents, which
     * Reverb includes on most objects, and falls back to the decimal string
     * for the few (payments) that omit it.
     *
     * @param  array<string, mixed>|null  $money
     */
    public static function toCents(?array $money): ?int
    {
        if ($money === null) {
            return null;
        }

        if (isset($money['amount_cents']) && is_numeric($money['amount_cents'])) {
            return (int) $money['amount_cents'];
        }

        if (! isset($money['amount']) || ! is_numeric($money['amount'])) {
            return null;
        }

        return (int) round(((float) $money['amount']) * 100);
    }
}
