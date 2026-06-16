<?php

namespace Whilesmart\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\Webhooks\Concerns\HasConfigurableKey;

/**
 * @property int $webhook_id
 * @property string $event
 * @property string $event_id
 * @property array $payload
 * @property string $status
 * @property int $attempts
 * @property ?int $response_status
 * @property ?string $response_body
 * @property ?string $error
 * @property ?\Carbon\Carbon $next_attempt_at
 * @property ?\Carbon\Carbon $delivered_at
 * @property-read ?Webhook $webhook
 */
class WebhookDelivery extends Model
{
    use HasConfigurableKey;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERING = 'delivering';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'webhook_id',
        'event',
        'event_id',
        'payload',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'error',
        'next_attempt_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'response_status' => 'integer',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function scopeRetryable($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_DELIVERING]);
    }
}
