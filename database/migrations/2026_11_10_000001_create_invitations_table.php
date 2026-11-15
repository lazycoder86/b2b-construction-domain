<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            // SHA-256 of the token sent by email; the plain token is never stored.
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
        });

        // At most one open invitation per email per organization.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX invitations_pending_unique ON invitations (organization_id, lower(email)) WHERE accepted_at IS NULL');
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('require_two_factor')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('require_two_factor');
        });

        Schema::dropIfExists('invitations');
    }
};
