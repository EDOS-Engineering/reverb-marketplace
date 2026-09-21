<?php

namespace Edos\ReverbMarketplace\Resources;

class Shop extends ApiResource
{
    /**
     * The shop, including its shipping_profiles.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->get('/shop');
    }

    /**
     * Shipping profiles can only be created and edited on Reverb's website;
     * the API reads them so a listing can name one by shipping_profile_id,
     * which Reverb strongly prefers to per-listing rates.
     *
     * @return list<array{id: string, name: string}>
     */
    public function shippingProfiles(): array
    {
        $profiles = $this->get()['shipping_profiles'] ?? [];

        return is_array($profiles) ? array_values($profiles) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function vacationStatus(): array
    {
        return $this->client->get('/shop/vacation');
    }

    /**
     * @return array<string, mixed>
     */
    public function enableVacation(): array
    {
        return $this->client->post('/shop/vacation');
    }

    /**
     * @return array<string, mixed>
     */
    public function disableVacation(): array
    {
        return $this->client->delete('/shop/vacation');
    }
}
