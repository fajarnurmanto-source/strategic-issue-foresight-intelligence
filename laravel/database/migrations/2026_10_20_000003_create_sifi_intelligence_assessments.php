<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_intelligence_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('candidate_id')->constrained('sifi_issue_candidates')->cascadeOnDelete();
            $table->foreignId('actor_user_id');
            $table->unsignedInteger('candidate_revision');
            $table->char('input_digest', 64);
            $table->string('status', 24)->default('ADVISORY_ONLY');
            $table->text('result_json');
            $table->timestamp('created_at');
            $table->unique(['candidate_id', 'input_digest'], 'sifi_advisory_candidate_input_unique');
            $table->index(['candidate_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_intelligence_assessments');
    }
};
