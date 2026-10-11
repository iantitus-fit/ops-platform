<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';
}
