<?php

namespace Edos\ReverbMarketplace\Console;

use Edos\ReverbMarketplace\Exceptions\ReverbException;
use Edos\ReverbMarketplace\ReverbClient;
use Illuminate\Console\Command;

/**
 * Read-only. Confirms which Reverb the application is pointed at and what
 * the token can reach, without creating or changing anything.
 */
class CheckCommand extends Command
{
    protected $signature = 'reverb-marketplace:check';

    protected $description = 'Check the Reverb marketplace credentials and environment without writing anything';

    public function handle(ReverbClient $client): int
    {
        $this->components->twoColumnDetail('Environment', $client->environment()->value);
        $this->components->twoColumnDetail('Base URL', $client->baseUrl());

        if (! $client->hasToken()) {
            $this->components->error('No token. Set REVERB_MARKETPLACE_TOKEN.');

            return self::FAILURE;
        }

        $failed = false;

        $failed = ! $this->probe('Account', function () use ($client): string {
            $account = $client->account()->get();

            return (string) ($account['email'] ?? $account['shop']['name'] ?? 'ok');
        }) || $failed;

        $failed = ! $this->probe('Shop', function () use ($client): string {
            $shop = $client->shop()->get();

            return sprintf('%s (%d shipping profiles)', $shop['name'] ?? 'unnamed', count($shop['shipping_profiles'] ?? []));
        }) || $failed;

        $failed = ! $this->probe('Conditions', fn (): string => count($client->catalog()->conditions()).' available to this shop') || $failed;

        $failed = ! $this->probe('Listings', fn (): string => $client->listings()->mine(['state' => 'all', 'per_page' => 1])->total.' in any state') || $failed;

        $failed = ! $this->probe('Webhooks', fn (): string => count($client->webhooks()->registrations()).' registered') || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  callable(): string  $check
     */
    protected function probe(string $label, callable $check): bool
    {
        try {
            $this->components->twoColumnDetail($label, $check());

            return true;
        } catch (ReverbException $e) {
            $this->components->twoColumnDetail($label, '<fg=red>'.$e->getMessage().'</>');

            return false;
        }
    }
}
