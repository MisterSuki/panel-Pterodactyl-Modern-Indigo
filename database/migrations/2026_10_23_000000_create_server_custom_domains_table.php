<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The own domains of the servers: a name the user points at the server in their own DNS (the panel only shows what
     * to create and checks it).
     */
    public function up(): void
    {
        if (!Schema::hasTable('server_custom_domains')) {
            Schema::create('server_custom_domains', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->string('hostname', 191)->unique();
                $table->unsignedInteger('allocation_id')->nullable();
                $table->string('notes', 191)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('allocation_id')->references('id')->on('allocations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_custom_domains');
    }
};
