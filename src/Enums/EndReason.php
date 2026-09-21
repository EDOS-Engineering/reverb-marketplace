<?php

namespace Edos\ReverbMarketplace\Enums;

enum EndReason: string
{
    case NotSold = 'not_sold';

    /**
     * The buyer was found on Reverb but paid in person. Reverb charges its
     * selling fee for a listing ended with this reason.
     */
    case ReverbSale = 'reverb_sale';
}
