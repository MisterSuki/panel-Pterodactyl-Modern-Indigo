<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * A notification someone removed from their bell. An announcement is for everyone: it is only hidden for that person.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('panel_notification_reads', 'hidden_at')) {
            Schema::table('panel_notification_reads', function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('panel_notification_reads', 'hidden_at')) {
            Schema::table('panel_notification_reads', function (Blueprint $table) {
                $table->dropColumn('hidden_at');
            });
        }
    }
};
