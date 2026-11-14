<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The maintenances shown on the status page (planned or in progress), and the answers of the Proxmox nodes, every
     * five minutes, for the VPS part of the page.
     */
    public function up(): void
    {
        if (!Schema::hasTable('status_maintenances')) {
            Schema::create('status_maintenances', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->text('body')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                // What it concerns, as a JSON list: "panel", "node:<id>", "proxmox:<id of the host>".
                $table->text('components');
                $table->timestamps();

                $table->index(['starts_at', 'ends_at']);
            });
        }

        if (!Schema::hasTable('proxmox_node_samples')) {
            Schema::create('proxmox_node_samples', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('host_id');
                $table->string('node', 64);
                $table->boolean('online');
                $table->timestamp('created_at')->nullable();

                $table->index(['host_id', 'node', 'created_at']);
                $table->foreign('host_id')->references('id')->on('proxmox_hosts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_node_samples');
        Schema::dropIfExists('status_maintenances');
    }
};
