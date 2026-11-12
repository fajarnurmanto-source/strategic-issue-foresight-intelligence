<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_source_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('source_type', 32);
            $table->string('homepage_url', 500)->nullable();
            $table->string('source_geography', 180)->nullable();
            $table->text('notes')->nullable();
            $table->string('rights_state', 32)->default('UNASSESSED');
            $table->string('collector_state', 12)->default('OFF');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('sifi_coverage_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('level', 20);
            $table->string('region_name', 180);
            $table->json('domains_json');
            $table->string('profile_state', 20)->default('DRAFT');
            $table->string('collector_state', 12)->default('OFF');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('sifi_coverage_source_drafts', function (Blueprint $table) {
            $table->uuid('profile_id');
            $table->uuid('source_id');
            $table->primary(['profile_id', 'source_id']);
            $table->foreign('profile_id')->references('id')->on('sifi_coverage_profiles')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('sifi_source_drafts')->cascadeOnDelete();
        });
        Schema::create('sifi_catalog_audit', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);
            $table->uuid('subject_id');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->unsignedInteger('revision');
            $table->string('action', 28);
            $table->text('snapshot_json');
            $table->timestamp('created_at');
            $table->unique(['subject_type', 'subject_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_catalog_audit');
        Schema::dropIfExists('sifi_coverage_source_drafts');
        Schema::dropIfExists('sifi_coverage_profiles');
        Schema::dropIfExists('sifi_source_drafts');
    }
};
