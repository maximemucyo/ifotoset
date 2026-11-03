<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One list per client email: their picks, and when they sent them to the photographer
        Schema::create('gallery_favorite_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->string('email', 191);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['gallery_id', 'email']);
        });

        Schema::create('gallery_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('gallery_favorite_lists')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['list_id', 'photo_id']);
        });

        // On for every gallery, as before; no limit unless the photographer sets one
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('favorites_enabled')->default(true)->after('allow_google_photos');
            $table->unsignedSmallInteger('favorites_limit')->nullable()->after('favorites_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['favorites_enabled', 'favorites_limit']);
        });
        Schema::dropIfExists('gallery_favorites');
        Schema::dropIfExists('gallery_favorite_lists');
    }
};
