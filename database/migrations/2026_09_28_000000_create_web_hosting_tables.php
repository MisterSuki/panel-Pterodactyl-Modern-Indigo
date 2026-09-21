<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Web hosting. A site is a server of the panel (a container that runs a web server and PHP), so the files, the
     * databases, the backups and the limits are the ones of the panel; what is added here is who owns what, the plans, the
     * domains of each site and the PHP version.
     *
     * - web_plans: what a client can have (number of sites and domains, the resources of each site, the PHP versions).
     * - web_accounts: a client's hosting, with the limits of the plan as they were when it was given.
     * - web_sites: one site of an account, and the server that runs it.
     * - web_domains: the names that lead to a site.
     */
    public function up(): void
    {
        Schema::create('web_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedInteger('egg_id');
            $table->unsignedInteger('location_id');
            $table->unsignedSmallInteger('max_sites')->default(1);
            $table->unsignedSmallInteger('max_domains')->default(3);
            $table->unsignedInteger('memory');
            $table->unsignedInteger('disk');
            $table->unsignedInteger('cpu')->default(0);
            $table->unsignedSmallInteger('database_limit')->default(1);
            $table->unsignedSmallInteger('backup_limit')->default(1);
            $table->text('php_versions')->nullable();
            $table->string('default_php', 10)->nullable();
            $table->text('environment')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('web_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('plan_id')->nullable()->index();
            $table->string('plan_name', 80);
            $table->unsignedSmallInteger('max_sites');
            $table->unsignedSmallInteger('max_domains');
            $table->string('status', 12)->default('active');
            $table->timestamps();
        });

        Schema::create('web_sites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('server_id')->nullable()->index();
            $table->string('name', 80);
            $table->string('php_version', 10)->nullable();
            $table->string('status', 12)->default('creating');
            $table->timestamps();
        });

        Schema::create('web_domains', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('site_id')->index();
            $table->string('domain', 253)->unique();
            $table->boolean('include_www')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 12)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_domains');
        Schema::dropIfExists('web_sites');
        Schema::dropIfExists('web_accounts');
        Schema::dropIfExists('web_plans');
    }
};
