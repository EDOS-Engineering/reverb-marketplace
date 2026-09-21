<?php

namespace Edos\ReverbMarketplace\Resources;

use Closure;
use Illuminate\Support\Str;

/**
 * Reference data: the vocabularies a listing or a shipment must draw from.
 * All of it is public, slow-changing and cached when the client has a
 * cache (cache_ttl seconds, keyed per environment).
 */
class Catalog extends ApiResource
{
    /**
     * The category tree.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->remember('categories', fn (): array => $this->rows('/categories', 'categories'));
    }

    /**
     * Every category as a flat row of uuid, name and full_name. A listing
     * takes up to two subcategories, or a root and two subcategories.
     * Organisational "middle" categories are in this list but are refused
     * by create and update with a 400.
     *
     * @return list<array<string, mixed>>
     */
    public function flatCategories(): array
    {
        return $this->remember('categories-flat', fn (): array => $this->rows('/categories/flat', 'categories'));
    }

    /**
     * The conditions this token's shop may list under. B-Stock and Mint
     * (with inventory) appear only for accounts enabled for them.
     *
     * @return list<array<string, mixed>>
     */
    public function conditions(): array
    {
        return $this->remember('conditions', fn (): array => $this->rows('/listing_conditions', 'conditions'));
    }

    /**
     * The uuid for a condition named by slug or display name ("brand-new",
     * "Brand New"), or null when this shop may not use it.
     */
    public function conditionUuid(string $slugOrName): ?string
    {
        $wanted = Str::slug($slugOrName);

        foreach ($this->conditions() as $condition) {
            if (Str::slug((string) ($condition['display_name'] ?? '')) === $wanted) {
                return isset($condition['uuid']) ? (string) $condition['uuid'] : null;
            }
        }

        return null;
    }

    /**
     * Region codes for shipping rates (US_CON, XX for everywhere else, ...).
     *
     * @return list<array<string, mixed>>
     */
    public function shippingRegions(): array
    {
        return $this->remember('shipping-regions', fn (): array => $this->rows('/shipping/regions', 'shipping_regions'));
    }

    /**
     * Carriers accepted by Orders::ship().
     *
     * @return list<array<string, mixed>>
     */
    public function shippingProviders(): array
    {
        return $this->remember('shipping-providers', fn (): array => $this->rows('/shipping/providers', 'shipping_providers'));
    }

    /**
     * Country codes with their subregions.
     *
     * @return list<array<string, mixed>>
     */
    public function countries(): array
    {
        return $this->remember('countries', fn (): array => $this->rows('/countries', 'countries'));
    }

    /**
     * Currencies a listing may be priced in.
     *
     * @return list<array<string, mixed>>
     */
    public function listingCurrencies(): array
    {
        return $this->remember('currencies-listing', fn (): array => $this->rows('/currencies/listing', 'currencies'));
    }

    /**
     * Values accepted by the X-Display-Currency header.
     *
     * @return list<array<string, mixed>>
     */
    public function displayCurrencies(): array
    {
        return $this->remember('currencies-display', fn (): array => $this->rows('/currencies/display', 'currencies'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentMethods(): array
    {
        return $this->remember('payment-methods', fn (): array => $this->rows('/payment_methods', 'payment_methods'));
    }

    public function flush(): void
    {
        $cache = $this->client->cache();

        foreach (['categories', 'categories-flat', 'conditions', 'shipping-regions', 'shipping-providers', 'countries', 'currencies-listing', 'currencies-display', 'payment-methods'] as $name) {
            $cache?->forget($this->cacheKey($name));
        }
    }

    /**
     * The list under $key, or — for the endpoints whose wrapper key is not
     * documented — the first list found in the response.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(string $path, string $key): array
    {
        $body = $this->client->get($path);

        if (isset($body[$key]) && is_array($body[$key])) {
            return array_values($body[$key]);
        }

        if (array_is_list($body)) {
            return $body;
        }

        foreach ($body as $name => $value) {
            if ($name !== '_links' && is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param  Closure(): list<array<string, mixed>>  $resolve
     * @return list<array<string, mixed>>
     */
    protected function remember(string $name, Closure $resolve): array
    {
        $cache = $this->client->cache();
        $ttl = $this->client->cacheTtl();

        if ($cache === null || $ttl <= 0) {
            return $resolve();
        }

        $key = $this->cacheKey($name);
        $cached = $cache->get($key);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $rows = $resolve();

        // An empty answer is never cached: it is far likelier a bad moment
        // than a Reverb with no categories, and caching it would break
        // every listing for a day.
        if ($rows !== []) {
            $cache->put($key, $rows, $ttl);
        }

        return $rows;
    }

    protected function cacheKey(string $name): string
    {
        return "reverb-marketplace:{$this->client->environment()->value}:{$name}";
    }
}
