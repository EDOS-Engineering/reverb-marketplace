<?php

namespace Edos\ReverbMarketplace\Tests\Feature;

use Edos\ReverbMarketplace\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class CatalogTest extends TestCase
{
    public function test_reference_data_is_fetched_once_and_then_served_from_the_cache(): void
    {
        Http::fake(['*' => Http::response(['conditions' => [
            ['uuid' => '7c3f45de-2ae0-4c81-8400-fdb6b1d74890', 'display_name' => 'Brand New'],
            ['uuid' => 'ae4d9114-1bd7-4ec5-a4ba-6653af5ac84d', 'display_name' => 'Very Good'],
        ]])]);

        $catalog = $this->client()->catalog();

        $this->assertSame('ae4d9114-1bd7-4ec5-a4ba-6653af5ac84d', $catalog->conditionUuid('very-good'));
        $this->assertSame('7c3f45de-2ae0-4c81-8400-fdb6b1d74890', $catalog->conditionUuid('Brand New'));
        Http::assertSentCount(1);
    }

    public function test_a_condition_this_shop_may_not_use_resolves_to_null(): void
    {
        Http::fake(['*' => Http::response(['conditions' => [['uuid' => 'x', 'display_name' => 'Good']]])]);

        $this->assertNull($this->client()->catalog()->conditionUuid('b-stock'));
    }

    public function test_an_empty_answer_is_not_cached(): void
    {
        Http::fakeSequence()
            ->push(['categories' => []])
            ->push(['categories' => [['uuid' => 'c1', 'name' => 'Electric Guitars']]]);

        $catalog = $this->client()->catalog();

        $this->assertSame([], $catalog->flatCategories());
        $this->assertCount(1, $catalog->flatCategories());
    }

    public function test_the_cache_is_kept_apart_per_environment(): void
    {
        Http::fake([
            'sandbox.reverb.com/*' => Http::response(['categories' => [['uuid' => 'sandbox']]]),
            'api.reverb.com/*' => Http::response(['categories' => [['uuid' => 'production']]]),
        ]);

        $this->assertSame('sandbox', $this->client()->catalog()->flatCategories()[0]['uuid']);
        $this->assertSame('production', $this->client()->usingEnvironment('production')->catalog()->flatCategories()[0]['uuid']);
    }

    public function test_flush_forgets_the_cached_reference_data(): void
    {
        Http::fake(['*' => Http::response(['categories' => [['uuid' => 'c1']]])]);
        $catalog = $this->client()->catalog();

        $catalog->flatCategories();
        $catalog->flush();
        $catalog->flatCategories();

        Http::assertSentCount(2);
    }
}
