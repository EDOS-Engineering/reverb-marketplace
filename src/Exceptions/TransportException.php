<?php

namespace Edos\ReverbMarketplace\Exceptions;

use Throwable;

/**
 * The request never produced a response: DNS, TLS, connect or read timeout.
 * On a write this is ambiguous — Reverb may have applied the change — so
 * look the resource up (by SKU, for a listing) before repeating it.
 */
class TransportException extends ReverbException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, null, $previous);
    }
}
