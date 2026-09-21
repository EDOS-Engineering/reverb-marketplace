<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;
use Illuminate\Support\LazyCollection;

/**
 * A payout is one transfer to the seller's bank and may cover many orders;
 * its line items are the per-order breakdown. Needs the read_payouts scope.
 */
class Payouts extends ApiResource
{
    /**
     * @param  array<string, mixed>  $filters  created_start_date, created_end_date, page, per_page
     */
    public function list(array $filters = []): Page
    {
        return $this->client->paginate('/my/payouts', 'payouts', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function lineItems(string|int $payoutId, array $filters = []): Page
    {
        return $this->client->paginate("/my/payouts/{$payoutId}/line_items", 'line_items', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allLineItems(string|int $payoutId, array $filters = []): LazyCollection
    {
        return $this->client->cursor("/my/payouts/{$payoutId}/line_items", 'line_items', $filters);
    }
}
