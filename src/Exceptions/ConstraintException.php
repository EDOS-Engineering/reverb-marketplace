<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 406: the request would violate a constraint. Reverb's guide gives deleting a published listing as the example, but that call actually answers 400.
 */
class ConstraintException extends ClientException {}
