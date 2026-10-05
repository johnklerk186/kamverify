<?php

namespace App\Exceptions;

/**
 * TextVerified provider failure. Extends HeroSmsException so every
 * existing error-code branch in the cancel/expire jobs and order
 * controllers keeps working unchanged — the jobs reason about
 * outcomes (OTP_RECEIVED, CANCELED, REFUNDED), not providers.
 */
class TextVerifiedException extends HeroSmsException
{
}
