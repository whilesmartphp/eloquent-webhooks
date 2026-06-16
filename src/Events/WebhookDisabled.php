<?php

namespace Whilesmart\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Webhooks\Models\Webhook;

class WebhookDisabled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Webhook $webhook)
    {
    }
}
