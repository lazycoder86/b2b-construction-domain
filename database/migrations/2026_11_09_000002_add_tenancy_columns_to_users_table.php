<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique()->after('id');
            $table->foreignId('current_organization_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        DB::table('users')->whereNull('public_id')->orderBy('id')->lazyById()->each(
            fn (object $user) => DB::table('users')
                ->where('id', $user->id)
                ->update(['public_id' => (string) Str::ulid()]),
        );

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
