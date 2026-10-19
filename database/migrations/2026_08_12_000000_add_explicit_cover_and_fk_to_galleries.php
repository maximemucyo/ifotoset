<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add has_explicit_cover column
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('has_explicit_cover')->default(false)->after('cover_photo_id');
        });

        // 2. Clean up orphaned cover_photo_id to null before creating the foreign key
        // An orphan is any cover_photo_id that does not exist in the photos table or is soft-deleted.
        DB::table('galleries')
            ->whereNotNull('cover_photo_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('photos')
                    ->whereColumn('photos.id', 'galleries.cover_photo_id')
                    ->whereNull('photos.deleted_at');
            })
            ->update(['cover_photo_id' => null]);

        // 3. Add foreign key constraint with nullOnDelete()
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreign('cover_photo_id')
                ->references('id')
                ->on('photos')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['cover_photo_id']);
            $table->dropColumn('has_explicit_cover');
        });
    }
};
?>
