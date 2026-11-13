<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Creating VPS from the panel: the public addresses that can be given to them, and the progress of a machine being
     * created (or deleted) on Proxmox.
     */
    public function up(): void
    {
        Schema::table('proxmox_vms', function (Blueprint $table) {
            if (!Schema::hasColumn('proxmox_vms', 'state')) {
                // "ready" for a machine that exists; "clone", "configure", "resize", "start" while it is created;
                // "stop", "destroy" while it is deleted; "failed" when a step went wrong.
                $table->string('state', 20)->default('ready')->after('name');
                // The task Proxmox is running for the current step ("UPID:..."), waited for before the next one.
                $table->string('task', 191)->nullable()->after('state');
                $table->text('error')->nullable()->after('task');
                $table->unsignedTinyInteger('attempts')->default(0)->after('error');
                // What was asked for (template, cores, memory, disk, address, SSH key), as JSON.
                $table->text('spec')->nullable()->after('attempts');
                // The root password set by cloud-init, encrypted with the key of the panel; shown to the owner.
                $table->text('password')->nullable()->after('spec');
            }
        });

        if (!Schema::hasTable('proxmox_ips')) {
            Schema::create('proxmox_ips', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('host_id');
                $table->string('address', 45)->unique();
                $table->unsignedTinyInteger('cidr')->default(32);
                $table->string('gateway', 45);
                // The virtual MAC address some hosts require for an additional IP (empty when not needed).
                $table->string('mac', 17)->nullable();
                $table->unsignedInteger('vm_id')->nullable();
                $table->timestamps();

                $table->foreign('host_id')->references('id')->on('proxmox_hosts')->cascadeOnDelete();
                $table->foreign('vm_id')->references('id')->on('proxmox_vms')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_ips');
        Schema::table('proxmox_vms', function (Blueprint $table) {
            $table->dropColumn(['state', 'task', 'error', 'attempts', 'spec', 'password']);
        });
    }
};
