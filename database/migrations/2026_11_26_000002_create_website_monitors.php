<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The monitor of the websites: the state of each site (checked every minute), and the checks of the last days for
     * its availability.
     */
    public function up(): void
    {
        if (!Schema::hasTable('website_monitors')) {
            Schema::create('website_monitors', function (Blueprint $table) {
                $table->unsignedInteger('server_id')->primary();
                $table->string('url', 255)->nullable();
                // up, down, stopped (the server is off), no_domain, unknown.
                $table->string('state', 12)->default('unknown');
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('response_ms')->nullable();
                $table->unsignedTinyInteger('failures')->default(0);
                $table->timestamp('down_since')->nullable();
                $table->boolean('alerted')->default(false);
                $table->string('last_error', 191)->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('ssl_expires_at')->nullable();
                $table->timestamp('ssl_checked_at')->nullable();
                $table->timestamp('ssl_warned_at')->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('website_monitor_samples')) {
            Schema::create('website_monitor_samples', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id');
                $table->boolean('ok');
                $table->unsignedInteger('ms')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['server_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_monitor_samples');
        Schema::dropIfExists('website_monitors');
    }
};
