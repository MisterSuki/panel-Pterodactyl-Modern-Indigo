<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * What a running server used, read every five minutes and kept 30 days: the history of its statistics.
     */
    public function up(): void
    {
        if (!Schema::hasTable('server_resource_samples')) {
            Schema::create('server_resource_samples', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id');
                // Tenths of a percent of one core (Wings counts 100% per core).
                $table->unsignedInteger('cpu');
                // Megabytes.
                $table->unsignedInteger('memory');
                $table->unsignedInteger('disk');
                $table->timestamp('created_at')->nullable();

                $table->index(['server_id', 'created_at']);
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_resource_samples');
    }
};
