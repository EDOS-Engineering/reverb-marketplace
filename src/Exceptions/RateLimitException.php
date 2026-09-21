<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 429: over the rate limit. Reverb allows 2,000 reads or 600 writes a
 * minute (10,000 / 3,000 per ten minutes) and answers 429 until the window
 * that tripped has expired.
 */
class RateLimitException extends ClientException
{
    /**
     * Seconds to wait, when Reverb says. Reverb has not documented the
     * header, so null is the usual answer and callers should back off on
     * their own schedule.
     */
    public function retryAfter(): ?int
    {
        $header = $this->response?->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}
