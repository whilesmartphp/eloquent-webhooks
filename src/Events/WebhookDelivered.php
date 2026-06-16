<?php

namespace Whilesmart\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Webhooks\Models\WebhookDelivery;

class WebhookDelivered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public WebhookDelivery $delivery)
    {
    }
}
