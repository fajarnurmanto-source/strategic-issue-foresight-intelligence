<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_claim_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignUuid('evidence_id')->constrained('sifi_evidence_drafts')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->text('statement');
            $table->string('attribution', 255)->default('');
            $table->string('modality', 20)->default('UNDETERMINED');
            $table->string('verification_state', 24)->default('NOT_VERIFIED');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['candidate_id', 'created_at']);
        });
        Schema::create('sifi_event_hypotheses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->text('summary');
            $table->string('time_note', 255)->default('');
            $table->string('place_note', 255)->default('');
            $table->string('verification_state', 24)->default('NOT_VERIFIED');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['candidate_id', 'created_at']);
        });
        Schema::create('sifi_claim_event_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignUuid('claim_id')->constrained('sifi_claim_drafts')->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained('sifi_event_hypotheses')->cascadeOnDelete();
            $table->string('relation', 24)->default('UNKNOWN');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->timestamp('created_at');
            $table->unique(['claim_id', 'event_id']);
            $table->index('candidate_id');
        });
        Schema::create('sifi_claim_event_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action', 32);
            $table->string('object_id', 36);
            $table->text('snapshot_json');
            $table->timestamp('created_at');
            $table->index(['candidate_id', 'id']);
        });
        Schema::create('sifi_claim_event_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->unsignedInteger('candidate_revision');
            $table->char('input_digest', 64);
            $table->string('status', 24)->default('ADVISORY_ONLY');
            $table->text('result_json');
            $table->timestamp('created_at');
            $table->unique(['candidate_id', 'input_digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_claim_event_assessments');
        Schema::dropIfExists('sifi_claim_event_audit');
        Schema::dropIfExists('sifi_claim_event_links');
        Schema::dropIfExists('sifi_event_hypotheses');
        Schema::dropIfExists('sifi_claim_drafts');
    }
};
