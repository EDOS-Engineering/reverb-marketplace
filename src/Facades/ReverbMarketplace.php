<?php

namespace Edos\ReverbMarketplace\Facades;

use Edos\ReverbMarketplace\ReverbClient;
use Illuminate\Support\Facades\Facade;

/**
 * Named ReverbMarketplace rather than Reverb so it cannot be mistaken for,
 * or collide with, Laravel Reverb.
 *
 * @method static \Edos\ReverbMarketplace\Resources\Account account()
 * @method static \Edos\ReverbMarketplace\Resources\Bumps bumps()
 * @method static \Edos\ReverbMarketplace\Resources\Catalog catalog()
 * @method static \Edos\ReverbMarketplace\Resources\Conversations conversations()
 * @method static \Edos\ReverbMarketplace\Resources\DirectOffers directOffers()
 * @method static \Edos\ReverbMarketplace\Resources\Feedback feedback()
 * @method static \Edos\ReverbMarketplace\Resources\Listings listings()
 * @method static \Edos\ReverbMarketplace\Resources\Negotiations negotiations()
 * @method static \Edos\ReverbMarketplace\Resources\Orders orders()
 * @method static \Edos\ReverbMarketplace\Resources\Payments payments()
 * @method static \Edos\ReverbMarketplace\Resources\Payouts payouts()
 * @method static \Edos\ReverbMarketplace\Resources\RefundRequests refundRequests()
 * @method static \Edos\ReverbMarketplace\Resources\Sales sales()
 * @method static \Edos\ReverbMarketplace\Resources\Shop shop()
 * @method static \Edos\ReverbMarketplace\Resources\Webhooks webhooks()
 * @method static \Edos\ReverbMarketplace\ReverbClient usingToken(?string $token)
 * @method static \Edos\ReverbMarketplace\ReverbClient usingEnvironment(\Edos\ReverbMarketplace\Environment|string $environment)
 * @method static \Edos\ReverbMarketplace\Environment environment()
 * @method static string baseUrl()
 * @method static bool hasToken()
 * @method static array get(string $path, array $query = [])
 * @method static array post(string $path, array $payload = [], array $query = [])
 * @method static array put(string $path, array $payload = [])
 * @method static array delete(string $path, array $payload = [])
 * @method static array follow(string $href, string $method = 'GET', array $payload = [])
 * @method static \Edos\ReverbMarketplace\Page paginate(string $path, string $key, array $query = [])
 * @method static \Illuminate\Support\LazyCollection cursor(string $path, string $key, array $query = [])
 *
 * @see ReverbClient
 */
class ReverbMarketplace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReverbClient::class;
    }
}
