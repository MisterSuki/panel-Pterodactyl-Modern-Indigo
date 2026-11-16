<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The folders of the file hosting shared with a public link (/d/<token>): everything in the folder, and in its
     * folders, can be seen and downloaded by anyone with the link, until the owner stops sharing it.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_folder_shares')) {
            return;
        }
        Schema::create('user_folder_shares', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            // The path of the folder, as in user_files.folder ("Holidays/2026").
            $table->string('folder', 191);
            $table->char('token', 24)->unique();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'folder']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_folder_shares');
    }
};
