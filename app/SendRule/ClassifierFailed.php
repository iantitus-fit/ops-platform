<?php

namespace App\SendRule;

use RuntimeException;

/**
 * The classifier could not give a usable answer: the call failed, timed out, or came back incomplete.
 * The send rule treats this as "hold the draft", never as "send".
 */
class ClassifierFailed extends RuntimeException {}
