<?php

namespace Edos\ReverbMarketplace\Tests\Feature;

use Edos\ReverbMarketplace\Environment;
use Edos\ReverbMarketplace\Exceptions\AuthenticationException;
use Edos\ReverbMarketplace\Exceptions\AuthorizationException;
use Edos\ReverbMarketplace\Exceptions\ConfigurationException;
use Edos\ReverbMarketplace\Exceptions\ConstraintException;
use Edos\ReverbMarketplace\Exceptions\NotFoundException;
use Edos\ReverbMarketplace\Exceptions\RateLimitException;
use Edos\ReverbMarketplace\Exceptions\ServerException;
use Edos\ReverbMarketplace\Exceptions\TransportException;
use Edos\ReverbMarketplace\Exceptions\ValidationException;
use Edos\ReverbMarketplace\Facades\ReverbMarketplace;
use Edos\ReverbMarketplace\ReverbClient;
use Edos\ReverbMarketplace\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class ClientTest extends TestCase
{
    public function test_it_sends_the_headers_reverb_requires_and_the_bearer_token(): void
    {
        Http::fake(['*' => Http::response(['email' => 'seller@example.com'])]);

        $this->client()->account()->get();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sandbox.reverb.com/api/my/account'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Accept', 'application/hal+json')
            && $request->hasHeader('Content-Type', 'application/hal+json')
            && $request->hasHeader('Accept-Version', '3.0')
            && $request->hasHeader('X-Display-Currency', 'USD'));
    }

    public function test_an_unrecognised_environment_resolves_to_the_sandbox(): void
    {
        config()->set('reverb-marketplace.environment', 'prod');
        $this->app->forgetInstance(ReverbClient::class);

        $this->assertSame(Environment::Sandbox, $this->client()->environment());
        $this->assertSame('https://sandbox.reverb.com/api', $this->client()->baseUrl());
    }

    public function test_production_is_reached_only_when_named_exactly(): void
    {
        Http::fake(['*' => Http::response([])]);

        $production = $this->client()->usingEnvironment(Environment::Production);
        $production->shop()->get();

        $this->assertTrue($production->environment()->isProduction());
        $this->assertSame(Environment::Sandbox, $this->client()->environment());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.reverb.com/api/shop');
    }

    public function test_using_token_returns_a_copy_and_leaves_the_shared_client_alone(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->usingToken('oauth-token')->webhooks()->register('https://example.com/hook?token=abc');
        $this->client()->account()->get();

        Http::assertSentInOrder([
            fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer oauth-token'),
            fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'),
        ]);
    }

    public function test_a_public_endpoint_is_called_without_an_authorization_header_when_there_is_no_token(): void
    {
        Http::fake(['*' => Http::response(['listings' => []])]);

        $this->client()->usingToken(null)->listings()->search(['query' => 'jazzmaster']);

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization')
            && $request->url() === 'https://sandbox.reverb.com/api/listings?query=jazzmaster');
    }

    public function test_the_facade_resolves_the_client(): void
    {
        Http::fake(['*' => Http::response(['name' => 'The Gear Barn'])]);

        $this->assertSame('The Gear Barn', ReverbMarketplace::shop()->get()['name']);
    }

    public function test_the_query_is_written_the_way_rails_reads_it(): void
    {
        $query = ReverbClient::buildQuery([
            'state' => ['approved', 'denied'],
            'unread_only' => true,
            'updated_start_date' => Carbon::parse('2026-09-21T10:00:00+00:00'),
            'sku' => 'A B/1',
            'skipped' => null,
        ]);

        $this->assertSame(
            'state%5B%5D=approved&state%5B%5D=denied&unread_only=true&updated_start_date=2026-09-21T10%3A00%3A00%2B00%3A00&sku=A%20B%2F1',
            $query,
        );
    }

    public function test_a_post_without_a_payload_sends_no_body(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->shop()->enableVacation();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->body() === '');
    }

    public function test_a_delete_can_carry_a_json_body(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->bumps()->remove([101, 102]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->data() === ['products' => [101, 102]]);
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    #[DataProvider('failures')]
    public function test_a_failed_response_becomes_the_matching_exception(int $status, string $exception): void
    {
        config()->set('reverb-marketplace.retries', 0);
        $this->app->forgetInstance(ReverbClient::class);
        Http::fake(['*' => Http::response(['message' => 'Nope.'], $status)]);

        $this->expectException($exception);
        $this->expectExceptionMessage("Reverb API responded {$status}: Nope.");

        $this->client()->account()->get();
    }

    /**
     * @return array<string, array{int, class-string<\Throwable>}>
     */
    public static function failures(): array
    {
        return [
            'unauthenticated' => [401, AuthenticationException::class],
            'forbidden' => [403, AuthorizationException::class],
            'missing' => [404, NotFoundException::class],
            'constraint' => [406, ConstraintException::class],
            'bad request' => [400, ValidationException::class],
            'precondition' => [412, ValidationException::class],
            'unprocessable' => [422, ValidationException::class],
            'rate limited' => [429, RateLimitException::class],
            'server error' => [503, ServerException::class],
        ];
    }

    public function test_a_validation_failure_exposes_the_field_errors(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'Validation failed.',
            'errors' => ['price' => ['must be greater than 0']],
        ], 412)]);

        try {
            $this->client()->listings()->create(['title' => 'Strat']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['price' => ['must be greater than 0']], $e->errors());
            $this->assertSame(412, $e->status());
        }
    }

    public function test_a_rate_limited_request_is_retried_and_then_succeeds(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'Slow down.'], 429)
            ->push(['email' => 'seller@example.com']);

        $this->assertSame('seller@example.com', $this->client()->account()->get()['email']);
        Http::assertSentCount(2);
    }

    public function test_a_rate_limit_that_outlasts_the_retries_reports_the_wait(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Slow down.'], 429, ['Retry-After' => '30'])]);

        try {
            $this->client()->account()->get();
            $this->fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter());
            Http::assertSentCount(3);
        }
    }

    public function test_a_server_error_on_a_write_is_not_retried(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Boom.'], 500)]);

        try {
            $this->client()->listings()->create(['title' => 'Strat']);
            $this->fail('Expected a ServerException.');
        } catch (ServerException) {
            Http::assertSentCount(1);
        }
    }

    public function test_a_connection_failure_becomes_a_transport_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(TransportException::class);

        $this->client()->account()->get();
    }

    public function test_a_followed_link_is_sent_to_this_clients_own_environment(): void
    {
        Http::fake(['*' => Http::response(['order_number' => '2'])]);

        $this->client()->follow('https://reverb.com/api/my/orders/selling/2?foo=bar');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sandbox.reverb.com/api/my/orders/selling/2?foo=bar');
    }

    public function test_a_link_to_a_foreign_host_is_refused_before_any_request(): void
    {
        Http::fake();

        try {
            $this->client()->follow('https://evil.example/api/my/account');
            $this->fail('Expected a ConfigurationException.');
        } catch (ConfigurationException) {
            Http::assertNothingSent();
        }
    }

    public function test_a_cursor_follows_next_links_until_they_stop(): void
    {
        Http::fake([
            'sandbox.reverb.com/api/my/orders/selling/all?per_page=1' => Http::response([
                'orders' => [['order_number' => '1']],
                'current_page' => 1,
                'total_pages' => 2,
                '_links' => ['next' => ['href' => 'https://api.reverb.com/api/my/orders/selling/all?page=2&per_page=1']],
            ]),
            'sandbox.reverb.com/api/my/orders/selling/all?page=2&per_page=1' => Http::response([
                'orders' => [['order_number' => '2']],
                'current_page' => 2,
                'total_pages' => 2,
                '_links' => [],
            ]),
        ]);

        $numbers = $this->client()->orders()->allSelling(['per_page' => 1])->pluck('order_number')->all();

        $this->assertSame(['1', '2'], $numbers);
    }

    public function test_a_cursor_counts_pages_itself_when_reverb_sends_no_next_link(): void
    {
        Http::fake([
            'sandbox.reverb.com/api/my/listings?state=all' => Http::response([
                'listings' => [['id' => 1]], 'current_page' => 1, 'total_pages' => 2,
            ]),
            'sandbox.reverb.com/api/my/listings?page=2&state=all' => Http::response([
                'listings' => [['id' => 2]], 'current_page' => 2, 'total_pages' => 2,
            ]),
        ]);

        $this->assertSame([1, 2], $this->client()->listings()->allMine()->pluck('id')->all());
    }

    public function test_a_client_can_be_built_without_laravel(): void
    {
        $client = ReverbClient::make('standalone-token', 'production');

        $this->assertSame('https://api.reverb.com/api', $client->baseUrl());
        $this->assertTrue($client->hasToken());
    }
}
