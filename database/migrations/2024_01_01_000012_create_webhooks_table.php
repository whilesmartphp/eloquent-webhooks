<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $uuids = (bool) config('webhooks.uuids', false);

        Schema::create('webhooks', function (Blueprint $table) use ($uuids) {
            if ($uuids) {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->nullableUuidMorphs('created_by');
            } else {
                $table->id();
                $table->nullableMorphs('owner');
                $table->nullableMorphs('created_by');
            }

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('direction')->default('incoming');
            $table->json('subscribed_events')->nullable();
            $table->string('token')->unique()->nullable();
            $table->string('url')->nullable();
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('trigger_count')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Dispatch asks for one owner's active outgoing hooks on every event
            // it sends. Indexing direction or is_active on their own is close to
            // useless: each holds two values, so neither narrows anything and the
            // planner ignores both.
            $table->index(['owner_type', 'owner_id', 'direction', 'is_active'], 'webhooks_owner_dispatch');
            $table->index(['direction', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
