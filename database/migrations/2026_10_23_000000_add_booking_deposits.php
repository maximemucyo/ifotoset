<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Deposit owed at booking time, copied from the package so later package edits don't change it
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('price');
            // null = no deposit taken online; pending | paid | failed | expired
            $table->string('deposit_status', 20)->nullable()->after('deposit_amount');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_status');
            // While a deposit is being paid the slot is held until this time
            $table->timestamp('hold_expires_at')->nullable()->after('deposit_paid_at')->index();
        });

        // Booking deposits are not tied to a subscription plan
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['hold_expires_at']);
            $table->dropColumn(['deposit_amount', 'deposit_status', 'deposit_paid_at', 'hold_expires_at']);
        });
    }
};
