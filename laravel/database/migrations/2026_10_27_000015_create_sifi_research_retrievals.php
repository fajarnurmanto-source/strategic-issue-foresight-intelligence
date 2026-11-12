<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_research_retrievals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('object_id');
            $t->uuid('session_id');
            $t->string('result_key', 80);
            $t->unsignedInteger('object_revision');
            $t->unsignedInteger('revision')->default(1);
            $t->string('state', 40);
            $t->json('result_json');
            $t->string('result_sha256', 64);
            $t->json('document_json')->nullable();
            $t->string('document_sha256', 64)->nullable();
            $t->uuid('candidate_id')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->foreign('object_id')->references('id')->on('sifi_monitoring_objects')->restrictOnDelete();
            $t->foreign('session_id')->references('id')->on('sifi_research_sessions')->restrictOnDelete();
            $t->foreign('candidate_id')->references('id')->on('sifi_issue_candidates')->restrictOnDelete();
            $t->unique(['session_id', 'result_key']);
        });
        Schema::create('sifi_research_retrieval_audit', function (Blueprint $t) {
            $t->id();
            $t->uuid('retrieval_id');
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->string('action', 50);
            $t->json('snapshot_json');
            $t->timestamp('created_at');
            $t->foreign('retrieval_id')->references('id')->on('sifi_research_retrievals')->restrictOnDelete();
            $t->unique(['retrieval_id', 'revision']);
        });
    }

    public function down(): void
    {
        foreach (['sifi_research_retrievals', 'sifi_research_retrieval_audit'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('REFUSE_NONEMPTY_RETRIEVAL_ROLLBACK');
            }
        }Schema::dropIfExists('sifi_research_retrieval_audit');
        Schema::dropIfExists('sifi_research_retrievals');
    }
};
