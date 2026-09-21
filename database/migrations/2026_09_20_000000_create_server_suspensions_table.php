<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The details of a suspension: why, by whom and until when. The suspended state itself stays in
     * servers.status, so the panel works as before if this table is ignored.
     */
    public function up(): void
    {
        Schema::create('server_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->text('reason')->nullable();
            $table->unsignedInteger('suspended_by')->nullable();
            $table->dateTime('suspended_until')->nullable()->index();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('suspended_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_suspensions');
    }
};
