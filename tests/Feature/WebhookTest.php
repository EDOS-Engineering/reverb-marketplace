<?php

namespace Edos\ReverbMarketplace\Tests\Feature;

use Edos\ReverbMarketplace\Tests\TestCase;
use Edos\ReverbMarketplace\Webhooks\WebhookReceived;
use Illuminate\Support\Facades\Event;

class WebhookTest extends TestCase
{
    protected const PATH = '/webhooks/reverb-marketplace';

    public function test_a_delivery_with_the_right_token_is_handed_to_the_application(): void
    {
        Event::fake();

        $this->postJson(self::PATH.'?token=hook-secret', ['order_number' => 1234, 'status' => 'paid'])->assertOk();

        Event::assertDispatched(fn (WebhookReceived $event): bool => $event->orderNumber() === '1234' && $event->status() === 'paid');
    }

    public function test_a_delivery_with_a_wrong_or_missing_token_is_refused(): void
    {
        Event::fake();

        $this->postJson(self::PATH.'?token=guess', ['order_number' => 1])->assertForbidden();
        $this->postJson(self::PATH, ['order_number' => 1])->assertForbidden();

        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_every_delivery_is_refused_while_no_token_is_configured(): void
    {
        config()->set('reverb-marketplace.webhooks.token', null);

        $this->postJson(self::PATH.'?token=', ['order_number' => 1])->assertForbidden();
    }

    public function test_an_empty_delivery_is_rejected_so_reverb_retries_it(): void
    {
        Event::fake();

        $this->postJson(self::PATH.'?token=hook-secret', [])->assertStatus(400);

        Event::assertNotDispatched(WebhookReceived::class);
    }
}
