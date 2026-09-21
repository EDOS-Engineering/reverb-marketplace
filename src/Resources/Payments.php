<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;

class Payments extends ApiResource
{
    /**
     * Payments received as a seller. Filter with order_id for one order.
     *
     * @param  array<string, mixed>  $filters
     */
    public function selling(array $filters = []): Page
    {
        return $this->client->paginate('/my/payments/selling', 'payments', $filters);
    }

    public function forOrder(string|int $orderNumber): Page
    {
        return $this->selling(['order_id' => $orderNumber]);
    }
}
