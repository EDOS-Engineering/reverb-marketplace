<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 406: the request would violate a constraint, such as deleting a listing that is not a draft.
 */
class ConstraintException extends ClientException {}
