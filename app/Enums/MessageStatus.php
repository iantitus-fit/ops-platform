<?php

namespace App\Enums;

/**
 * Where a message stands. A withdrawn message is a draft that never went out: the owner discarded it or a newer one
 * replaced it. It stays on the record and is never shown to the customer or the model.
 */
enum MessageStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';
    case Withdrawn = 'withdrawn';
}
