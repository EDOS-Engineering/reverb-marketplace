<?php

namespace Edos\ReverbMarketplace\Enums;

enum RefundReason: string
{
    case BuyerReturn = 'buyer_return';
    case LostShipment = 'lost_shipment';
    case ShippingDamage = 'shipping_damage';
    case SoldElsewhere = 'sold_elsewhere';
    case AccidentalOrder = 'accidental_order';
    case ChangeShippingAddress = 'change_shipping_address';
    case ShippingAdjustments = 'shipping_adjustments';
}
