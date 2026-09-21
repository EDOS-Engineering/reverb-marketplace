<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 400, 412 or 422: parameters missing or invalid. See errors() for the field breakdown.
 */
class ValidationException extends ClientException {}
