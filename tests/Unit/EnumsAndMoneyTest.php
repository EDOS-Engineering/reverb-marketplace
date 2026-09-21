<?php

namespace Edos\ReverbMarketplace\Tests\Unit;

use Edos\ReverbMarketplace\Enums\ListingCondition;
use Edos\ReverbMarketplace\Enums\ListingState;
use Edos\ReverbMarketplace\Enums\OrderStatus;
use Edos\ReverbMarketplace\Page;
use Edos\ReverbMarketplace\Support\Money;
use PHPUnit\Framework\TestCase;

class EnumsAndMoneyTest extends TestCase
{
    public function test_cents_become_a_decimal_string_without_float_drift(): void
    {
        $this->assertSame(['amount' => '1234.05', 'currency' => 'USD'], Money::fromCents(123405));
        $this->assertSame(['amount' => '0.07', 'currency' => 'CAD'], Money::fromCents(7, 'cad'));
        $this->assertSame(['amount' => '-10.00', 'currency' => 'USD'], Money::fromCents(-1000));
    }

    public function test_cents_are_read_from_either_money_shape(): void
    {
        $this->assertSame(9500, Money::toCents(['amount' => '95.00', 'amount_cents' => 9500]));
        $this->assertSame(1999, Money::toCents(['amount' => '19.99', 'currency' => 'USD']));
        $this->assertNull(Money::toCents(null));
        $this->assertNull(Money::toCents(['currency' => 'USD']));
    }

    public function test_only_cleared_statuses_count_as_sold(): void
    {
        $sold = array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isSold());

        $this->assertSame(
            [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::PickedUp, OrderStatus::Received],
            array_values($sold),
        );
        $this->assertTrue(OrderStatus::Cancelled->isReversed());
        $this->assertTrue(OrderStatus::PendingReview->isAwaitingPayment());
        $this->assertFalse(OrderStatus::PendingReview->isSold());
    }

    public function test_a_condition_is_found_by_slug_and_knows_whether_it_can_hold_inventory(): void
    {
        $this->assertSame(ListingCondition::BStock, ListingCondition::fromSlug('b-stock'));
        $this->assertSame(ListingCondition::MintWithInventory, ListingCondition::fromSlug('Mint (with inventory)'));
        $this->assertNull(ListingCondition::fromSlug('pristine'));
        $this->assertTrue(ListingCondition::BrandNew->supportsInventory());
        $this->assertFalse(ListingCondition::Excellent->supportsInventory());
        $this->assertSame(['uuid' => 'df268ad1-c462-4ba6-b6db-e007e23922ea'], ListingCondition::Excellent->toPayload());
    }

    public function test_listing_state_is_read_from_a_bare_listing_and_from_a_create_envelope(): void
    {
        $this->assertSame(ListingState::Draft, ListingState::fromListing(['listing' => ['state' => ['slug' => 'draft', 'description' => 'Draft']]]));
        $this->assertSame(ListingState::Live, ListingState::fromListing(['state' => ['slug' => 'live']]));
        $this->assertNull(ListingState::fromListing(['state' => ['slug' => 'something_new']]));
        $this->assertSame('something_new', ListingState::slugFromListing(['state' => ['slug' => 'something_new']]));
        $this->assertNull(ListingState::fromListing(['title' => 'No state here']));
    }

    public function test_a_page_reads_reverbs_collection_envelope(): void
    {
        $page = Page::fromResponse([
            'total' => 3, 'current_page' => 1, 'total_pages' => 2,
            'orders' => [['order_number' => '1'], ['order_number' => '2']],
            '_links' => ['next' => ['href' => 'https://api.reverb.com/api/my/orders/selling/all?page=2']],
        ], 'orders');

        $this->assertCount(2, $page);
        $this->assertSame(3, $page->total);
        $this->assertTrue($page->hasMorePages());
        $this->assertSame('1', $page->first()['order_number']);
        $this->assertSame(['1', '2'], array_column(iterator_to_array($page), 'order_number'));
    }
}
