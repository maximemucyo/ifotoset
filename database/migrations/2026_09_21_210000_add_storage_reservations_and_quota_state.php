<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_reserved_bytes')->default(0)->after('storage_used_bytes');
            $table->boolean('storage_warning_75_active')->default(false)->after('storage_reserved_bytes');
            $table->boolean('storage_warning_100_active')->default(false)->after('storage_warning_75_active');
            $table->unsignedInteger('storage_warning_generation')->default(1)->after('storage_warning_100_active');
        });

        Schema::create('storage_quota_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedSmallInteger('threshold'); // 75 or 100
            $table->unsignedInteger('generation')->default(1);
            $table->unsignedBigInteger('usage_bytes');
            $table->unsignedBigInteger('limit_bytes');
            $table->string('status', 30)->default('dispatched'); // dispatched, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'threshold', 'generation'], 'uq_user_threshold_generation');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_quota_notifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'storage_reserved_bytes',
                'storage_warning_75_active',
                'storage_warning_100_active',
                'storage_warning_generation',
            ]);
        });
    }
};
