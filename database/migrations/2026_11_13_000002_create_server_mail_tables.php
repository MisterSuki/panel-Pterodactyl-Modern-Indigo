<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Email on the domain of a site: the domains it is turned on for (with their DKIM key), and the mailboxes.
     */
    public function up(): void
    {
        if (!Schema::hasTable('server_mail_domains')) {
            Schema::create('server_mail_domains', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->string('hostname', 191)->unique();
                // The DKIM record the mail server made for the domain ("v=DKIM1; k=rsa; p=..."), once it is ready.
                $table->text('dkim')->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('server_mail_boxes')) {
            Schema::create('server_mail_boxes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->unsignedInteger('mail_domain_id');
                $table->string('local_part', 64);
                $table->string('address', 191)->unique();
                // SHA512-CRYPT, the form the mail server reads.
                $table->text('password');
                $table->unsignedInteger('quota_mb');
                $table->unsignedBigInteger('used_bytes')->nullable();
                $table->timestamps();

                $table->index('server_id');
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('mail_domain_id')->references('id')->on('server_mail_domains')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_mail_boxes');
        Schema::dropIfExists('server_mail_domains');
    }
};
