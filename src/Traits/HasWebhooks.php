<?php

namespace Whilesmart\Webhooks\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Services\WebhookDispatcher;

trait HasWebhooks
{
    public function webhooks(): MorphMany
    {
        return $this->morphMany(Webhook::class, 'owner');
    }

    /**
     * @return Collection<int, \Whilesmart\Webhooks\Models\WebhookDelivery>
     */
    public function triggerWebhook(string $event, array $payload): Collection
    {
        return app(WebhookDispatcher::class)->dispatch($event, $payload, [
            'owner_type' => $this->getMorphClass(),
            'owner_id' => $this->getKey(),
        ]);
    }
}
