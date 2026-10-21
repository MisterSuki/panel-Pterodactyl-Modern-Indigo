<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Subdomains: the domains the administration offers (with the DNS provider that hosts them), the subdomains the
     * servers created under them, and how many each server may have.
     */
    public function up(): void
    {
        if (!Schema::hasTable('subdomain_domains')) {
            Schema::create('subdomain_domains', function (Blueprint $table) {
                $table->increments('id');
                $table->string('domain', 191)->unique();
                $table->string('provider', 32);
                $table->text('credentials');
                $table->string('zone_id', 191)->nullable();
                $table->boolean('available')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('server_subdomains')) {
            Schema::create('server_subdomains', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->unsignedInteger('domain_id');
                $table->string('name', 63);
                $table->unsignedInteger('allocation_id')->nullable();
                $table->string('notes', 191)->nullable();
                $table->text('records')->nullable();
                $table->timestamps();

                $table->unique(['domain_id', 'name']);
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('domain_id')->references('id')->on('subdomain_domains')->cascadeOnDelete();
                $table->foreign('allocation_id')->references('id')->on('allocations')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('servers', 'subdomain_limit')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('subdomain_limit')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_subdomains');
        Schema::dropIfExists('subdomain_domains');
        if (Schema::hasColumn('servers', 'subdomain_limit')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('subdomain_limit');
            });
        }
    }
};
