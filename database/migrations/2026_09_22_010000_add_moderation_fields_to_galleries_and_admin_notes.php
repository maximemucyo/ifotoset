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
        // 1. Add moderation fields to galleries
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('moderation_status', 50)->default('normal')->index()->after('visibility');
            $table->timestamp('taken_down_at')->nullable()->after('moderation_status');
            $table->unsignedBigInteger('taken_down_by')->nullable()->after('taken_down_at');
            $table->text('takedown_reason')->nullable()->after('taken_down_by');
            $table->timestamp('restored_at')->nullable()->after('takedown_reason');
            $table->unsignedBigInteger('restored_by')->nullable()->after('restored_at');

            $table->foreign('taken_down_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('restored_by')->references('id')->on('users')->nullOnDelete();
        });

        // 2. Create admin_notes table (retained independently if user is soft-deleted)
        Schema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('admin_user_id')->index();
            $table->text('content');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('admin_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 3. Ensure composite indexes for activity timeline performance
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['gallery_id', 'created_at'], 'idx_activity_logs_gallery_created');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->index(['target_type', 'target_id', 'created_at'], 'idx_audit_logs_target_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_target_created');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_logs_gallery_created');
        });

        Schema::dropIfExists('admin_notes');

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['taken_down_by']);
            $table->dropForeign(['restored_by']);
            $table->dropColumn([
                'moderation_status',
                'taken_down_at',
                'taken_down_by',
                'takedown_reason',
                'restored_at',
                'restored_by',
            ]);
        });
    }
};
