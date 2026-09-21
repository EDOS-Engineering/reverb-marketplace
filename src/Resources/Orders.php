<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;
use Illuminate\Support\LazyCollection;

/**
 * Orders. One Reverb order is one listing; items bought in the same
 * checkout share an order_bundle_id, which is how to combine shipping.
 *
 * Filters accept created_start_date, created_end_date, updated_start_date,
 * updated_end_date (DateTimeInterface or ISO 8601), page and per_page.
 * Poll on the updated_* pair: an order placed before the window but paid
 * inside it is missed by a created_* filter.
 */
class Orders extends ApiResource
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function selling(array $filters = []): Page
    {
        return $this->client->paginate('/my/orders/selling/all', 'orders', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allSelling(array $filters = []): LazyCollection
    {
        return $this->client->cursor('/my/orders/selling/all', 'orders', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function unpaid(array $filters = []): Page
    {
        return $this->client->paginate('/my/orders/selling/unpaid', 'orders', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function awaitingShipment(array $filters = []): Page
    {
        return $this->client->paginate('/my/orders/selling/awaiting_shipment', 'orders', $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string|int $orderNumber): array
    {
        return $this->client->get("/my/orders/selling/{$orderNumber}");
    }

    /**
     * Mark an order shipped. The provider must be one of
     * Catalog::shippingProviders(). With $notifyBuyer, Reverb emails the
     * buyer the tracking details.
     *
     * @return array<string, mixed>
     */
    public function ship(string|int $orderNumber, string $provider, string $trackingNumber, bool $notifyBuyer = true): array
    {
        return $this->client->post("/my/orders/selling/{$orderNumber}/ship", [
            'provider' => $provider,
            'tracking_number' => $trackingNumber,
            'send_notification' => $notifyBuyer,
        ]);
    }

    /**
     * Local-pickup orders only.
     *
     * @return array<string, mixed>
     */
    public function markPickedUp(string|int $orderNumber): array
    {
        return $this->client->post("/my/orders/selling/{$orderNumber}/mark_picked_up");
    }

    /**
     * Orders this account placed as a buyer.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buying(array $filters = []): Page
    {
        return $this->client->paginate('/my/orders/buying/all', 'orders', $filters);
    }
}
