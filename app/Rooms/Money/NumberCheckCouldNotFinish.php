<?php

namespace App\Rooms\Money;

use RuntimeException;

/**
 * The money notes number check could not finish reading the text: the pattern engine hit one of its limits, which a
 * very long run of digit groups does. The money notes type treats this as a refusal, never as a pass (05 D-30).
 */
class NumberCheckCouldNotFinish extends RuntimeException {}
