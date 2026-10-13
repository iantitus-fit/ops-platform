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

    /**
     * The texts a customer has on their phone: the ones they sent us, and ours that reached them. A draft, a
     * withdrawn draft, a queued text and a failed one never reached them. This is the one definition for any view of
     * the conversation as the customer sees it (the dev chat page, the model's history, open-leads.md and the
     * Customers waiting widget).
     *
     * @return list<self>
     */
    public static function onTheCustomersPhone(): array
    {
        return [self::Received, self::Sent, self::Delivered];
    }
}
