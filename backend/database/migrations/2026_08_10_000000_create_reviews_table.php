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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('quote');
            $table->integer('rating')->default(5);
            $table->string('detail')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
