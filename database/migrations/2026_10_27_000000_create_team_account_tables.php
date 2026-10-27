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
        // A member works inside the owner's studio; the owner (account_id) is never a member row
        Schema::create('account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when the owner's plan no longer has seats for this member
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->string('token_hash', 64)->unique(); // SHA-256 of the emailed token
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('account_members');
    }
};
