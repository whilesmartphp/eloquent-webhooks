<?php

namespace Whilesmart\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\Webhooks\Concerns\HasConfigurableKey;

class WebhookEvent extends Model
{
    use HasConfigurableKey;

    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'payload',
        'headers',
        'response_status',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'response_status' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (! $event->created_at) {
                $event->created_at = now();
            }
        });
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
