<?php

namespace Whilesmart\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\Webhooks\Concerns\HasConfigurableKey;

/**
 * @property int|string $webhook_id
 * @property array|null $payload
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
        'payload' => 'array',
        'headers' => 'array',
        'response_status' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function rawBody(): ?string
    {
        $raw = $this->payload['raw_body'] ?? null;

        if (! is_string($raw)) {
            return null;
        }

        if (($this->payload['encoding'] ?? 'utf-8') === 'base64') {
            $decoded = base64_decode($raw, true);

            return $decoded === false ? null : $decoded;
        }

        return $raw;
    }

    public function decoded(): ?array
    {
        $raw = $this->rawBody();

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
