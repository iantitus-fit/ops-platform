<?php

namespace App\Channels;

use RuntimeException;

/**
 * The carrier refused a text because the number opted out (Twilio error 21610).
 */
class RecipientOptedOut extends RuntimeException {}
