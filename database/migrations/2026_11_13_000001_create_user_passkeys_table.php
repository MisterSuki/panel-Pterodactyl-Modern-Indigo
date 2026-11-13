<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The passkeys of the accounts: signing in with Face ID, Touch ID, Windows Hello or a security key.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_passkeys')) {
            Schema::create('user_passkeys', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('name', 64);
                // The site the passkey was made on (the host of the panel).
                $table->string('rp_id', 191);
                // The id of the credential (base64url), and its public key (SubjectPublicKeyInfo, base64).
                $table->string('credential_id', 191)->unique();
                $table->text('public_key');
                $table->integer('algorithm');
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->string('transports', 191)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_passkeys');
    }
};
