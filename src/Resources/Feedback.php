<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;
use InvalidArgumentException;

class Feedback extends ApiResource
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function received(array $filters = []): Page
    {
        return $this->client->paginate('/my/feedback/received', 'feedbacks', $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function sent(array $filters = []): Page
    {
        return $this->client->paginate('/my/feedback/sent', 'feedbacks', $filters);
    }

    /**
     * Feedback the seller has left for an order's buyer.
     *
     * @return array<string, mixed>
     */
    public function forBuyer(string|int $orderNumber): array
    {
        return $this->client->get("/orders/{$orderNumber}/feedback/buyer");
    }

    /**
     * @param  int  $rating  1 to 5
     * @return array<string, mixed>
     */
    public function leaveForBuyer(string|int $orderNumber, string $message, int $rating): array
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('A Reverb feedback rating is between 1 and 5.');
        }

        return $this->client->post("/orders/{$orderNumber}/feedback/buyer", ['message' => $message, 'rating' => $rating]);
    }
}
