<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The file hosting of the users (pictures, videos, any file), and the space a user may use when it is not the
     * default one.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_files')) {
            Schema::create('user_files', function (Blueprint $table) {
                $table->increments('id');
                $table->char('uuid', 36)->unique();
                $table->unsignedInteger('user_id');
                $table->string('name', 191);
                $table->string('path', 191);
                $table->string('mime', 100);
                $table->unsignedBigInteger('size');
                // The secret part of the link of the file (/f/<token>).
                $table->string('token', 32)->unique();
                $table->boolean('public')->default(true);
                $table->unsignedInteger('views')->default(0);
                $table->timestamps();

                $table->index('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('users', 'file_quota')) {
            Schema::table('users', function (Blueprint $table) {
                // In bytes; null = the default space set in the administration.
                $table->unsignedBigInteger('file_quota')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_files');
        if (Schema::hasColumn('users', 'file_quota')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('file_quota'));
        }
    }
};
