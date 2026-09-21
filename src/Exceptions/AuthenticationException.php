<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 401: the token is missing, wrong, or belongs to the other environment. Reverb also answers 401 when an operation needs an OAuth token rather than a personal one (webhook registration).
 */
class AuthenticationException extends ClientException {}
