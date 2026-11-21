<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('delivery_id')->nullable()->after('webhook_id');
            $table->unique(['webhook_id', 'delivery_id']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique(['webhook_id', 'delivery_id']);
            $table->dropColumn('delivery_id');
        });
    }
};
