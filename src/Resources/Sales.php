<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;
use InvalidArgumentException;

/**
 * Sales are discount events a listing can join, not orders. Sales are
 * created on Reverb's website; the API manages membership.
 *
 * seller() and reverb() are open to Preferred Sellers only. Any other
 * account gets 401 "You must be a preferred seller to complete this
 * action", which surfaces as an AuthenticationException even though the
 * token is fine (confirmed against the API).
 */
class Sales extends ApiResource
{
    public const MAX_LISTINGS_PER_CALL = 25;

    /**
     * Sales this seller created, in any state.
     *
     * @return array<string, mixed>
     */
    public function seller(): array
    {
        return $this->client->get('/sales/seller');
    }

    /**
     * Reverb's own site-wide sales that are active now.
     */
    public function reverb(): Page
    {
        return $this->client->paginate('/sales/reverb', 'sales');
    }

    /**
     * The response reports success per listing under "results"; a 200 does
     * not mean every listing was added.
     *
     * @param  list<string|int>  $listingIds  At most 25
     * @return array<string, mixed>
     */
    public function addListings(string|int $saleId, array $listingIds): array
    {
        return $this->client->post("/sales/{$saleId}/listings", $this->listingIds($listingIds));
    }

    /**
     * @param  list<string|int>  $listingIds  At most 25
     * @return array<string, mixed>
     */
    public function removeListings(string|int $saleId, array $listingIds): array
    {
        return $this->client->delete("/sales/{$saleId}/listings", $this->listingIds($listingIds));
    }

    /**
     * @param  list<string|int>  $listingIds
     * @return array{listing_ids: list<string>}
     */
    protected function listingIds(array $listingIds): array
    {
        if ($listingIds === [] || count($listingIds) > self::MAX_LISTINGS_PER_CALL) {
            throw new InvalidArgumentException('Reverb takes between 1 and '.self::MAX_LISTINGS_PER_CALL.' listings per sale call.');
        }

        return ['listing_ids' => array_map(strval(...), array_values($listingIds))];
    }
}
