<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly billing of the resources a client adds to a server bought in the shop.
 *
 * - shop_orders gains the resources the client currently pays for on top of the offer, the baseline they started from,
 *   and their monthly cost (so it does not have to be worked out on every page).
 * - shop_resource_changes: one line per change made in the middle of a month, with the part of the month left to pay
 *   (the proration), waiting to be put on the next invoice.
 * - shop_invoices / shop_invoice_lines: the bill made on the first of the month (the recurring cost of the resources
 *   plus the mid-month changes), paid from the credit.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            // What the offer gave when it was bought (memory, disk, cpu, databases, backups, ports): the floor the client
            // cannot go below, and the point from which the extra is counted.
            $table->text('base_resources')->nullable()->after('duration_days');
            // The monthly cost, in cents, of the resources added on top of the offer right now.
            $table->unsignedInteger('resource_cents')->default(0)->after('base_resources');
        });

        Schema::create('shop_resource_changes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('order_id')->index();
            $table->string('description');
            // The part of the current month left to pay for this change (can be negative when going back down).
            $table->integer('amount_cents');
            $table->unsignedInteger('invoice_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('shop_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            // The month the invoice is for, as YYYY-MM.
            $table->string('period', 7);
            $table->string('status', 20)->default('open'); // open, paid, void
            $table->unsignedInteger('total_cents')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period']);
        });

        Schema::create('shop_invoice_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id')->index();
            $table->unsignedInteger('order_id')->nullable();
            $table->string('label');
            $table->integer('amount_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_invoice_lines');
        Schema::dropIfExists('shop_invoices');
        Schema::dropIfExists('shop_resource_changes');
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['base_resources', 'resource_cents']);
        });
    }
};
