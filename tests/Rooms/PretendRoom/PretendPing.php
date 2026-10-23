<?php

namespace Tests\Rooms\PretendRoom;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One note the pretend feed handed over. It exists only in tests and is the subject of the pretend room's timeline
 * entry, under the morph alias pretend_ping.
 */
#[Fillable(['contact_id', 'body', 'feed_id'])]
class PretendPing extends Model
{
    /**
     * The customer the ping is about.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
