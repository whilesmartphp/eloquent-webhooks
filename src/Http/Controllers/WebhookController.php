<?php

namespace Whilesmart\Webhooks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Models\WebhookEvent;

class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        [$ownerType, $ownerId] = $this->authOwner();

        $webhooks = Webhook::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get()
            ->map(fn ($webhook) => $this->summarize($webhook));

        return response()->json([
            'success' => true,
            'data' => $webhooks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        $metadata = array_filter([
            'provider' => $request->input('provider', 'custom'),
            'event_type' => $request->input('event_type'),
            'filters' => $request->input('filters'),
            'settings' => $request->input('settings'),
        ], fn ($value) => $value !== null);

        try {
            $webhook = Webhook::create([
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
                'created_by_type' => $user->getMorphClass(),
                'created_by_id' => $user->getKey(),
                'name' => $request->name,
                'description' => $request->description,
                'metadata' => $metadata,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $webhook->id,
                    'name' => $webhook->name,
                    'url' => $webhook->url,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create webhook', [
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create webhook.',
            ], 500);
        }
    }

    public function show(string $webhookId): JsonResponse
    {
        $webhook = $this->findOwned($webhookId);

        if (! $webhook) {
            return response()->json(['success' => false, 'message' => 'Webhook not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->summarize($webhook) + [
                'token' => $webhook->token,
                'secret' => $webhook->secret,
                'metadata' => $webhook->metadata,
            ],
        ]);
    }

    public function update(Request $request, string $webhookId): JsonResponse
    {
        $webhook = $this->findOwned($webhookId);

        if (! $webhook) {
            return response()->json(['success' => false, 'message' => 'Webhook not found.'], 404);
        }

        if ($request->boolean('regenerate_token')) {
            $webhook->regenerateToken();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $webhook->id,
                    'token' => $webhook->token,
                    'url' => $webhook->url,
                ],
            ]);
        }

        $webhook->update([
            'name' => $request->input('name', $webhook->name),
            'description' => $request->input('description', $webhook->description),
            'is_active' => $request->boolean('is_active', $webhook->is_active),
            'metadata' => $request->input('metadata', $webhook->metadata),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'is_active' => $webhook->is_active,
            ],
        ]);
    }

    public function destroy(string $webhookId): JsonResponse
    {
        $webhook = $this->findOwned($webhookId);

        if (! $webhook) {
            return response()->json(['success' => false, 'message' => 'Webhook not found.'], 404);
        }

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully.',
        ]);
    }

    public function events(string $webhookId): JsonResponse
    {
        $webhook = $this->findOwned($webhookId);

        if (! $webhook) {
            return response()->json(['success' => false, 'message' => 'Webhook not found.'], 404);
        }

        $events = $webhook->events()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function ingress(Request $request, string $token): JsonResponse
    {
        $webhook = Webhook::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook token.',
            ], 404);
        }

        $responseStatus = 202;

        try {
            $webhook->recordTrigger();

            WebhookEvent::create([
                'webhook_id' => $webhook->id,
                'raw_payload' => $request->getContent(),
                'payload' => $request->all(),
                'headers' => collect($request->headers->all())
                    ->except(['authorization', 'cookie'])
                    ->toArray(),
                'response_status' => $responseStatus,
                'processed_at' => now(),
            ]);

            if (class_exists('Whilesmart\\Activities\\Models\\Activity')) {
                \Whilesmart\Activities\Models\Activity::create([
                    'actor_type' => Webhook::class,
                    'actor_id' => $webhook->id,
                    'action' => 'webhook_triggered',
                    'subject_type' => Webhook::class,
                    'subject_id' => $webhook->id,
                    'context_type' => $webhook->owner_type,
                    'context_id' => $webhook->owner_id,
                    'source' => 'webhook',
                    'source_id' => $webhook->id,
                    'summary' => $request->input('title', 'Webhook activity'),
                    'description' => $request->input('description', ''),
                    'properties' => $request->all(),
                    'occurred_at' => $request->input('startTime') ?
                        \Carbon\Carbon::parse($request->input('startTime')) : now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully.',
            ], $responseStatus);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }

    private function authOwner(): array
    {
        $user = auth()->user();

        return [$user->getMorphClass(), $user->getKey()];
    }

    private function findOwned(string $webhookId): ?Webhook
    {
        [$ownerType, $ownerId] = $this->authOwner();

        return Webhook::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('id', $webhookId)
            ->first();
    }

    private function summarize(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'description' => $webhook->description,
            'url' => $webhook->url,
            'is_active' => $webhook->is_active,
            'direction' => $webhook->direction,
            'owner_type' => $webhook->owner_type,
            'owner_id' => $webhook->owner_id,
            'provider' => $webhook->metadata['provider'] ?? null,
            'trigger_count' => $webhook->trigger_count,
            'last_triggered_at' => $webhook->last_triggered_at?->toISOString(),
            'created_at' => $webhook->created_at->toISOString(),
        ];
    }
}
