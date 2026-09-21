<?php

namespace Edos\ReverbMarketplace\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Base class for every failure the Reverb API reports. Reverb asks clients
 * to code against classes of status (4xx, 5xx) rather than exact codes, so
 * the subclasses below mark the few codes with a distinct meaning and
 * everything else lands on ClientException or ServerException.
 */
class ReverbException extends RuntimeException
{
    public function __construct(string $message, public readonly ?Response $response = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $message = self::describe($response);

        return match (true) {
            $status === 401 => new AuthenticationException($message, $response),
            $status === 403 => new AuthorizationException($message, $response),
            $status === 404 => new NotFoundException($message, $response),
            $status === 406 => new ConstraintException($message, $response),
            $status === 429 => new RateLimitException($message, $response),
            in_array($status, [400, 412, 422], true) => new ValidationException($message, $response),
            $status >= 500 => new ServerException($message, $response),
            default => new ClientException($message, $response),
        };
    }

    public function status(): int
    {
        return $this->response?->status() ?? 0;
    }

    /**
     * Field-by-field errors. Reverb sends a hash of field => messages on a
     * validation failure; some endpoints send a flat list instead, which is
     * returned as-is.
     *
     * @return array<int|string, mixed>
     */
    public function errors(): array
    {
        $errors = $this->response?->json('errors');

        return is_array($errors) ? $errors : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $body = $this->response?->json();

        return is_array($body) ? $body : [];
    }

    protected static function describe(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('error');

        if (! is_string($message) || $message === '') {
            $message = mb_substr(trim(strip_tags($response->body())), 0, 300) ?: 'No response body';
        }

        return "Reverb API responded {$response->status()}: {$message}";
    }
}
