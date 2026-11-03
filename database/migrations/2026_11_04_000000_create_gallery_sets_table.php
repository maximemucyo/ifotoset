<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sets split a gallery into parts such as "Ceremony" and "Reception". A photo is in at most one.
        Schema::create('gallery_sets', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['gallery_id', 'sort_order']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('set_id')->nullable()->after('gallery_id')->constrained('gallery_sets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('set_id');
        });
        Schema::dropIfExists('gallery_sets');
    }
};
