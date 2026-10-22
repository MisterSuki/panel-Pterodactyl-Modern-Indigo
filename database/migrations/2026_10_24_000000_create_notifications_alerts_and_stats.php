<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The bell of the panel (notifications to one person or to everybody), the alerts of the servers (Discord and the
     * bell), how many players were on each server over time, and whether each node answered (for the status page).
     */
    public function up(): void
    {
        if (!Schema::hasTable('panel_notifications')) {
            Schema::create('panel_notifications', function (Blueprint $table) {
                $table->increments('id');
                // Empty: for everybody (an announcement of the administration).
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('kind', 16)->default('info');
                $table->string('title', 191);
                $table->text('body')->nullable();
                $table->string('url', 500)->nullable();
                // An announcement also shown on the public status page.
                $table->boolean('public')->default(false);
                $table->timestamp('created_at')->nullable()->index();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('panel_notification_reads')) {
            Schema::create('panel_notification_reads', function (Blueprint $table) {
                $table->unsignedInteger('notification_id');
                $table->unsignedInteger('user_id');
                $table->primary(['notification_id', 'user_id']);

                $table->foreign('notification_id')->references('id')->on('panel_notifications')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('server_alerts')) {
            Schema::create('server_alerts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id')->unique();
                $table->text('webhook')->nullable();
                $table->text('events')->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('server_player_samples')) {
            Schema::create('server_player_samples', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id');
                $table->unsignedSmallInteger('players');
                $table->unsignedSmallInteger('max_players')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['server_id', 'created_at']);
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('node_status_samples')) {
            Schema::create('node_status_samples', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('node_id');
                $table->boolean('online');
                $table->timestamp('created_at')->nullable();

                $table->index(['node_id', 'created_at']);
                $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('node_status_samples');
        Schema::dropIfExists('server_player_samples');
        Schema::dropIfExists('server_alerts');
        Schema::dropIfExists('panel_notification_reads');
        Schema::dropIfExists('panel_notifications');
    }
};
