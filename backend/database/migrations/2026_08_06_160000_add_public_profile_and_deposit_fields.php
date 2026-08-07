<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add deposit configuration to packages
        Schema::table('packages', function (Blueprint $table) {
            // deposit_type: none = no deposit required, fixed = flat amount, percentage = % of price
            $table->string('deposit_type', 20)->default('none')->after('is_active');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('deposit_type');
        });

        // Add featured gallery support
        Schema::table('galleries', function (Blueprint $table) {
            // NULL = not featured on profile; integer = featured with given display order
            $table->unsignedTinyInteger('featured_order')->nullable()->default(null)->after('version');
            $table->index('featured_order');
        });

        // Link payments to bookings and distinguish payment purposes
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('bookings')
                ->onDelete('set null');
            $table->string('purpose', 30)->default('subscription')->after('booking_id');

            $table->index('booking_id');
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropIndex(['booking_id']);
            $table->dropIndex(['purpose']);
            $table->dropColumn(['booking_id', 'purpose']);
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['featured_order']);
            $table->dropColumn('featured_order');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['deposit_type', 'deposit_amount']);
        });
    }
};
