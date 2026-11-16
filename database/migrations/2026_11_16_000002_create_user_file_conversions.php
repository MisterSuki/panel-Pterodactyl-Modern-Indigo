<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The conversions of the files of the file hosting (a video into MP4 or GIF, a picture into WebP, a sound into
     * MP3...): made by ffmpeg in the background, the result is a new file next to the first one.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_file_conversions')) {
            return;
        }
        Schema::create('user_file_conversions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            // The file converted (null once it was deleted), and its name at the time.
            $table->unsignedInteger('file_id')->nullable();
            $table->string('name', 191);
            $table->string('format', 8);
            // queued, running, done or failed.
            $table->string('state', 10)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('error', 191)->nullable();
            $table->unsignedInteger('result_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'state']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_file_conversions');
    }
};
