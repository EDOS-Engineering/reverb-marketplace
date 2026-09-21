<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Page;

class Conversations extends ApiResource
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(bool $unreadOnly = false, array $filters = []): Page
    {
        return $this->client->paginate('/my/conversations', 'conversations', $filters + ($unreadOnly ? ['unread_only' => true] : []));
    }

    /**
     * One conversation with its messages in chronological order.
     *
     * @return array<string, mixed>
     */
    public function find(string|int $conversationId): array
    {
        return $this->client->get("/my/conversations/{$conversationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function markRead(string|int $conversationId, bool $read = true): array
    {
        return $this->client->put("/my/conversations/{$conversationId}", ['read' => $read]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reply(string|int $conversationId, string $body): array
    {
        return $this->client->post("/my/conversations/{$conversationId}/messages", ['body' => $body]);
    }
}
