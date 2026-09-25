<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The support tickets and the messages exchanged in them.
     *
     * A ticket is "open" while it waits for an answer from the staff, "answered" while it waits for the person who
     * opened it, and "closed" when it is over. The two "read" numbers are the last message the person has seen, so the
     * panel can tell them that there is something new.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('server_id')->nullable();
            $table->string('subject', 191);
            $table->string('category', 32)->default('general');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('open')->index();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('user_read_id')->default(0);
            $table->dateTime('last_message_at')->nullable()->index();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedInteger('user_id');
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            // A note between staff members: the person who opened the ticket never sees it.
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
