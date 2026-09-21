<?php

/**
 * Live probe: exercises the listing lifecycle against a real Reverb account
 * and prints what Reverb actually answers. Development tool, not shipped.
 *
 *   REVERB_MARKETPLACE_TOKEN=... php scripts/live-probe.php --env=production --stage=drafts
 *
 * Safety rules this script holds itself to:
 *  - It only ever touches listings it created in this run (tracked by id),
 *    all carrying the SKU prefix RMTEST- and a "TEST LISTING — DO NOT BUY"
 *    title with obviously fictional Faker data.
 *  - The "drafts" stage never sends publish=true, so nothing becomes public.
 *  - The "public" stage refuses to run without --allow-public.
 *  - Whatever happens, the finally block deletes every draft it created and
 *    ends every listing it published, then sweeps the account for RMTEST-
 *    leftovers and reports them.
 *  - The token is never printed or logged.
 */

use Edos\ReverbMarketplace\Enums\ListingCondition;
use Edos\ReverbMarketplace\Enums\ListingState;
use Edos\ReverbMarketplace\Exceptions\ClientException;
use Edos\ReverbMarketplace\Exceptions\NotFoundException;
use Edos\ReverbMarketplace\Exceptions\ReverbException;
use Edos\ReverbMarketplace\ReverbClient;
use Edos\ReverbMarketplace\Support\Money;
use Faker\Factory;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['env:', 'stage:', 'allow-public', 'log:']);
$environment = $options['env'] ?? null;
$stage = $options['stage'] ?? 'drafts';
$token = getenv('REVERB_MARKETPLACE_TOKEN') ?: null;

if (! in_array($environment, ['production', 'sandbox'], true) || $token === null) {
    fwrite(STDERR, "Usage: REVERB_MARKETPLACE_TOKEN=... php scripts/live-probe.php --env=production|sandbox [--stage=drafts|public] [--allow-public] [--log=path]\n");
    exit(2);
}

if ($stage === 'public' && ! isset($options['allow-public'])) {
    fwrite(STDERR, "The public stage publishes a real, buyable listing. Re-run with --allow-public to confirm.\n");
    exit(2);
}

$client = ReverbClient::make($token, $environment, ['retries' => 0]);
$faker = Factory::create();
$log = isset($options['log']) ? fopen($options['log'], 'a') : null;
$created = [];   // listing id => 'draft' | 'public'
$failures = 0;

$record = function (string $label, mixed $data) use ($log): void {
    if ($log) {
        fwrite($log, json_encode(['at' => date('c'), 'step' => $label, 'data' => $data], JSON_UNESCAPED_SLASHES)."\n");
    }
};

$step = function (string $label, callable $run, ?string $expect = null) use (&$failures, $record): mixed {
    try {
        $result = $run();
        $record($label, $result);
        $summary = is_array($result) ? ($result['__summary'] ?? '') : (string) $result;
        $mark = $expect === null ? 'ok  ' : 'FAIL';
        $failures += $expect === null ? 0 : 1;
        printf("[%s] %-46s %s%s\n", $mark, $label, $summary, $expect === null ? '' : " (expected {$expect})");

        return $result;
    } catch (ReverbException $e) {
        $record($label, ['status' => $e->status(), 'body' => $e->body()]);
        $matched = $expect !== null && $e instanceof $expect;
        $failures += $matched ? 0 : 1;
        printf("[%s] %-46s %s %s\n", $matched ? 'ok  ' : 'FAIL', $label, (new ReflectionClass($e))->getShortName(), mb_substr($e->getMessage(), 0, 140));

        return null;
    }
};

$listingOf = fn (array $body): array => is_array($body['listing'] ?? null) ? $body['listing'] : $body;

$fakePayload = function () use ($faker, $client): array {
    $category = collect($client->catalog()->flatCategories())
        ->first(fn (array $row): bool => strcasecmp((string) ($row['name'] ?? ''), 'Picks') === 0);

    $model = ucfirst($faker->word()).' '.strtoupper($faker->bothify('??-###'));

    return array_filter([
        'make' => 'Zzyzx Testworks',
        'model' => $model,
        'title' => "TEST LISTING — DO NOT BUY — Zzyzx Testworks {$model}",
        'description' => 'This is an automated API integration test listing with fictional data. '
            .'It is not a real item and is not for sale. It will be removed within minutes. '
            .$faker->sentence(12),
        'finish' => ucfirst($faker->safeColorName()),
        'year' => (string) $faker->numberBetween(1990, 2020),
        'categories' => $category ? [['uuid' => $category['uuid']]] : null,
        'condition' => ListingCondition::Good->toPayload(),
        'price' => Money::fromCents(999_900),
        'sku' => 'RMTEST-'.strtoupper($faker->bothify('####??')),
        'has_inventory' => true,
        'inventory' => 1,
        'offers_enabled' => false,
        'shipping' => ['local' => true, 'rates' => [['region_code' => 'US_CON', 'rate' => Money::fromCents(500)]]],
        'publish' => false,
    ], fn (mixed $value): bool => $value !== null);
};

printf("Environment: %s (%s)\nStage: %s\n\n", $client->environment()->value, $client->baseUrl(), $stage);

try {
    if ($stage === 'drafts') {
        // 1. Create a draft.
        $payload = $fakePayload();
        $sku = $payload['sku'];
        $body = $step('1. create draft (publish=false)', function () use ($client, $payload, $listingOf): array {
            $body = $client->listings()->create($payload);
            $listing = $listingOf($body);

            return $body + ['__summary' => sprintf('id=%s state=%s sku=%s envelope=[%s] inventory=%s',
                $listing['id'] ?? '?', ListingState::slugFromListing($body) ?? '?', $listing['sku'] ?? '?',
                implode(',', array_diff(array_keys($body), ['listing'])), json_encode($listing['inventory'] ?? null))];
        });

        $id = $body ? (string) ($listingOf($body)['id'] ?? '') : '';

        if ($id === '') {
            throw new RuntimeException('No listing id came back; stopping before any further write.');
        }

        $created[$id] = 'draft';

        // 2. Find it by SKU: visible with state=all, invisible to the live-only default.
        $step('2a. findBySku state=all', fn (): string => 'found id='.($client->listings()->findBySku($sku)['id'] ?? 'NOT FOUND'));
        $step('2b. mine(sku) default state', fn (): string => 'total='.$client->listings()->mine(['sku' => $sku])->total.' (0 expected: drafts hidden)');
        $step('2c. drafts() contains it', fn (): string => 'present='.json_encode(collect($client->listings()->drafts(['per_page' => 50])->items)->contains(fn ($l) => (string) $l['id'] === $id)));

        // 3. Update and read back.
        $step('3a. update price + description', function () use ($client, $id, $listingOf): array {
            $body = $client->listings()->update($id, ['price' => Money::fromCents(999_800), 'description' => 'Updated by the API integration test. Not a real item.']);

            return $body + ['__summary' => 'price='.($listingOf($body)['price']['amount'] ?? '?').' state='.(ListingState::slugFromListing($body) ?? '?')];
        });
        $step('3b. read back', function () use ($client, $id): array {
            $listing = $client->listings()->find($id);

            return $listing + ['__summary' => sprintf('price=%s inventory=%s has_inventory=%s state=%s links=[%s]',
                $listing['price']['amount'] ?? '?', json_encode($listing['inventory'] ?? null), json_encode($listing['has_inventory'] ?? null),
                ListingState::slugFromListing($listing) ?? '?', implode(',', array_keys($listing['_links'] ?? [])))];
        });

        // 4. Images: add two, wait for Reverb to fetch them, reorder, delete one.
        $photos = ['https://placehold.co/1200x900/png?text=TEST+LISTING+1', 'https://placehold.co/1200x900/png?text=TEST+LISTING+2'];
        $step('4a. add two photos', fn (): array => $client->listings()->update($id, ['photos' => $photos]) + ['__summary' => 'sent']);

        $images = [];
        for ($attempt = 1; $attempt <= 8 && count($images) < 2; $attempt++) {
            sleep(5);
            $images = $client->listings()->images($id)['images'] ?? [];
        }
        $step('4b. images after fetch', fn (): string => count($images).' image(s) after '.(($attempt - 1) * 5).'s; keys=['.implode(',', array_keys($images[0] ?? [])).']');

        if (count($images) >= 2) {
            $step('4c. reorder photos', fn (): array => $client->listings()->reorderPhotos($id, array_reverse($photos)) + ['__summary' => 'sent']);
            $step('4d. delete one image', fn (): array => $client->listings()->deleteImage($id, $images[0]['id']) + ['__summary' => 'deleted image '.$images[0]['id']]);
            sleep(3);
            $step('4e. images after delete', fn (): string => count($client->listings()->images($id)['images'] ?? []).' image(s)');
        }

        // Ending a draft is documented nowhere; the sandbox said 422.
        $step('5x. end a draft (expect refusal)', fn (): array => $client->listings()->end($id) + ['__summary' => 'ENDED?!'], ClientException::class);

        // 8. Delete the draft.
        $step('8a. delete draft', fn (): array => $client->listings()->delete($id) + ['__summary' => 'deleted']);
        $step('8b. find after delete', fn (): array => $client->listings()->find($id) + ['__summary' => 'STILL THERE'], NotFoundException::class);
        $step('8c. findBySku after delete', fn (): string => 'found='.json_encode($client->listings()->findBySku($sku) !== null));
    }
} catch (Throwable $e) {
    $failures++;
    printf("\nABORTED: %s: %s\n", $e::class, $e->getMessage());
} finally {
    echo "\nCleanup\n";

    foreach ($created as $id => $kind) {
        try {
            $state = ListingState::slugFromListing($client->listings()->find($id));
        } catch (ReverbException) {
            printf("  %s already gone\n", $id);

            continue;
        }

        try {
            $state === 'draft' ? $client->listings()->delete($id) : $client->listings()->end($id);
            printf("  %s (%s) %s\n", $id, $state, $state === 'draft' ? 'deleted' : 'ended');
        } catch (ReverbException $e) {
            $failures++;
            printf("  %s (%s) COULD NOT BE CLEANED UP: %s\n", $id, $state, $e->getMessage());
        }
    }

    $leftovers = $client->listings()->allMine(['state' => 'all'])
        ->filter(fn (array $l): bool => str_starts_with((string) ($l['sku'] ?? ''), 'RMTEST-'))
        ->map(fn (array $l): string => $l['id'].':'.(ListingState::slugFromListing($l) ?? '?'))
        ->values()->all();

    printf("  RMTEST- listings left on the account: %s\n", $leftovers === [] ? 'none' : implode(', ', $leftovers));
}

exit($failures === 0 ? 0 : 1);
