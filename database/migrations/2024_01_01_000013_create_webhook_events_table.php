<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $uuids = (bool) config('webhooks.uuids', false);

        Schema::create('webhook_events', function (Blueprint $table) use ($uuids) {
            if ($uuids) {
                $table->uuid('id')->primary();
                $table->foreignUuid('webhook_id')->constrained()->onDelete('cascade');
            } else {
                $table->id();
                $table->foreignId('webhook_id')->constrained()->onDelete('cascade');
            }

            // The bytes exactly as they arrived. A signature is computed over
            // these, so a parsed and re-encoded copy cannot verify against it,
            // and neither can anything this event is later forwarded to.
            $table->longText('raw_payload')->nullable();
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->integer('response_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['webhook_id']);
            $table->index(['response_status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
