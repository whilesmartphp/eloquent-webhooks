<?php

namespace Whilesmart\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\Webhooks\Concerns\HasConfigurableKey;

/**
 * @property int|string $webhook_id
 * @property string|null $payload the body exactly as it arrived
 * @property array|null $headers
 * @property int|null $response_status
 */
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
        'headers' => 'array',
        'response_status' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * The payload as data, worked out from the bytes rather than stored twice.
     *
     * Returns null when the body is not something we can read, which is not an
     * error: the bytes are still there to be read by whatever understands them.
     */
    public function decoded(): ?array
    {
        $raw = $this->payload;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        // Some senders post a form rather than a document.
        parse_str($raw, $form);

        return $form === [] ? null : $form;
    }

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
