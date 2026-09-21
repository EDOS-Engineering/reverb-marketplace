<?php

namespace Edos\ReverbMarketplace\Enums;

use Illuminate\Support\Str;

/**
 * Reverb's listing conditions, backed by the uuid the API requires.
 *
 * The uuids are the ones Reverb publishes in its Create Listings guide and
 * are the same in production and the sandbox. Which of them a shop may
 * use is per account (B-Stock and Mint with inventory are opt-in), so ask
 * Catalog::conditions() with the shop's token before relying on one.
 */
enum ListingCondition: string
{
    case NonFunctioning = 'fbf35668-96a0-4baa-bcde-ab18d6b1b329';
    case Poor = '6a9dfcad-600b-46c8-9e08-ce6e5057921e';
    case Fair = '98777886-76d0-44c8-865e-bb40e669e934';
    case Good = 'f7a3f48c-972a-44c6-b01a-0cd27488d3f6';
    case VeryGood = 'ae4d9114-1bd7-4ec5-a4ba-6653af5ac84d';
    case Excellent = 'df268ad1-c462-4ba6-b6db-e007e23922ea';
    case Mint = 'ac5b9c1e-dc78-466d-b0b3-7cf712967a48';
    case MintWithInventory = '6db7df88-293b-4017-a1c1-cdb5e599fa1a';
    case BStock = '9225283f-60c2-4413-ad18-1f5eba7a856f';
    case BrandNew = '7c3f45de-2ae0-4c81-8400-fdb6b1d74890';

    public function label(): string
    {
        return match ($this) {
            self::NonFunctioning => 'Non functioning',
            self::Poor => 'Poor',
            self::Fair => 'Fair',
            self::Good => 'Good',
            self::VeryGood => 'Very Good',
            self::Excellent => 'Excellent',
            self::Mint => 'Mint',
            self::MintWithInventory => 'Mint (with inventory)',
            self::BStock => 'B-Stock',
            self::BrandNew => 'Brand New',
        };
    }

    public function slug(): string
    {
        return Str::slug($this->label());
    }

    /**
     * Match "brand-new", "Brand New", "b-stock" and the like.
     */
    public static function fromSlug(string $slug): ?self
    {
        $slug = Str::slug($slug);

        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Only these three may hold an inventory above one, and only these
     * relist themselves when stock returns after selling out. Every other
     * condition is a one-of-a-kind used item: once it sells the listing is
     * locked as ordered and cannot be relisted, so a second unit needs a
     * listing of its own.
     */
    public function supportsInventory(): bool
    {
        return in_array($this, [self::BrandNew, self::BStock, self::MintWithInventory], true);
    }

    /**
     * The shape the listing payload expects.
     *
     * @return array{uuid: string}
     */
    public function toPayload(): array
    {
        return ['uuid' => $this->value];
    }
}
