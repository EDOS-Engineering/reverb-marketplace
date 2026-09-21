<?php

namespace Edos\ReverbMarketplace\Resources;

/**
 * Direct Offers: an automatic discounted offer sent to a listing's
 * watchers. The feature is per account; without it Reverb answers 403.
 */
class DirectOffers extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function find(string|int $listingId): array
    {
        return $this->client->get("/listings/{$listingId}/auto_offer");
    }

    /**
     * @param  int  $percentage  Whole percent off: 10 means 10%
     * @return array<string, mixed>
     */
    public function assign(string|int $listingId, int $percentage): array
    {
        return $this->client->post("/listings/{$listingId}/auto_offer", ['offer_percentage' => $percentage]);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string|int $listingId): array
    {
        return $this->client->delete("/listings/{$listingId}/auto_offer");
    }
}
