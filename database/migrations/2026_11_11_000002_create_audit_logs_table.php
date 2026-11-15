<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only record of security-relevant actions. Actor details are
        // snapshotted so entries stay meaningful after a user is deleted.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Null for platform-level events (logins, admin actions without an org).
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            // Set when a platform admin acted while impersonating user_id.
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Internal staff with access to /admin. Never mass-assignable; grant via `php artisan admin:grant`.
            $table->boolean('is_platform_admin')->default(false)->after('current_organization_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('deletion_scheduled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('deletion_scheduled_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
        Schema::dropIfExists('audit_logs');
    }
};
