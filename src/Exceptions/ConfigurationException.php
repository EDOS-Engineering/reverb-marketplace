<?php

namespace Edos\ReverbMarketplace\Exceptions;

use LogicException;

/**
 * The client was asked to do something its configuration cannot support,
 * before any request was made.
 */
class ConfigurationException extends LogicException {}
