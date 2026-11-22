<?php

namespace App\Icm\Walks;

/**
 * How one walk ended. Passed: every step ran and every check held. Failed: a check did not hold (the checks arrive in
 * plan 10-04). NeedsRecording: the candidate asked for a model reply or a Jev reading the recording does not hold, so
 * nothing was guessed (10 D-27). Errored: the walk could not run at all (the gate sets this in plan 10-05).
 */
enum WalkOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case NeedsRecording = 'needs_recording';
    case Errored = 'errored';
}
