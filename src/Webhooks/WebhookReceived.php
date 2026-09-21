<?php

namespace Edos\ReverbMarketplace\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A delivery passed the token check. The payload is the order object as
 * Reverb sent it and is unsigned: use it to learn which order changed, and
 * read the order back through Orders::find() before acting on any amount
 * or status in it.
 *
 * Reverb retries a delivery that is not answered 2xx, so listeners must be
 * idempotent; keying on orderNumber() is the usual way.
 */
class WebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function orderNumber(): ?string
    {
        $number = $this->payload['order_number'] ?? $this->payload['id'] ?? null;

        return is_scalar($number) && (string) $number !== '' ? (string) $number : null;
    }

    public function status(): ?string
    {
        $status = $this->payload['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
