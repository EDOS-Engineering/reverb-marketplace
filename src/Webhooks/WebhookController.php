<?php

namespace Edos\ReverbMarketplace\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    /**
     * Hand the delivery to the application and acknowledge it. Do slow
     * work in a queued listener: Reverb treats a slow answer as a failure
     * and sends the delivery again.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return new JsonResponse(['message' => 'Empty payload.'], 400);
        }

        WebhookReceived::dispatch($payload);

        return new JsonResponse(['message' => 'Accepted.']);
    }
}
