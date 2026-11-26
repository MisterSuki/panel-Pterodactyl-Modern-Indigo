<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * An offer put forward in the shop and on the home page ("Popular").
     */
    public function up(): void
    {
        if (Schema::hasTable('shop_offers') && !Schema::hasColumn('shop_offers', 'featured')) {
            Schema::table('shop_offers', function (Blueprint $table) {
                $table->boolean('featured')->default(false)->after('enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_offers', 'featured')) {
            Schema::table('shop_offers', fn (Blueprint $table) => $table->dropColumn('featured'));
        }
    }
};
