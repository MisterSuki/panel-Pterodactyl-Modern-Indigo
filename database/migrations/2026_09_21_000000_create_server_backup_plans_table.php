<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The automatic backups of a server: how often, how many to keep, and when the next one is due.
     */
    public function up(): void
    {
        Schema::create('server_backup_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('interval_hours');
            $table->unsignedTinyInteger('at_hour')->nullable();
            $table->unsignedSmallInteger('keep')->default(3);
            $table->text('ignored')->nullable();
            $table->dateTime('next_run_at')->nullable()->index();
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_backup_plans');
    }
};
