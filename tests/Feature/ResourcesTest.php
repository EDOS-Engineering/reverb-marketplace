<?php

namespace Edos\ReverbMarketplace\Tests\Feature;

use Edos\ReverbMarketplace\Enums\EndReason;
use Edos\ReverbMarketplace\Enums\RefundReason;
use Edos\ReverbMarketplace\Enums\RefundRequestState;
use Edos\ReverbMarketplace\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ResourcesTest extends TestCase
{
    protected const BASE = 'https://sandbox.reverb.com/api';

    protected function fakeEmptyResponses(): void
    {
        Http::fake(['*' => Http::response([])]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function assertRequest(string $method, string $url, ?array $data = null): void
    {
        Http::assertSent(fn (Request $request): bool => $request->method() === $method
            && $request->url() === self::BASE.$url
            && ($data === null || $request->data() === $data));
    }

    public function test_find_by_sku_searches_every_state_so_a_draft_is_not_created_twice(): void
    {
        Http::fake(['*' => Http::response(['listings' => [['id' => 9, 'sku' => 'TF-100']]])]);

        $listing = $this->client()->listings()->findBySku('TF-100');

        $this->assertSame(9, $listing['id']);
        $this->assertRequest('GET', '/my/listings?sku=TF-100&state=all');
    }

    public function test_find_by_sku_is_null_when_reverb_has_no_such_listing(): void
    {
        Http::fake(['*' => Http::response(['listings' => []])]);

        $this->assertNull($this->client()->listings()->findBySku('MISSING'));
    }

    public function test_listing_writes_use_the_documented_endpoints(): void
    {
        $this->fakeEmptyResponses();

        $listings = $this->client()->listings();

        $listings->create(['title' => 'Strat', 'publish' => false]);
        $this->assertRequest('POST', '/listings', ['title' => 'Strat', 'publish' => false]);

        $listings->update(7, ['inventory' => 0]);
        $this->assertRequest('PUT', '/listings/7', ['inventory' => 0]);

        $listings->publish(7);
        $this->assertRequest('PUT', '/listings/7', ['publish' => true]);

        $listings->end(7, EndReason::ReverbSale);
        $this->assertRequest('PUT', '/my/listings/7/state/end', ['reason' => 'reverb_sale']);

        $listings->delete(7);
        $this->assertRequest('DELETE', '/listings/7');

        $listings->deleteImage(7, 55);
        $this->assertRequest('DELETE', '/listings/7/images/55');

        $listings->reorderPhotos(7, ['https://cdn.test/b.jpg', 'https://cdn.test/a.jpg']);
        $this->assertRequest('PUT', '/listings/7', [
            'photos' => ['https://cdn.test/b.jpg', 'https://cdn.test/a.jpg'],
            'photo_upload_method' => 'override_position',
        ]);
    }

    public function test_orders_are_polled_by_update_date_and_shipped_with_tracking(): void
    {
        $this->fakeEmptyResponses();

        $orders = $this->client()->orders();

        $orders->selling(['updated_start_date' => Carbon::parse('2026-09-21T00:00:00Z'), 'per_page' => 50]);
        $this->assertRequest('GET', '/my/orders/selling/all?updated_start_date=2026-09-21T00%3A00%3A00%2B00%3A00&per_page=50');

        $orders->ship('1234', 'UPS', '1Z999', notifyBuyer: false);
        $this->assertRequest('POST', '/my/orders/selling/1234/ship', [
            'provider' => 'UPS', 'tracking_number' => '1Z999', 'send_notification' => false,
        ]);

        $orders->markPickedUp('1234');
        $this->assertRequest('POST', '/my/orders/selling/1234/mark_picked_up');

        $orders->awaitingShipment();
        $this->assertRequest('GET', '/my/orders/selling/awaiting_shipment');
    }

    public function test_refund_requests_filter_by_a_list_of_states_and_send_money_as_decimals(): void
    {
        $this->fakeEmptyResponses();

        $refunds = $this->client()->refundRequests();

        $refunds->list(['state' => [RefundRequestState::Approved, RefundRequestState::ConditionallyApproved]]);
        $this->assertRequest('GET', '/my/refund_requests/selling?state%5B%5D=approved&state%5B%5D=conditionally_approved');

        $refunds->create('290', RefundReason::SoldElsewhere, 1000);
        $this->assertRequest('POST', '/my/orders/selling/290/refund_requests', [
            'state' => 'conditionally_approved',
            'reason' => 'sold_elsewhere',
            'refund_amount' => ['amount' => '10.00', 'currency' => 'USD'],
        ]);

        $refunds->conditionallyApprove(47, 'Send it back first.');
        $this->assertRequest('PUT', '/my/refund_requests/selling/47', [
            'state' => 'conditionally_approved', 'note_to_buyer' => 'Send it back first.',
        ]);

        $refunds->deny(47);
        $this->assertRequest('PUT', '/my/refund_requests/selling/47', ['state' => 'denied']);
    }

    public function test_offers_can_be_accepted_declined_and_countered(): void
    {
        $this->fakeEmptyResponses();

        $negotiations = $this->client()->negotiations();

        $negotiations->accept(5);
        $this->assertRequest('POST', '/my/negotiations/5/accept');

        $negotiations->decline(5);
        $this->assertRequest('POST', '/my/negotiations/5/decline');

        $negotiations->counter(5, 5000, 1000);
        $this->assertRequest('POST', '/my/negotiations/5/counter', [
            'price' => ['amount' => '50.00', 'currency' => 'USD'],
            'shipping_price' => ['amount' => '10.00', 'currency' => 'USD'],
        ]);
    }

    public function test_active_offers_are_read_from_the_listings_key_reverb_wraps_them_in(): void
    {
        Http::fake(['*' => Http::response(['total' => 1, 'listings' => [['id' => 7]]])]);

        $this->assertSame(7, $this->client()->negotiations()->active()->first()['id']);
    }

    public function test_sale_membership_is_sent_as_string_ids_in_the_body_of_both_verbs(): void
    {
        $this->fakeEmptyResponses();

        $sales = $this->client()->sales();

        $sales->addListings(3, [21363, '24524']);
        $this->assertRequest('POST', '/sales/3/listings', ['listing_ids' => ['21363', '24524']]);

        $sales->removeListings(3, [21363]);
        $this->assertRequest('DELETE', '/sales/3/listings', ['listing_ids' => ['21363']]);
    }

    public function test_more_than_twenty_five_listings_in_one_sale_call_is_refused_locally(): void
    {
        $this->fakeEmptyResponses();

        $this->expectException(InvalidArgumentException::class);

        $this->client()->sales()->addListings(3, range(1, 26));
    }

    public function test_bumps_direct_offers_messages_feedback_payouts_and_vacation(): void
    {
        $this->fakeEmptyResponses();

        $client = $this->client();

        $client->bumps()->bid([15191342], 0.035);
        $this->assertRequest('PUT', '/bump/v2/bids', ['products' => [15191342], 'bid' => 0.035]);

        $client->directOffers()->assign(7, 10);
        $this->assertRequest('POST', '/listings/7/auto_offer', ['offer_percentage' => 10]);

        $client->conversations()->list(unreadOnly: true);
        $this->assertRequest('GET', '/my/conversations?unread_only=true');

        $client->conversations()->reply(88, 'It ships Monday.');
        $this->assertRequest('POST', '/my/conversations/88/messages', ['body' => 'It ships Monday.']);

        $client->feedback()->leaveForBuyer('1234', 'Great buyer.', 5);
        $this->assertRequest('POST', '/orders/1234/feedback/buyer', ['message' => 'Great buyer.', 'rating' => 5]);

        $client->payouts()->lineItems(54, ['per_page' => 5]);
        $this->assertRequest('GET', '/my/payouts/54/line_items?per_page=5');

        $client->payments()->forOrder('1234');
        $this->assertRequest('GET', '/my/payments/selling?order_id=1234');

        $client->shop()->disableVacation();
        $this->assertRequest('DELETE', '/shop/vacation');
    }

    public function test_a_feedback_rating_outside_one_to_five_is_refused_locally(): void
    {
        $this->fakeEmptyResponses();

        $this->expectException(InvalidArgumentException::class);

        $this->client()->feedback()->leaveForBuyer('1234', 'Hm.', 6);
    }

    public function test_shipping_profiles_are_read_from_the_shop(): void
    {
        Http::fake(['*' => Http::response(['name' => 'The Gear Barn', 'shipping_profiles' => [['id' => '456', 'name' => 'Guitars']]])]);

        $this->assertSame([['id' => '456', 'name' => 'Guitars']], $this->client()->shop()->shippingProfiles());
    }

    public function test_a_webhook_registration_is_recognised_by_url_and_topic(): void
    {
        Http::fake(['*' => Http::response(['registrations' => [
            ['url' => 'https://shop.test/hook?token=abc', 'topic' => 'orders/paid'],
        ]])]);

        $webhooks = $this->client()->webhooks();

        $this->assertTrue($webhooks->isRegistered('https://shop.test/hook?token=abc'));
        $this->assertFalse($webhooks->isRegistered('https://shop.test/hook?token=rotated'));
    }
}
