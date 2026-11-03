<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // Where the subject sits in the cover, 0..1 from the left and top. Null keeps the centre.
            $table->decimal('cover_focal_x', 4, 3)->nullable()->after('has_explicit_cover');
            $table->decimal('cover_focal_y', 4, 3)->nullable()->after('cover_focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['cover_focal_x', 'cover_focal_y']);
        });
    }
};
