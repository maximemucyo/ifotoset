<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quick share links: a link to a few chosen photos, which opens without the gallery's password or invitation
        Schema::create('gallery_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['gallery_id', 'created_at']);
        });

        Schema::create('gallery_share_link_photos', function (Blueprint $table) {
            $table->foreignId('share_link_id')->constrained('gallery_share_links')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();

            $table->primary(['share_link_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_share_link_photos');
        Schema::dropIfExists('gallery_share_links');
    }
};
