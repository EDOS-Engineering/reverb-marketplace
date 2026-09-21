<?php

namespace Edos\ReverbMarketplace\Resources;

class Account extends ApiResource
{
    /**
     * The account behind the token. The cheapest authenticated call, so
     * also the way to check a token and its environment.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->get('/my/account');
    }

    /**
     * Unread messages, open offers and similar badge counts.
     *
     * @return array<string, mixed>
     */
    public function counts(): array
    {
        return $this->client->get('/my/counts');
    }
}
