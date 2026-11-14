<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The sub-users of a VPS: other people of the panel the owner lets see the machine, with what they may do on it.
     */
    public function up(): void
    {
        if (Schema::hasTable('proxmox_vm_users')) {
            return;
        }

        Schema::create('proxmox_vm_users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('vm_id');
            $table->unsignedInteger('user_id');
            // What the sub-user may do, as a JSON list ("power", "console", "password").
            $table->text('permissions');
            $table->timestamps();

            $table->unique(['vm_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('vm_id')->references('id')->on('proxmox_vms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_vm_users');
    }
};
