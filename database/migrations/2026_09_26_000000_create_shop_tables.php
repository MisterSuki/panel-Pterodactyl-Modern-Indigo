<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The shop. Every amount is a whole number of cents (no decimals anywhere, so nothing is ever rounded).
     *
     * - shop_offers: what is sold, and the server that is made when it is bought.
     * - shop_wallets: the credit of a person.
     * - shop_transactions: every movement of a credit, and what it was for. The credit is always the sum of these.
     * - shop_payments: money asked from a provider (Stripe, PayPal, SumUp) to add credit.
     * - shop_orders: an offer bought by a person, the server it made and until when it is paid.
     */
    public function up(): void
    {
        Schema::create('shop_offers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->unsignedInteger('egg_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('memory');
            $table->unsignedInteger('disk');
            $table->unsignedInteger('cpu')->default(0);
            $table->unsignedSmallInteger('database_limit')->default(0);
            $table->unsignedSmallInteger('allocation_limit')->default(0);
            $table->unsignedSmallInteger('backup_limit')->default(0);
            $table->text('environment')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_wallets', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->bigInteger('balance_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('offer_id')->nullable()->index();
            $table->string('offer_name', 80);
            $table->unsignedInteger('server_id')->nullable()->index();
            $table->string('status', 16)->default('provisioning')->index();
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('renewals')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('shop_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 40)->unique();
            $table->unsignedInteger('user_id')->index();
            $table->string('provider', 16);
            $table->string('provider_ref', 120)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 12)->default('pending')->index();
            $table->unsignedInteger('buy_offer_id')->nullable();
            $table->unsignedInteger('renew_order_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_ref']);
        });

        Schema::create('shop_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('type', 16);
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('note', 160)->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_transactions');
        Schema::dropIfExists('shop_payments');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_wallets');
        Schema::dropIfExists('shop_offers');
    }
};
