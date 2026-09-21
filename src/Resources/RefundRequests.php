<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\Enums\RefundReason;
use Edos\ReverbMarketplace\Enums\RefundRequestState;
use Edos\ReverbMarketplace\Page;
use Edos\ReverbMarketplace\Support\Money;

/**
 * Refund requests. A buyer's request arrives as pending_seller_response;
 * the seller conditionally approves it (refund once the item is back),
 * approves it (money moves now, final) or denies it (final). An order can
 * carry several requests until it is fully refunded.
 *
 * Amounts must be in the shop's own currency, not the buyer's.
 */
class RefundRequests extends ApiResource
{
    /**
     * Active requests by default. Filters: state (one or a list),
     * order_number, created_start_date, created_end_date,
     * updated_start_date, updated_end_date, page, per_page.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): Page
    {
        return $this->client->paginate('/my/refund_requests/selling', 'refund_requests', $filters);
    }

    public function forOrder(string|int $orderNumber): Page
    {
        return $this->list(['order_number' => $orderNumber]);
    }

    /**
     * @return array<string, mixed>
     */
    public function respond(
        string|int $refundRequestId,
        RefundRequestState|string $state,
        ?string $noteToBuyer = null,
        ?int $amountCents = null,
        string $currency = 'USD',
    ): array {
        return $this->client->put("/my/refund_requests/selling/{$refundRequestId}", array_filter([
            'state' => $state instanceof RefundRequestState ? $state->value : $state,
            'note_to_buyer' => $noteToBuyer,
            'refund_amount' => $amountCents === null ? null : Money::fromCents($amountCents, $currency),
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(string|int $refundRequestId, ?string $noteToBuyer = null, ?int $amountCents = null, string $currency = 'USD'): array
    {
        return $this->respond($refundRequestId, RefundRequestState::Approved, $noteToBuyer, $amountCents, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function conditionallyApprove(string|int $refundRequestId, ?string $noteToBuyer = null, ?int $amountCents = null, string $currency = 'USD'): array
    {
        return $this->respond($refundRequestId, RefundRequestState::ConditionallyApproved, $noteToBuyer, $amountCents, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function deny(string|int $refundRequestId, ?string $noteToBuyer = null): array
    {
        return $this->respond($refundRequestId, RefundRequestState::Denied, $noteToBuyer);
    }

    /**
     * Open a refund as the seller. With state approved the money moves at
     * once; conditionally_approved leaves it to be approved later.
     *
     * @return array<string, mixed>
     */
    public function create(
        string|int $orderNumber,
        RefundReason|string $reason,
        int $amountCents,
        RefundRequestState|string $state = RefundRequestState::ConditionallyApproved,
        string $currency = 'USD',
        ?string $noteToBuyer = null,
    ): array {
        return $this->client->post("/my/orders/selling/{$orderNumber}/refund_requests", array_filter([
            'state' => $state instanceof RefundRequestState ? $state->value : $state,
            'reason' => $reason instanceof RefundReason ? $reason->value : $reason,
            'refund_amount' => Money::fromCents($amountCents, $currency),
            'note_to_buyer' => $noteToBuyer,
        ], fn (mixed $value): bool => $value !== null));
    }
}
