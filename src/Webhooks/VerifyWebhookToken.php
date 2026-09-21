<?php

namespace Edos\ReverbMarketplace\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reverb does not sign its deliveries and a registration cannot carry a
 * header, so the only thing that can tell Reverb from a stranger is a
 * secret in the registered URL: https://example.com/webhooks/...?token=…
 *
 * With no token configured every delivery is refused. An open webhook
 * that records sales is worse than one that is switched off.
 */
class VerifyWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('reverb-marketplace.webhooks.token');
        $given = $request->query('token');

        if (! is_string($expected) || $expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            abort(403);
        }

        return $next($request);
    }
}
