<?php

namespace Edos\ReverbMarketplace;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Reverb collection.
 *
 * Reverb wraps every collection in an object keyed by the resource name
 * ("listings", "orders") beside total, current_page, total_pages and HAL
 * links. Reverb asks clients to follow _links.next rather than build page
 * URLs, so the link is kept here for ReverbClient::cursor() to follow.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $currentPage,
        public readonly int $totalPages,
        public readonly ?string $nextHref,
        public readonly ?string $prevHref,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(array $body, string $key): self
    {
        $items = $body[$key] ?? [];
        $items = is_array($items) ? array_values($items) : [];

        return new self(
            items: $items,
            total: (int) ($body['total'] ?? count($items)),
            currentPage: (int) ($body['current_page'] ?? 1),
            totalPages: (int) ($body['total_pages'] ?? 1),
            nextHref: self::href($body, 'next'),
            prevHref: self::href($body, 'prev'),
            raw: $body,
        );
    }

    public function hasMorePages(): bool
    {
        return $this->nextHref !== null || $this->currentPage < $this->totalPages;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected static function href(array $body, string $rel): ?string
    {
        $href = $body['_links'][$rel]['href'] ?? null;

        return is_string($href) && $href !== '' ? $href : null;
    }
}
