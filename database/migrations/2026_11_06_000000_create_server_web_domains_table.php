<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The domains of the websites: an own domain or a subdomain of the panel, sent to the container of the site by the
     * web agent of its node, with the state of its certificate.
     */
    public function up(): void
    {
        if (!Schema::hasTable('server_web_domains')) {
            Schema::create('server_web_domains', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->string('hostname', 191)->unique();
                $table->unsignedInteger('custom_domain_id')->nullable();
                $table->unsignedInteger('subdomain_id')->nullable();
                $table->boolean('www')->default(false);
                $table->string('ssl_status', 16)->default('pending');
                $table->string('ssl_error', 500)->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('custom_domain_id')->references('id')->on('server_custom_domains')->cascadeOnDelete();
                $table->foreign('subdomain_id')->references('id')->on('server_subdomains')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_web_domains');
    }
};
