<?php

namespace Edos\ReverbMarketplace\Enums;

enum RefundRequestState: string
{
    case PendingSellerResponse = 'pending_seller_response';
    case ConditionallyApproved = 'conditionally_approved';
    case Approved = 'approved';
    case Denied = 'denied';

    /**
     * Still needs a decision. Approved and denied are final.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::PendingSellerResponse, self::ConditionallyApproved], true);
    }
}
