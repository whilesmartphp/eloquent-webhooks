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

            // The bytes exactly as they arrived, and only those. A signature is
            // computed over them, and decoding then re-encoding does not
            // reproduce them: spacing, unicode escaping, slashes, float
            // formatting and large integers all shift. Storing the parsed form
            // as well would be the same data twice, so it is derived on read.
            $table->longText('payload')->nullable();
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
