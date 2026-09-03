<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('public_key')->nullable()->after('id');
        });

        foreach (DB::table('users')->orderBy('id')->lazyById() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'public_key' => (string) Str::uuid(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('public_key')->nullable(false)->change();
            $table->unique('public_key');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('public_key')->nullable()->after('id');
        });

        foreach (DB::table('conversations')->orderBy('id')->lazyById() as $conversation) {
            DB::table('conversations')->where('id', $conversation->id)->update([
                'public_key' => (string) Str::uuid(),
            ]);
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('public_key')->nullable(false)->change();
            $table->unique('public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });
    }
};
