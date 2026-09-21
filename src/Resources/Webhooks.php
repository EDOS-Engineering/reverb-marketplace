<?php

namespace Edos\ReverbMarketplace\Resources;

/**
 * Webhook registrations. Reverb's guides do not cover these endpoints;
 * what follows was established by calling the API (sandbox, 2026-09).
 *
 * - A registration is {url, topic} and nothing else: no custom headers,
 *   no signing secret. Deliveries are unsigned, so gate the receiving URL
 *   with a token in its query string (see Webhooks\VerifyWebhookToken).
 * - "orders/paid" is the only topic found to be valid for a seller; other
 *   plausible names answer 400 "topic does not have a valid value".
 * - Registering requires an OAuth token. A personal access token answers
 *   401 "Please log in via Oauth to create webhooks", so call
 *   $client->usingToken($oauthToken)->webhooks()->register(...).
 * - A registration belongs to one environment and one URL; changing the
 *   URL token means registering again.
 */
class Webhooks extends ApiResource
{
    public const TOPIC_ORDER_PAID = 'orders/paid';

    /**
     * @return list<array<string, mixed>>
     */
    public function registrations(): array
    {
        $body = $this->client->get('/webhooks/registrations');
        $rows = $body['registrations'] ?? $body['webhooks'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function register(string $url, string $topic = self::TOPIC_ORDER_PAID): array
    {
        return $this->client->post('/webhooks/registrations', ['url' => $url, 'topic' => $topic]);
    }

    public function isRegistered(string $url, string $topic = self::TOPIC_ORDER_PAID): bool
    {
        foreach ($this->registrations() as $registration) {
            if (($registration['url'] ?? null) === $url && ($registration['topic'] ?? null) === $topic) {
                return true;
            }
        }

        return false;
    }
}
