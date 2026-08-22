<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Webhooks\Http\Controllers\WebhookController;

Route::post('/webhooks/ingress/{token}', [WebhookController::class, 'ingress'])
    ->name('webhooks.ingress');
