<?php

namespace Edos\ReverbMarketplace;

use BackedEnum;
use DateTimeInterface;
use Edos\ReverbMarketplace\Exceptions\ConfigurationException;
use Edos\ReverbMarketplace\Exceptions\ReverbException;
use Edos\ReverbMarketplace\Exceptions\TransportException;
use Edos\ReverbMarketplace\Resources\Account;
use Edos\ReverbMarketplace\Resources\ApiResource;
use Edos\ReverbMarketplace\Resources\Bumps;
use Edos\ReverbMarketplace\Resources\Catalog;
use Edos\ReverbMarketplace\Resources\Conversations;
use Edos\ReverbMarketplace\Resources\DirectOffers;
use Edos\ReverbMarketplace\Resources\Feedback;
use Edos\ReverbMarketplace\Resources\Listings;
use Edos\ReverbMarketplace\Resources\Negotiations;
use Edos\ReverbMarketplace\Resources\Orders;
use Edos\ReverbMarketplace\Resources\Payments;
use Edos\ReverbMarketplace\Resources\Payouts;
use Edos\ReverbMarketplace\Resources\RefundRequests;
use Edos\ReverbMarketplace\Resources\Sales;
use Edos\ReverbMarketplace\Resources\Shop;
use Edos\ReverbMarketplace\Resources\Webhooks;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Client for the Reverb.com marketplace API.
 *
 * Every documented endpoint is reachable through a resource (listings(),
 * orders(), ...). Reverb's API is wider than its documentation, so get(),
 * post(), put(), delete() and follow() are public: anything a resource
 * does not cover is still one call away, with the same headers, error
 * mapping and environment safety.
 */
class ReverbClient
{
    /**
     * Hosts a HAL link may point at. Reverb's own responses mix
     * api.reverb.com and reverb.com for the same API; a link to anywhere
     * else is refused rather than followed with the bearer token attached.
     */
    protected const LINK_HOSTS = ['api.reverb.com', 'reverb.com', 'www.reverb.com', 'sandbox.reverb.com'];

    /** @var array<class-string<ApiResource>, ApiResource> */
    protected array $resources = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Factory $http,
        protected array $config = [],
        protected ?CacheRepository $cache = null,
    ) {}

    /**
     * Build a client without a Laravel application.
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(?string $token, Environment|string $environment = Environment::Sandbox, array $config = []): self
    {
        $environment = $environment instanceof Environment ? $environment : Environment::fromConfig($environment);

        return new self(new Factory, ['token' => $token, 'environment' => $environment->value] + $config);
    }

    /**
     * A copy of this client that authenticates as someone else. Needed for
     * the operations Reverb restricts to OAuth tokens, and for apps that
     * serve more than one shop.
     */
    public function usingToken(?string $token): static
    {
        $clone = clone $this;
        $clone->config['token'] = $token;

        return $clone;
    }

    public function usingEnvironment(Environment|string $environment): static
    {
        $clone = clone $this;
        $clone->config['environment'] = $environment instanceof Environment ? $environment->value : $environment;
        $clone->config['base_url'] = null;

        return $clone;
    }

    public function __clone()
    {
        $this->resources = [];
    }

    public function environment(): Environment
    {
        return Environment::fromConfig($this->config['environment'] ?? null);
    }

    public function baseUrl(): string
    {
        return rtrim((string) (($this->config['base_url'] ?? null) ?: $this->environment()->baseUrl()), '/');
    }

    public function hasToken(): bool
    {
        return filled($this->config['token'] ?? null);
    }

    public function cache(): ?CacheRepository
    {
        return $this->cache;
    }

    public function cacheTtl(): int
    {
        return (int) ($this->config['cache_ttl'] ?? 0);
    }

    public function account(): Account
    {
        return $this->resource(Account::class);
    }

    public function bumps(): Bumps
    {
        return $this->resource(Bumps::class);
    }

    public function catalog(): Catalog
    {
        return $this->resource(Catalog::class);
    }

    public function conversations(): Conversations
    {
        return $this->resource(Conversations::class);
    }

    public function directOffers(): DirectOffers
    {
        return $this->resource(DirectOffers::class);
    }

    public function feedback(): Feedback
    {
        return $this->resource(Feedback::class);
    }

    public function listings(): Listings
    {
        return $this->resource(Listings::class);
    }

    public function negotiations(): Negotiations
    {
        return $this->resource(Negotiations::class);
    }

    public function orders(): Orders
    {
        return $this->resource(Orders::class);
    }

    public function payments(): Payments
    {
        return $this->resource(Payments::class);
    }

    public function payouts(): Payouts
    {
        return $this->resource(Payouts::class);
    }

    public function refundRequests(): RefundRequests
    {
        return $this->resource(RefundRequests::class);
    }

    public function sales(): Sales
    {
        return $this->resource(Sales::class);
    }

    public function shop(): Shop
    {
        return $this->resource(Shop::class);
    }

    public function webhooks(): Webhooks
    {
        return $this->resource(Webhooks::class);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->send('POST', $path, $query, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('PUT', $path, [], $payload);
    }

    /**
     * Reverb takes a JSON body on some DELETE calls (removing listings
     * from a sale, removing a bump), so delete() accepts one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function delete(string $path, array $payload = []): array
    {
        return $this->send('DELETE', $path, [], $payload);
    }

    /**
     * Follow a HAL link taken from a response. The link's host is checked
     * and then discarded: the request goes to this client's own base URL,
     * so a sandbox client can never be steered to production by a payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function follow(string $href, string $method = 'GET', array $payload = []): array
    {
        return $this->send(strtoupper($method), $href, [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function paginate(string $path, string $key, array $query = []): Page
    {
        return Page::fromResponse($this->get($path, $query), $key);
    }

    /**
     * Every item of a collection, fetched a page at a time as it is
     * consumed. Follows _links.next as Reverb asks; when a response names
     * more pages but carries no link, falls back to incrementing page.
     *
     * @param  array<string, mixed>  $query
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function cursor(string $path, string $key, array $query = []): LazyCollection
    {
        return LazyCollection::make(function () use ($path, $key, $query) {
            $page = $this->paginate($path, $key, $query);

            while (true) {
                yield from $page->items;

                if ($page->isEmpty() || ! $page->hasMorePages()) {
                    return;
                }

                $page = $page->nextHref !== null
                    ? Page::fromResponse($this->follow($page->nextHref), $key)
                    : $this->paginate($path, $key, ['page' => $page->currentPage + 1] + $query);
            }
        });
    }

    /**
     * Serialise a query the way Reverb's Rails backend reads it: a list
     * becomes key[]=a&key[]=b, percent-encoded (PHP's own builder would
     * write key[0]=a, which Rails parses as a hash), booleans become
     * true/false, dates become ISO 8601, and nulls are dropped.
     *
     * @param  array<string, mixed>  $query
     */
    public static function buildQuery(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode((string) $key).'%5B%5D='.rawurlencode(self::scalar($item));
                }

                continue;
            }

            $pairs[] = rawurlencode((string) $key).'='.rawurlencode(self::scalar($value));
        }

        return implode('&', $pairs);
    }

    protected static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }

    /**
     * @template T of ApiResource
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function resource(string $class): ApiResource
    {
        return $this->resources[$class] ??= new $class($this);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $query = [], array $payload = []): array
    {
        $url = $this->url($path, $query);

        // An empty payload is sent as no body at all: an encoded "[]" is a
        // JSON array, which is not what a bodiless POST (vacation mode,
        // accepting an offer) means.
        $options = $payload === [] ? [] : ['json' => $payload];

        try {
            $response = $this->pending()->send($method, $url, $options);
        } catch (ConnectionException $e) {
            throw new TransportException("Could not reach Reverb ({$method} {$url}): {$e->getMessage()}", $e);
        } catch (RequestException $e) {
            // Only reachable when the retry budget ran out on a 429.
            $response = $e->response;
        }

        return $this->decode($response);
    }

    protected function pending(): PendingRequest
    {
        $pending = $this->http
            ->withHeaders(array_filter([
                'Accept' => 'application/hal+json',
                'Content-Type' => 'application/hal+json',
                'Accept-Version' => (string) ($this->config['api_version'] ?? '3.0'),
                'X-Display-Currency' => $this->config['display_currency'] ?? null,
                'Accept-Language' => $this->config['accept_language'] ?? null,
                'X-Shipping-Region' => $this->config['shipping_region'] ?? null,
            ]))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
            ->timeout((int) ($this->config['timeout'] ?? 20));

        if ($this->hasToken()) {
            $pending = $pending->withToken((string) $this->config['token']);
        }

        $retries = (int) ($this->config['retries'] ?? 2);

        if ($retries > 0) {
            $pending = $pending->retry(
                $retries + 1,
                (int) ($this->config['retry_delay_ms'] ?? 500),
                fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->status() === 429),
                throw: false,
            );
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        if ($response->failed()) {
            throw ReverbException::fromResponse($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function url(string $path, array $query): string
    {
        $url = $this->baseUrl().'/'.ltrim($this->relativePath($path), '/');
        $queryString = self::buildQuery($query);

        if ($queryString === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$queryString;
    }

    /**
     * Reduce a path or a HAL href to the part after /api.
     */
    protected function relativePath(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            $parts = parse_url($path);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $ownHost = strtolower((string) parse_url($this->baseUrl(), PHP_URL_HOST));

            if ($host !== $ownHost && ! in_array($host, self::LINK_HOSTS, true)) {
                throw new ConfigurationException("Refusing to follow a link to [{$host}]: not a Reverb API host.");
            }

            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return (string) preg_replace('#^/?api(?=/|\?|$)#', '', $path);
    }
}
