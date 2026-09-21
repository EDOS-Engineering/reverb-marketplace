<?php

namespace Edos\ReverbMarketplace\Resources;

use Edos\ReverbMarketplace\ReverbClient;

abstract class ApiResource
{
    public function __construct(protected ReverbClient $client) {}
}
