<?php

namespace App\Icm\Walks;

/**
 * What one item of a walk file's script does: the customer says something, time passes, or the customer books a visit.
 * Each item in the file holds exactly one of these words as its key.
 */
enum WalkStepKind: string
{
    case Says = 'says';
    case Wait = 'wait';
    case Books = 'books';
}
