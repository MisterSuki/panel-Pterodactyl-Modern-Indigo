<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The profile picture of a person: the name of the file that holds it (the file itself is kept out of the public
     * folder) and the moment it was changed, so that browsers fetch the new one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 80)->nullable();
            $table->timestamp('avatar_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'avatar_updated_at']);
        });
    }
};
