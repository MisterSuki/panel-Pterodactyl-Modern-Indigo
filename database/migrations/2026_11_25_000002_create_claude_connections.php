<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The links that connect the Claude app (claude.ai, Claude Desktop) to the panel, as a connector (MCP): each one
     * acts with the rights of its owner, read only or not. Only a hash of the secret of the link is kept.
     */
    public function up(): void
    {
        if (Schema::hasTable('claude_connections')) {
            return;
        }
        Schema::create('claude_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name', 60);
            $table->char('token_hash', 64)->unique();
            $table->boolean('read_only')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_connections');
    }
};
