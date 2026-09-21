<?php

namespace Edos\ReverbMarketplace\Enums;

enum OrderStatus: string
{
    case Unpaid = 'unpaid';
    case PaymentPending = 'payment_pending';
    case PendingReview = 'pending_review';
    case Blocked = 'blocked';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case PickedUp = 'picked_up';
    case Received = 'received';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /**
     * The money has cleared and the item is gone: safe to decrement stock.
     * unpaid, payment_pending, pending_review and blocked all mean the
     * sale may still not happen.
     */
    public function isSold(): bool
    {
        return in_array($this, [self::Paid, self::Shipped, self::PickedUp, self::Received], true);
    }

    /**
     * The sale was undone. refunded is a transient state that becomes
     * cancelled immediately afterwards.
     */
    public function isReversed(): bool
    {
        return in_array($this, [self::Refunded, self::Cancelled], true);
    }

    public function isAwaitingPayment(): bool
    {
        return in_array($this, [self::Unpaid, self::PaymentPending, self::PendingReview, self::Blocked], true);
    }
}
