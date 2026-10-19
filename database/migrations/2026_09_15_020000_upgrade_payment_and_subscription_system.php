<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upgrade payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('purpose');
            $table->string('provider_transaction_id', 255)->nullable()->after('pawapay_deposit_id');
            $table->string('provider_status', 50)->nullable()->after('status');
            $table->text('failure_reason')->nullable()->after('error_message');
            $table->timestamp('paid_at')->nullable()->after('failure_reason');
            $table->json('metadata')->nullable()->after('paid_at');
        });

        // 2. Upgrade subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('status');
            $table->foreignId('payment_id')->nullable()->after('billing_cycle')->constrained('payments')->onDelete('set null');
            $table->foreignId('assigned_by')->nullable()->after('payment_id')->constrained('users')->onDelete('set null');
            $table->foreignId('revoked_by')->nullable()->after('assigned_by')->constrained('users')->onDelete('set null');
            $table->timestamp('revoked_at')->nullable()->after('cancels_at');
        });

        // 3. Upgrade payment_webhooks table
        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->string('deposit_id', 64)->nullable()->after('event_id')->index();
            $table->string('signature', 512)->nullable()->after('deposit_id');
            $table->string('payload_hash', 64)->nullable()->after('signature');
            $table->string('processing_status', 50)->default('received')->after('payload');
            $table->timestamp('processed_at')->nullable()->after('received_at');
        });

        // 4. Update plans table: make gallery_limit nullable for true unlimited
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('gallery_limit')->nullable()->change();
        });

        // Update plan storage values to decimal commercial standard and gallery_limit = NULL
        DB::table('plans')->where('slug', 'free')->update([
            'storage_limit' => 2147483648, // 2 GB
            'gallery_limit' => null,
        ]);

        DB::table('plans')->where('slug', 'basic')->update([
            'storage_limit' => 50000000000, // 50 GB
            'gallery_limit' => null,
            'monthly_price' => 10999.00,
            'annual_price' => 107988.00,
        ]);

        DB::table('plans')->where('slug', 'pro')->update([
            'storage_limit' => 1000000000000, // 1 TB
            'gallery_limit' => null,
            'monthly_price' => 29999.00,
            'annual_price' => 299988.00,
        ]);

        DB::table('plans')->where('slug', 'business')->update([
            'storage_limit' => 3000000000000, // 3 TB
            'gallery_limit' => null,
            'monthly_price' => 59999.00,
            'annual_price' => 599988.00,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('gallery_limit')->default(0)->change();
        });

        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->dropIndex(['deposit_id']);
            $table->dropColumn(['deposit_id', 'signature', 'payload_hash', 'processing_status', 'processed_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropForeign(['revoked_by']);
            $table->dropColumn(['billing_cycle', 'payment_id', 'assigned_by', 'revoked_by', 'revoked_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'billing_cycle',
                'provider_transaction_id',
                'provider_status',
                'failure_reason',
                'paid_at',
                'metadata',
            ]);
        });
    }
};
