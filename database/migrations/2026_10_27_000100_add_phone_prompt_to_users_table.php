<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('phone');
            // Last time the owner closed the "add a phone number" prompt; it comes back later
            $table->timestamp('phone_prompt_dismissed_at')->nullable()->after('phone');
        });

        // Phones are now stored in E.164. This runs against the live code during deploy, before
        // libphonenumber is installed, so only the common Rwandan formats are rewritten here.
        DB::table('users')->whereNotNull('phone')->select(['id', 'phone'])->orderBy('id')->each(function ($user) {
            $digits = preg_replace('/\D+/', '', $user->phone);

            $phone = match (true) {
                (bool) preg_match('/^07\d{8}$/', $digits) => '+250' . substr($digits, 1),
                (bool) preg_match('/^2507\d{8}$/', $digits) => '+' . $digits,
                default => null,
            };

            if ($phone && $phone !== $user->phone) {
                DB::table('users')->where('id', $user->id)->update(['phone' => $phone]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn('phone_prompt_dismissed_at');
        });
    }
};
