<?php

namespace Edos\ReverbMarketplace\Enums;

/**
 * Listing state slugs. Reverb does not publish the list. draft, live and
 * ended have been confirmed against the API; the rest are the values of
 * Reverb's own state filter and are unconfirmed, so treat an unknown slug
 * as possible (fromListing() returns null, slugFromListing() the raw text).
 *
 * The API reports state as an object ({"slug": "draft", "description":
 * "Draft"}), which both readers unwrap.
 */
enum ListingState: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Ended = 'ended';
    case Ordered = 'ordered';
    case Sold = 'sold';
    case Suspended = 'suspended';
    case SellerUnavailable = 'seller_unavailable';

    /**
     * Read the state out of a listing response, whether it is a bare
     * listing or a create/update envelope that nests it under "listing".
     * Null when the state is absent or not one this enum knows.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromListing(array $body): ?self
    {
        $slug = self::slugFromListing($body);

        return $slug === null ? null : self::tryFrom($slug);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function slugFromListing(array $body): ?string
    {
        $listing = is_array($body['listing'] ?? null) ? $body['listing'] : $body;
        $state = $listing['state'] ?? null;
        $slug = is_array($state) ? ($state['slug'] ?? null) : $state;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
