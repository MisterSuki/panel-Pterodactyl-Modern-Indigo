<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Folders in the file hosting (a folder sent as it is keeps its tree), and the biggest file one user may send
     * when it is not the default one.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_files') && !Schema::hasColumn('user_files', 'folder')) {
            Schema::table('user_files', function (Blueprint $table) {
                // "" for the top, "Holidays/2026" for a folder in a folder.
                $table->string('folder', 191)->default('')->after('name');
                $table->index(['user_id', 'folder']);
            });
        }
        if (!Schema::hasColumn('users', 'file_max')) {
            Schema::table('users', function (Blueprint $table) {
                // In bytes; null = the default biggest file set in the administration.
                $table->unsignedBigInteger('file_max')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_files', 'folder')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'folder']);
                $table->dropColumn('folder');
            });
        }
        if (Schema::hasColumn('users', 'file_max')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('file_max'));
        }
    }
};
