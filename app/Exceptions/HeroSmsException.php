<?php

namespace App\Exceptions;

/**
 * Typed error for HeroSMS API failures. $errorCode carries the
 * documented API error code (NO_NUMBERS, EARLY_CANCEL_DENIED, BANNED,
 * ...) so callers can branch on it without parsing messages.
 */
class HeroSmsException extends \Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly ?array $info = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    /** Human-readable map for the documented error codes. */
    public const MESSAGES = [
        'BAD_ACTION' => 'Incorrect API action',
        'BAD_KEY' => 'Incorrect API key',
        'NO_KEY' => 'API key is missing',
        'ERROR_SQL' => 'Provider server error',
        'BAD_SERVICE' => 'Incorrect service code',
        'BAD_STATUS' => 'Incorrect status code',
        'NO_NUMBERS' => 'No numbers available',
        'NO_ACTIVATION' => 'Activation not found',
        'WRONG_ACTIVATION_ID' => 'Invalid activation ID',
        'WRONG_MAX_PRICE' => 'Max price below minimum',
        'EARLY_CANCEL_DENIED' => 'Cancellation only possible 2 minutes after purchase',
        'CHANNELS_LIMIT' => 'Provider concurrent-activation limit reached',
        'OPERATORS_NOT_FOUND' => 'No operators found',
        'ORDER_ALREADY_EXISTS' => 'Order already exists',
        'NO_ACTIVATIONS' => 'No activations found',
        'ACCOUNT_INACTIVE' => 'Provider account inactive',
        'SERVICE_NOT_AVAILABLE' => 'Service not available',
        'SIM_OFFLINE' => 'SIM offline',
        'NO_BALANCE' => 'Insufficient provider balance',
        'UNPROCESSABLE_ENTITY' => 'Validation failed at provider',
        'FREE_CANCELLATION_EXPIRED' => 'Free cancellation period expired',
        'OTP_RECEIVED' => 'OTP already received',
        'NEW_OTP_RECEIVED' => 'New OTP received',
        'ACTIVATION_NOT_ACTIVE' => 'Activation not active',
        'FINISHED' => 'Activation already finished',
        'REFUNDED' => 'Activation already refunded',
        'CANCELED' => 'Activation already cancelled',
        'BAD_DURATION' => 'Invalid duration',
        'WRONG_COUNTRY' => 'Invalid country',
        'WRONG_SERVICE' => 'Invalid service',
        'WRONG_CURRENCY' => 'Invalid currency',
        'NOT_FOUND' => 'Not found',
        'SERVER_ERROR' => 'Provider server error',
        'BANNED' => 'Provider account banned',
    ];

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, ['ERROR_SQL', 'SERVER_ERROR', 'HTTP_500', 'HTTP_502', 'HTTP_503', 'HTTP_504', 'TIMEOUT']);
    }
}
