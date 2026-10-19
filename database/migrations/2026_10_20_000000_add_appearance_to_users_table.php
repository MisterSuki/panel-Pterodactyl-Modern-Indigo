<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * The look each person chose for the panel (accent colour, background), kept as a small JSON text.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'appearance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('appearance')->nullable()->after('language');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'appearance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('appearance');
            });
        }
    }
};
