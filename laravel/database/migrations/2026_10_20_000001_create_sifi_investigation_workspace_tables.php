<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel-owned, local analyst workflow state. No writes to SIFI Python data.
        Schema::create('sifi_investigations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('title', 180);
            $table->string('review_state', 24)->default('DRAFT');
            $table->text('claim_hypothesis')->nullable();
            $table->text('evidence_gaps')->nullable();
            $table->text('next_steps')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('sifi_investigation_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('investigation_id')->constrained('sifi_investigations')->cascadeOnDelete();
            $table->string('signal_id', 64);
            $table->foreignId('added_by_user_id');
            $table->timestamp('created_at');
            $table->unique(['investigation_id', 'signal_id']);
        });
        Schema::create('sifi_investigation_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('investigation_id')->constrained('sifi_investigations')->cascadeOnDelete();
            $table->foreignId('actor_user_id');
            $table->unsignedInteger('revision');
            $table->string('action', 32);
            $table->text('snapshot_json');
            $table->timestamp('created_at');
            $table->unique(['investigation_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_investigation_audit');
        Schema::dropIfExists('sifi_investigation_signals');
        Schema::dropIfExists('sifi_investigations');
    }
};
