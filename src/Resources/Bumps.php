<?php

namespace Edos\ReverbMarketplace\Resources;

/**
 * Bump is Reverb's paid placement: a percentage fee charged only when the
 * bumped listing sells.
 */
class Bumps extends ApiResource
{
    /**
     * Stats and the bids available for a listing. bump_v2_stats is absent
     * when the listing has never been bumped.
     *
     * @return array<string, mixed>
     */
    public function find(string|int $listingId): array
    {
        return $this->client->get("/listings/{$listingId}/bump");
    }

    /**
     * @param  list<string|int>  $listingIds
     * @param  float  $bid  A fraction, e.g. 0.035 for 3.5%; must be one of the listing's offered bids
     * @return array<string, mixed>
     */
    public function bid(array $listingIds, float $bid): array
    {
        return $this->client->put('/bump/v2/bids', ['products' => array_values($listingIds), 'bid' => $bid]);
    }

    /**
     * @param  list<string|int>  $listingIds
     * @return array<string, mixed>
     */
    public function remove(array $listingIds): array
    {
        return $this->client->delete('/bump/v2/bids', ['products' => array_values($listingIds)]);
    }
}
