<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The phones and computers that get the notifications of the panel (Web Push): where to send them, and the keys of
     * the device to encrypt them.
     */
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('device', 120)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
