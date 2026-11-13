<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Proxmox: the hosts the panel talks to (with an API token), and the virtual machines given to users as VPS.
     */
    public function up(): void
    {
        if (!Schema::hasTable('proxmox_hosts')) {
            Schema::create('proxmox_hosts', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 80);
                // The address of the API, "https://203.0.113.10:8006".
                $table->string('url', 191);
                // "user@realm!tokenid"; the secret is kept encrypted with the key of the panel.
                $table->string('token_id', 191);
                $table->text('token_secret');
                // Proxmox ships with a self-signed certificate: checking it is optional.
                $table->boolean('verify_tls')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('proxmox_vms')) {
            Schema::create('proxmox_vms', function (Blueprint $table) {
                $table->increments('id');
                // What the address of the VPS in the panel says, so the numbers of Proxmox are never in it.
                $table->char('uuid', 36)->unique();
                $table->unsignedInteger('host_id');
                $table->string('node', 64);
                $table->unsignedInteger('vmid');
                $table->unsignedInteger('user_id');
                // The name shown to the user (the one of Proxmox by default).
                $table->string('name', 100);
                $table->timestamps();

                $table->unique(['host_id', 'vmid']);
                $table->index('user_id');
                $table->foreign('host_id')->references('id')->on('proxmox_hosts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_vms');
        Schema::dropIfExists('proxmox_hosts');
    }
};
