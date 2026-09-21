<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;
use Edos\ReverbMarketplace\Support\Money;

/**
 * Offers buyers make on the seller's listings.
 */
class Negotiations extends ApiResource
{
    /**
     * Listings that carry an active offer. Reverb wraps these rows under
     * "listings", not "negotiations" (confirmed against the API).
     *
     * @param  array<string, mixed>  $filters
     */
    public function active(array $filters = []): Page
    {
        return $this->client->paginate('/my/listings/negotiations', 'listings', $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string|int $negotiationId): array
    {
        return $this->client->get("/my/negotiations/{$negotiationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(string|int $negotiationId): array
    {
        return $this->client->post("/my/negotiations/{$negotiationId}/accept");
    }

    /**
     * @return array<string, mixed>
     */
    public function decline(string|int $negotiationId): array
    {
        return $this->client->post("/my/negotiations/{$negotiationId}/decline");
    }

    /**
     * @return array<string, mixed>
     */
    public function counter(string|int $negotiationId, int $priceCents, ?int $shippingCents = null, string $currency = 'USD'): array
    {
        return $this->client->post("/my/negotiations/{$negotiationId}/counter", array_filter([
            'price' => Money::fromCents($priceCents, $currency),
            'shipping_price' => $shippingCents === null ? null : Money::fromCents($shippingCents, $currency),
        ]));
    }
}
