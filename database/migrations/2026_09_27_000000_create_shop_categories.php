<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Categories of offers (Minecraft, FiveM, bots...), so that the shop can group what it sells. An offer belongs to one
     * category at most, and stays on sale without one.
     */
    public function up(): void
    {
        Schema::create('shop_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 60);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('shop_offers', function (Blueprint $table) {
            $table->unsignedInteger('category_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shop_offers', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('shop_categories');
    }
};
