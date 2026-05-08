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
    /**
     * List webhooks for user or workspace.
     */
    public function index(?string $workspaceId = null): JsonResponse
    {
        $user = auth()->user();
        $query = Webhook::where('user_id', $user->id);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);

            // @phpstan-ignore-next-line
            if (
                ! $user->hasRole('workspace-member', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId) &&
                // @phpstan-ignore-next-line
                ! $user->hasRole('workspace-owner', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId) &&
                // @phpstan-ignore-next-line
                ! $user->hasRole('workspace-admin', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId)
            ) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $webhooks = $query->get()->map(function ($webhook) {
            return [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'description' => $webhook->description,
                'url' => $webhook->url,
                'is_active' => $webhook->is_active,
                'project_id' => $webhook->project_id,
                'provider' => $webhook->provider,
                'event_type' => $webhook->event_type,
                'trigger_count' => $webhook->trigger_count,
                'last_triggered_at' => $webhook->last_triggered_at?->toISOString(),
                'created_at' => $webhook->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $webhooks,
        ]);
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request, ?string $workspaceId = null): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'projectId' => 'required|exists:projects,id',
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

        if ($workspaceId) {
            // @phpstan-ignore-next-line
            if (
                ! $user->hasRole('workspace-member', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId) &&
                // @phpstan-ignore-next-line
                ! $user->hasRole('workspace-owner', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId) &&
                // @phpstan-ignore-next-line
                ! $user->hasRole('workspace-admin', 'Whilesmart\\Workspaces\\Models\\Workspace', $workspaceId)
            ) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        try {
            $webhook = Webhook::create([
                'user_id' => $user->id,
                'workspace_id' => $workspaceId,
                'project_id' => $request->projectId,
                'name' => $request->name,
                'description' => $request->description,
                'provider' => $request->provider ?? 'custom',
                'event_type' => $request->event_type,
                'is_active' => true,
                'trigger_count' => 0,
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
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create webhook.',
            ], 500);
        }
    }

    /**
     * Get webhook details.
     */
    public function show(?string $workspaceId = null, ?int $webhookId = null): JsonResponse
    {
        if ($webhookId === null) {
            $webhookId = (int) $workspaceId;
            $workspaceId = null;
        }

        $webhook = Webhook::where('user_id', auth()->id())
            ->where('id', $webhookId)
            ->first();

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'description' => $webhook->description,
                'url' => $webhook->url,
                'token' => $webhook->token,
                'secret' => $webhook->secret,
                'provider' => $webhook->provider,
                'event_type' => $webhook->event_type,
                'is_active' => $webhook->is_active,
                'project_id' => $webhook->project_id,
                'settings' => $webhook->settings,
                'trigger_count' => $webhook->trigger_count,
                'last_triggered_at' => $webhook->last_triggered_at?->toISOString(),
                'created_at' => $webhook->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update webhook.
     */
    public function update(Request $request, ?string $workspaceId = null, ?int $webhookId = null): JsonResponse
    {
        if ($webhookId === null) {
            $webhookId = (int) $workspaceId;
            $workspaceId = null;
        }

        $webhook = Webhook::where('user_id', auth()->id())
            ->where('id', $webhookId)
            ->first();

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook not found.',
            ], 404);
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
            'settings' => $request->input('settings', $webhook->settings),
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

    /**
     * Delete webhook.
     */
    public function destroy(?string $workspaceId = null, ?int $webhookId = null): JsonResponse
    {
        if ($webhookId === null) {
            $webhookId = (int) $workspaceId;
            $workspaceId = null;
        }

        $webhook = Webhook::where('user_id', auth()->id())
            ->where('id', $webhookId)
            ->first();

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook not found.',
            ], 404);
        }

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully.',
        ]);
    }

    /**
     * List events for a webhook.
     */
    public function events(?string $workspaceId = null, ?int $webhookId = null): JsonResponse
    {
        if ($webhookId === null) {
            $webhookId = (int) $workspaceId;
            $workspaceId = null;
        }

        $webhook = Webhook::where('user_id', auth()->id())
            ->where('id', $webhookId)
            ->first();

        if (! $webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook not found.',
            ], 404);
        }

        $events = $webhook->events()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Public webhook ingress endpoint.
     */
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
                'payload' => $request->all(),
                'headers' => collect($request->headers->all())
                    ->except(['authorization', 'cookie'])
                    ->toArray(),
                'response_status' => $responseStatus,
                'processed_at' => now(),
            ]);

            if (class_exists('Whilesmart\\Activities\\Models\\Activity')) {
                $activityData = [
                    'actor_type' => Webhook::class,
                    'actor_id' => $webhook->id,
                    'action' => 'webhook_triggered',
                    'subject_type' => 'Whilesmart\\Projects\\Models\\Project',
                    'subject_id' => $webhook->project_id,
                    'context_type' => 'Whilesmart\\Workspaces\\Models\\Workspace',
                    'context_id' => $webhook->workspace_id,
                    'source' => 'webhook',
                    'source_id' => $webhook->id,
                    'summary' => $request->input('title', 'Webhook activity'),
                    'description' => $request->input('description', ''),
                    'properties' => $request->all(),
                    'occurred_at' => $request->input('startTime') ?
                        \Carbon\Carbon::parse($request->input('startTime')) : now(),
                ];

                \Whilesmart\Activities\Models\Activity::create($activityData);
            }

            Log::info('Webhook triggered successfully', [
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
                'trigger_count' => $webhook->trigger_count,
            ]);

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
}
