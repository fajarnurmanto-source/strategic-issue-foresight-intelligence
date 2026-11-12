<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_evidence_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users');
            $table->string('signal_id', 64); // existing offline fixture reference, not an admitted evidence ID
            $table->text('claim_text');
            $table->text('rationale')->nullable();
            $table->text('known_gaps')->nullable();
            $table->string('relationship', 16)->default('UNKNOWN');
            $table->string('workflow_state', 24)->default('DRAFT');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['candidate_id', 'signal_id']);
            $table->index(['owner_user_id', 'updated_at']);
            $table->index(['reviewer_user_id', 'workflow_state']);
        });
        Schema::create('sifi_evidence_draft_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('evidence_id')->constrained('sifi_evidence_drafts')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->unsignedInteger('revision');
            $table->string('action', 32);
            $table->text('snapshot_json');
            $table->timestamp('created_at');
            $table->unique(['evidence_id', 'revision']);
        });
        Schema::create('sifi_evidence_review_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('evidence_id')->constrained('sifi_evidence_drafts')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users');
            $table->unsignedInteger('evidence_revision');
            $table->string('review_finding', 32);
            $table->text('review_notes');
            $table->timestamp('created_at');
            $table->unique(['evidence_id', 'evidence_revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_evidence_review_notes');
        Schema::dropIfExists('sifi_evidence_draft_audit');
        Schema::dropIfExists('sifi_evidence_drafts');
    }
};
