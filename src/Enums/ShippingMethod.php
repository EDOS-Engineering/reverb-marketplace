<?php

namespace Edos\ReverbMarketplace\Enums;

enum ShippingMethod: string
{
    /** Ground shipping; mark shipped with tracking within 72 hours. */
    case Shipped = 'shipped';

    /** The buyer collects from the seller. */
    case Local = 'local';

    /** Two-day shipping; mark shipped within 24 hours. */
    case ExpeditedShipping = 'expedited_shipping';
}
