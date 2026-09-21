<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Web hosting plans in the shop. An offer can be a web hosting plan (web_plan_id): buying it gives the buyer a hosting
     * account and their first site. The order remembers the account (web_account_id), so that when the time paid for ends
     * the whole account is suspended, and given back when it is renewed. A payment that waits to buy an offer remembers
     * what the buyer chose for the site (its name and its domain).
     */
    public function up(): void
    {
        Schema::table('shop_offers', function (Blueprint $table) {
            $table->unsignedInteger('web_plan_id')->nullable()->index();
        });
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->unsignedInteger('web_account_id')->nullable()->index();
        });
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->text('buy_options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->dropColumn('buy_options');
        });
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropIndex(['web_account_id']);
            $table->dropColumn('web_account_id');
        });
        Schema::table('shop_offers', function (Blueprint $table) {
            $table->dropIndex(['web_plan_id']);
            $table->dropColumn('web_plan_id');
        });
    }
};
