<?php

namespace App\Enums;

/**
 * Why a booking was refused, one case for each way BookingRefused is made. A caller decides what to do by the reason and
 * never by the sentence, so rewording a sentence cannot change what happens next (WR-04).
 */
enum BookingRefusal: string
{
    case RoomOff = 'room_off';
    case NoSuchService = 'no_such_service';
    case PastStart = 'past_start';
    case Taken = 'taken';
    case ProjectNotOpen = 'project_not_open';
    case NoteTooLong = 'note_too_long';
    case NotStarted = 'not_started';
    case NotBooked = 'not_booked';
    case EndBeforeStart = 'end_before_start';
    case BlockNoteTooLong = 'block_note_too_long';
    case BlockOverBooking = 'block_over_booking';
    case Busy = 'busy';
    case LinkClosed = 'link_closed';
}
