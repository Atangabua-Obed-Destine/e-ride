<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A WhatsApp or SMS message sent to a traveller who booked on the website. */
class BusMessage extends Model
{
    public const WHATSAPP = 'whatsapp';
    public const SMS = 'sms';

    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const READ = 'read';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    protected $fillable = ['booking_id', 'event', 'dedupe', 'channel', 'to', 'status', 'provider_id', 'error', 'fallback_id', 'body'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BusBooking::class, 'booking_id');
    }
}
