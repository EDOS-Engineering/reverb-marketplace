<?php

namespace Edos\ReverbMarketplace\Exceptions;

/**
 * 403: the token lacks a scope, or the account is not enabled for the action (publishing without multi-factor authentication, direct offers, B-Stock).
 */
class AuthorizationException extends ClientException {}
