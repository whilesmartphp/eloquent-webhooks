<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Webhooks\Http\Controllers\WebhookController;

Route::prefix('workspaces/{workspaceId}/webhooks')->group(function () {
    Route::get('/', [WebhookController::class, 'index']);
    Route::post('/', [WebhookController::class, 'store']);
    Route::get('/{webhookId}', [WebhookController::class, 'show']);
    Route::patch('/{webhookId}', [WebhookController::class, 'update']);
    Route::delete('/{webhookId}', [WebhookController::class, 'destroy']);
    Route::get('/{webhookId}/events', [WebhookController::class, 'events']);
});

Route::prefix('webhooks')->group(function () {
    Route::get('/', [WebhookController::class, 'index']);
    Route::post('/', [WebhookController::class, 'store']);
    Route::get('/{webhookId}', [WebhookController::class, 'show']);
    Route::patch('/{webhookId}', [WebhookController::class, 'update']);
    Route::delete('/{webhookId}', [WebhookController::class, 'destroy']);
    Route::get('/{webhookId}/events', [WebhookController::class, 'events']);
});
