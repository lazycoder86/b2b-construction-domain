<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Same column name Cashier uses for generic (card-less) trials.
            $table->timestamp('trial_ends_at')->nullable()->after('require_two_factor');
            $table->timestamp('trial_reminder_sent_at')->nullable()->after('trial_ends_at');
            // Sales/support exceptions: {"plan": "...", "features": [...], "limits": {...}}
            $table->json('entitlement_overrides')->nullable()->after('trial_reminder_sent_at');
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 64);
            $table->unsignedBigInteger('quantity');
            $table->timestamp('recorded_at');
            $table->json('metadata')->nullable();

            $table->index(['organization_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'trial_reminder_sent_at', 'entitlement_overrides']);
        });
    }
};
