<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The changes of files proposed by the AI assistant of a server: nothing is written until the person applies
     * one, and the content from before is kept so it can be put back at any time.
     */
    public function up(): void
    {
        if (Schema::hasTable('assistant_file_changes')) {
            return;
        }
        Schema::create('assistant_file_changes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('user_id');
            $table->string('path', 255);
            $table->string('summary', 255)->default('');
            // The file before (null: the change creates it) and after the change.
            $table->longText('before')->nullable();
            $table->longText('after');
            // What is shown: the parts replaced, as [{"removed": "...", "added": "..."}].
            $table->longText('hunks');
            // proposed, applied, reverted or dismissed.
            $table->string('state', 10)->default('proposed');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_file_changes');
    }
};
