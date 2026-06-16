<?php

namespace Whilesmart\Webhooks\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Whilesmart\Webhooks\Concerns\HasConfigurableKey;

/**
 * @property int $trigger_count
 * @property ?string $owner_type
 * @property ?int $owner_id
 * @property ?string $created_by_type
 * @property ?int $created_by_id
 * @property bool $is_active
 * @property string $name
 * @property ?string $description
 * @property string $url
 * @property string $token
 * @property Carbon $created_at
 * @property ?Carbon $last_triggered_at
 * @property string $secret
 * @property ?array $metadata
 * @property string $direction
 * @property ?array $subscribed_events
 * @property int $consecutive_failures
*/
class Webhook extends Model
{
    use HasConfigurableKey;
    use HasFactory;
    use SoftDeletes;

    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    protected $attributes = [
        'direction' => self::DIRECTION_INCOMING,
        'consecutive_failures' => 0,
    ];

    protected $fillable = [
        'owner_type',
        'owner_id',
        'created_by_type',
        'created_by_id',
        'name',
        'direction',
        'description',
        'subscribed_events',
        'token',
        'url',
        'secret',
        'is_active',
        'metadata',
        'last_triggered_at',
        'trigger_count',
        'consecutive_failures',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscribed_events' => 'array',
        'metadata' => 'array',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
        'consecutive_failures' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webhook) {
            if ($webhook->isOutgoing()) {
                if (! $webhook->secret) {
                    $webhook->secret = Str::random(40);
                }

                return;
            }

            if (! $webhook->token) {
                $webhook->token = Str::random(40);
            }
            if (! $webhook->url) {
                $webhook->url = url("/webhooks/ingress/{$webhook->token}");
            }
        });
    }

    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUTGOING;
    }

    public function listensFor(string $event): bool
    {
        $events = $this->subscribed_events ?? [];

        return empty($events) || in_array($event, $events, true);
    }

    public function sign(string $timestamp, string $body): string
    {
        $algo = config('webhooks.signing.algo', 'sha256');

        return hash_hmac($algo, $timestamp . '.' . $body, (string) $this->secret);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', self::DIRECTION_OUTGOING);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', self::DIRECTION_INCOMING);
    }

    public function recordTrigger(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function regenerateToken(): void
    {
        $newToken = Str::random(40);
        $this->update([
            'token' => $newToken,
            'url' => url("/webhooks/ingress/{$newToken}"),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOwner($query, Model $owner)
    {
        return $query
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }
}
