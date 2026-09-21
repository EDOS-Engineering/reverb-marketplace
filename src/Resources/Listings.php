<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Enums\EndReason;
use Edos\ReverbMarketplace\Page;
use Illuminate\Support\LazyCollection;

/**
 * Listings: the public marketplace search and the seller's own inventory.
 *
 * A listing payload takes make, model, title, description, categories
 * ([{uuid}]), condition ({uuid}), price ({amount, currency}), photos
 * (URLs), videos ([{link}]), sku, upc, finish, year, has_inventory,
 * inventory, offers_enabled, handmade, shipping_profile_id or shipping
 * ({rates, local}), preorder_info, location and publish.
 */
class Listings extends ApiResource
{
    /**
     * Search the public marketplace. Works without a token.
     *
     * @param  array<string, mixed>  $filters  e.g. query, make, category, price_min, page, per_page
     */
    public function search(array $filters = []): Page
    {
        return $this->client->paginate('/listings', 'listings', $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string|int $listingId): array
    {
        return $this->client->get("/listings/{$listingId}");
    }

    /**
     * The seller's own listings. Reverb returns only live listings unless
     * a state is given; pass ['state' => 'all'] to include drafts and
     * ended listings.
     *
     * @param  array<string, mixed>  $filters  e.g. sku, state, query, page, per_page
     */
    public function mine(array $filters = []): Page
    {
        return $this->client->paginate('/my/listings', 'listings', $filters);
    }

    /**
     * Every one of the seller's listings, lazily, across all pages.
     *
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allMine(array $filters = ['state' => 'all']): LazyCollection
    {
        return $this->client->cursor('/my/listings', 'listings', $filters);
    }

    /**
     * The seller's listing for a SKU, in any state. This is the lookup
     * Reverb prescribes before every create: POST only when it is null,
     * otherwise PUT, or the same item ends up listed twice.
     *
     * @return array<string, mixed>|null
     */
    public function findBySku(string $sku, string $state = 'all'): ?array
    {
        return $this->mine(['sku' => $sku, 'state' => $state])->first();
    }

    /**
     * The seller's drafts. This index lags: a draft created seconds ago is
     * not in it yet, while findBySku() sees it at once (confirmed against
     * production). Use findBySku() to decide between create and update.
     *
     * @param  array<string, mixed>  $filters
     */
    public function drafts(array $filters = []): Page
    {
        return $this->client->paginate('/my/listings/drafts', 'listings', $filters);
    }

    /**
     * Create a listing. It is saved as a draft unless the payload carries
     * publish => true. Publishing needs a completed shop (billing, one
     * manual listing) and, on current accounts, multi-factor
     * authentication; without them Reverb answers 403 or keeps the draft.
     *
     * Publishing is asynchronous. On production a create with publish =>
     * true answers "We are processing your request and will send you an
     * email if any errors are found" with the listing still a draft;
     * Reverb fetches the photos first. Read the listing back later for its
     * real state, and do not treat a draft in this response as a failure.
     *
     * The response nests the listing under "listing", beside "message",
     * "errors" and "warnings".
     *
     * A listing in a used condition reads back has_inventory false and
     * inventory 0 as a draft whatever was sent: used items are one of a
     * kind (see ListingCondition::supportsInventory()).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('/listings', $payload);
    }

    /**
     * Update takes the same fields as create; send only what changed.
     * Setting inventory to 0 ends the listing. Raising it above 0 relists
     * only a Brand New, B-Stock or Mint (with inventory) listing.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string|int $listingId, array $payload): array
    {
        return $this->client->put("/listings/{$listingId}", $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(string|int $listingId): array
    {
        return $this->update($listingId, ['publish' => true]);
    }

    /**
     * End a live listing. A draft cannot be ended (422); delete it instead.
     *
     * @return array<string, mixed>
     */
    public function end(string|int $listingId, EndReason|string $reason = EndReason::NotSold): array
    {
        return $this->client->put("/my/listings/{$listingId}/state/end", [
            'reason' => $reason instanceof EndReason ? $reason->value : $reason,
        ]);
    }

    /**
     * Delete a draft. A listing that was ever published cannot be deleted
     * and answers 406.
     *
     * @return array<string, mixed>
     */
    public function delete(string|int $listingId): array
    {
        return $this->client->delete("/listings/{$listingId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function images(string|int $listingId): array
    {
        return $this->client->get("/listings/{$listingId}/images");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteImage(string|int $listingId, string|int $imageId): array
    {
        return $this->client->delete("/listings/{$listingId}/images/{$imageId}");
    }

    /**
     * Put a listing's photos in the given order. The URLs must be the
     * exact originals that were first sent to Reverb, not the
     * images.reverb.com URLs it serves them from.
     *
     * @param  list<string>  $photoUrls
     * @return array<string, mixed>
     */
    public function reorderPhotos(string|int $listingId, array $photoUrls): array
    {
        return $this->update($listingId, [
            'photos' => array_values($photoUrls),
            'photo_upload_method' => 'override_position',
        ]);
    }

    /**
     * The sales a listing belongs to. Works without a token.
     */
    public function sales(string|int $listingId): Page
    {
        return $this->client->paginate("/listings/{$listingId}/sales", 'sales');
    }
}
