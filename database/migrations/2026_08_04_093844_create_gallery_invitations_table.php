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
        Schema::create('gallery_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('email');
            $table->string('token', 64)->unique(); // SHA-256 hash of the invitation token (64 hex characters)
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_invitations');
    }
};
