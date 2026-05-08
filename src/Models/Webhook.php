<?php

namespace Whilesmart\Webhooks\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $trigger_count
 * @property int $workspace_id
 * @property int $project_id
 * @property bool $is_active
 * @property string $name
 * @property string $description
 * @property string $url
 * @property string $token
 * @property Carbon $created_at
 * @property ?Carbon $last_triggered_at
 * @property string $event_type
 * @property string $provider
 * @property string $secret
 * @property array $settings
*/
class Webhook extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'project_id',
        'name',
        'description',
        'provider',
        'event_type',
        'token',
        'url',
        'secret',
        'filters',
        'is_active',
        'settings',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webhook) {
            if (! $webhook->token) {
                $webhook->token = Str::random(40);
            }
            if (! $webhook->url) {
                $webhook->url = url("/webhooks/ingress/{$webhook->token}");
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('webhooks.user_model', 'App\\Models\\User'));
    }

    public function workspace(): BelongsTo
    {
        if (class_exists('Whilesmart\\Workspaces\\Models\\Workspace')) {
            // @phpstan-ignore-next-line
            return $this->belongsTo('Whilesmart\\Workspaces\\Models\\Workspace');
        }
        // @phpstan-ignore-next-line
        return $this->belongsTo('App\\Models\\Workspace');
    }

    public function project(): BelongsTo
    {
        if (class_exists('Whilesmart\\Projects\\Models\\Project')) {
            // @phpstan-ignore-next-line
            return $this->belongsTo('Whilesmart\\Projects\\Models\\Project');
        }

        // @phpstan-ignore-next-line
        return $this->belongsTo('App\\Models\\Project');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
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

    public function scopeInWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
