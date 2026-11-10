<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sifi_issue_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('investigation_id')->unique()->constrained('sifi_investigations')->cascadeOnDelete();
            $table->foreignId('user_id')->index();
            $table->string('title', 180);
            $table->text('issue_hypothesis')->nullable();
            $table->text('exposure_notes')->nullable();
            $table->text('evidence_gaps')->nullable();
            $table->text('next_checks')->nullable();
            $table->string('review_state', 24)->default('DRAFT');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('sifi_issue_candidate_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('actor_user_id');
            $table->unsignedInteger('revision');
            $table->string('action', 32);
            $table->text('snapshot_json');
            $table->timestamp('created_at');
            $table->unique(['candidate_id', 'revision']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sifi_issue_candidate_audit');
        Schema::dropIfExists('sifi_issue_candidates');
    }
};
