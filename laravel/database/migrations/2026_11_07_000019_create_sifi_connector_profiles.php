<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_connector_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_key', 64);
            $table->string('label', 100);
            $table->string('kind', 24);
            $table->string('endpoint', 255);
            $table->string('auth_scheme', 24);
            $table->text('credential_ciphertext')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(0);
            $table->unsignedBigInteger('max_cost_micros')->default(0);
            $table->boolean('enabled')->default(false);
            $table->boolean('billing_authorized')->default(false);
            $table->string('rights_status', 24)->default('UNASSESSED');
            $table->string('connection_status', 24)->default('NOT_TESTED');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['kind', 'provider_key']);
        });

        Schema::create('sifi_connector_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('profile_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('event_code', 48);
            $table->timestamps();
            $table->index(['profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_connector_audits');
        Schema::dropIfExists('sifi_connector_profiles');
    }
};