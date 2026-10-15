<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('expiry_reminder_7d_sent_at')->nullable();
            $table->timestamp('expiry_reminder_1d_sent_at')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['expiry_reminder_7d_sent_at', 'expiry_reminder_1d_sent_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'starts_at']);
            $table->dropColumn('reminder_sent_at');
        });
    }
};
