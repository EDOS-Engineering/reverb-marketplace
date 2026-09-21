# Reverb Marketplace for PHP and Laravel

A client for the [Reverb.com](https://reverb.com) marketplace API: listings,
orders, shipping, offers, refunds, payouts, messages and webhooks.

> This is **not** [Laravel Reverb](https://reverb.laravel.com), the WebSocket
> server. This package talks to reverb.com, where people buy and sell music
> gear. Everything here is named `reverb-marketplace` / `ReverbMarketplace`,
> and its environment variables are prefixed `REVERB_MARKETPLACE_`, so the two
> can live in one application.

Not affiliated with or endorsed by Reverb.

## Requirements

PHP 8.2+. Laravel 11, 12 or 13 for the service provider, facade, webhook route
and Artisan command; the client itself also runs without Laravel.

## Install

```bash
composer require edos-engineering/reverb-marketplace
```

```dotenv
REVERB_MARKETPLACE_TOKEN=your-personal-access-token
REVERB_MARKETPLACE_ENVIRONMENT=sandbox
```

Create the token on Reverb under **My Profile → API & Integrations**. For a
shop integration Reverb recommends the scopes `public`, `read_listings`,
`write_listings`, `read_orders` and `write_orders`; add `read_payouts`,
`read_messages`/`write_messages`, `read_offers`/`write_offers` and
`read_feedback`/`write_feedback` for those resources. Tokens do not expire.

Then check what the application can reach. This writes nothing:

```bash
php artisan reverb-marketplace:check
```

To publish the config file:

```bash
php artisan vendor:publish --tag=reverb-marketplace-config
```

## Environments

| `REVERB_MARKETPLACE_ENVIRONMENT` | Base URL                         |
| :------------------------------- | :------------------------------- |
| `production`                     | `https://api.reverb.com/api`     |
| `sandbox` (default)              | `https://sandbox.reverb.com/api` |

Anything other than the exact word `production` — unset, a typo — resolves to
the sandbox, so a misconfiguration cannot write to a live shop. Tokens are
bound to one environment: a production token is refused by the sandbox and the
reverse. Sandbox accounts are separate accounts, created at
<https://sandbox.reverb.com/signup>.

One application can address both:

```php
use Edos\ReverbMarketplace\Environment;
use Edos\ReverbMarketplace\Facades\ReverbMarketplace;

$live = ReverbMarketplace::usingEnvironment(Environment::Production)->usingToken($productionToken);
```

`usingEnvironment()` and `usingToken()` return copies; the shared client is
never changed.

## Usage

Resolve `Edos\ReverbMarketplace\ReverbClient` from the container, or use the
facade. Responses are the decoded HAL+JSON arrays Reverb sent. Collections are
a `Page` (iterable, with `total`, `currentPage`, `totalPages`), and the `all…`
methods return a `LazyCollection` that fetches pages as it is consumed.

### Listings

Reverb's rule for a sync: look the item up by SKU first, update it if it
exists, create it only if it does not. Skipping the lookup is how an item ends
up listed twice.

```php
use Edos\ReverbMarketplace\Enums\ListingCondition;
use Edos\ReverbMarketplace\Support\Money;

$listings = ReverbMarketplace::listings();

$payload = [
    'make' => 'Fender',
    'model' => 'Stratocaster',
    'title' => '1964 Fender Stratocaster, Sunburst',
    'description' => 'All original.',
    'categories' => [['uuid' => $categoryUuid]],   // ReverbMarketplace::catalog()->flatCategories()
    'condition' => ListingCondition::Excellent->toPayload(),
    'price' => Money::fromCents(2_450_000),
    'sku' => 'TF-1042',
    'photos' => ['https://example.com/strat-1.jpg'],
    'shipping_profile_id' => $profileId,           // ReverbMarketplace::shop()->shippingProfiles()
    'has_inventory' => true,
    'inventory' => 1,
    'offers_enabled' => true,
    'publish' => true,
];

$existing = $listings->findBySku('TF-1042');       // any state, drafts included

$existing
    ? $listings->update($existing['id'], $payload)
    : $listings->create($payload);

$listings->end($id);                                // live → ended
$listings->delete($id);                             // drafts only
```

Things Reverb's behaviour makes worth knowing:

- A create is a **draft** unless `publish` is true. Publishing needs a shop
  with billing set up and one listing made by hand, and current accounts also
  need multi-factor authentication; otherwise Reverb answers 403 or keeps the
  draft. Read the state back with `ListingState::fromListing($response)`.
- A draft cannot be ended (422). A published listing cannot be deleted (406).
- Setting `inventory` to 0 ends a listing.
- Only **Brand New**, **B-Stock** and **Mint (with inventory)** can hold more
  than one unit and relist themselves when stock returns
  (`ListingCondition::supportsInventory()`). Any other condition is a
  one-of-a-kind used item: once it sells, the listing is locked and cannot be
  relisted.
- "Middle" categories are in the category list but are refused with a 400.
  List under a leaf category.
- Prefer `shipping_profile_id` to per-listing `shipping.rates`. Profiles can
  only be created on Reverb's website.

### Orders

```php
use Edos\ReverbMarketplace\Enums\OrderStatus;

$orders = ReverbMarketplace::orders()->allSelling(['updated_start_date' => $lastPolledAt]);

foreach ($orders as $order) {
    $status = OrderStatus::tryFrom($order['status']);

    if ($status?->isSold()) {
        // paid, shipped, picked_up or received: the money has cleared
    } elseif ($status?->isReversed()) {
        // refunded or cancelled
    }
}

ReverbMarketplace::orders()->ship($orderNumber, 'UPS', '1Z999AA10123456784');
```

Poll on `updated_start_date`, not `created_start_date`: an order placed before
the window and paid inside it is otherwise missed. One order is one listing;
items bought together share `order_bundle_id`.

### Everything else

| Resource             | Covers                                                           |
| :------------------- | :--------------------------------------------------------------- |
| `account()`          | Account details, badge counts                                    |
| `shop()`             | Shop, shipping profiles, vacation mode                           |
| `catalog()`          | Categories, conditions, shipping regions and providers, countries, currencies, payment methods (cached) |
| `listings()`         | Search, find, create, update, publish, end, delete, images, drafts |
| `orders()`           | Selling and buying orders, ship, mark picked up                  |
| `refundRequests()`   | List, approve, conditionally approve, deny, create               |
| `negotiations()`     | Offers: list, accept, decline, counter                           |
| `directOffers()`     | Automatic offers to watchers                                     |
| `sales()`            | Sale membership (Preferred Sellers only for the listing calls)   |
| `bumps()`            | Bump stats, bid, remove                                          |
| `conversations()`    | Messages: list, read, reply                                      |
| `feedback()`         | Received, sent, leave for a buyer                                |
| `payments()`         | Payments per order                                               |
| `payouts()`          | Payouts and their line items                                     |
| `webhooks()`         | Registrations                                                    |

Reverb's API is wider than its documentation. Anything not wrapped is still one
call away with the same headers, error handling and environment safety:

```php
ReverbMarketplace::get('/priceguide', ['query' => 'jazzmaster']);
ReverbMarketplace::follow($order['_links']['packing_slip']['href']);
```

`follow()` checks that a link points at a Reverb host and then sends the
request to the client's **own** environment, so a payload can neither leak the
token to another host nor steer a sandbox client to production.

## Errors

Every failure is an `Edos\ReverbMarketplace\Exceptions\ReverbException` with
`status()`, `errors()`, `body()` and the raw `response`.

| Exception                 | When                                                         |
| :------------------------ | :----------------------------------------------------------- |
| `AuthenticationException` | 401. Bad token, wrong environment, or an OAuth-only operation |
| `AuthorizationException`  | 403. Missing scope, or a feature the account does not have    |
| `NotFoundException`       | 404                                                           |
| `ConstraintException`     | 406. For example, deleting a published listing                |
| `ValidationException`     | 400, 412, 422. `errors()` has the field breakdown             |
| `RateLimitException`      | 429, after retries. `retryAfter()` when Reverb says           |
| `ClientException`         | Any other 4xx                                                 |
| `ServerException`         | 5xx                                                           |
| `TransportException`      | No response at all                                            |

Connection failures and 429s are retried (`retries`, `retry_delay_ms`). A 5xx
is never retried, and a `TransportException` on a write is ambiguous: the
create may have happened. Look the listing up by SKU before repeating it.

Reverb's limits are 2,000 reads or 600 writes a minute (10,000 / 3,000 per ten
minutes). Queue bulk syncs rather than looping.

## Webhooks

Reverb's guides do not document webhooks. What this package knows comes from
calling the API:

- A registration is a URL and a topic. **No headers, no signing secret** —
  deliveries are unsigned.
- `orders/paid` is the only topic found to be valid for a seller account.
- Registering needs an **OAuth** token. A personal access token gets 401
  "Please log in via Oauth to create webhooks".

Since deliveries are unsigned, the package gates its route with a secret in the
URL and refuses everything while none is configured:

```dotenv
REVERB_MARKETPLACE_WEBHOOK_TOKEN=a-long-random-string
```

Register `https://your-app.test/webhooks/reverb-marketplace?token=a-long-random-string`
and listen:

```php
use Edos\ReverbMarketplace\Webhooks\WebhookReceived;

Event::listen(function (WebhookReceived $event) {
    // The payload is unsigned. Use it to learn which order changed, then
    // read the truth back before acting on any amount or status.
    $order = ReverbMarketplace::orders()->find($event->orderNumber());
});
```

Reverb retries a delivery that is not answered 2xx, so listeners must be
idempotent and slow work belongs on a queue. Keep polling
`orders()->allSelling()` as well: a webhook is the fast path, not the only one.
Set `webhooks.path` to `null` to skip the route.

## Without Laravel

```php
use Edos\ReverbMarketplace\ReverbClient;

$reverb = ReverbClient::make($token, 'production');
$reverb->listings()->mine(['state' => 'all']);
```

## Testing your application

The client uses Laravel's HTTP client, so `Http::fake()` works as usual:

```php
Http::preventStrayRequests();
Http::fake(['sandbox.reverb.com/api/my/listings*' => Http::response(['listings' => []])]);
```

## Developing

```bash
composer test
composer lint
```

The suite never touches the network.

## License

MIT.
