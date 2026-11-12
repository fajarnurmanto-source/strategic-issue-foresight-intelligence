<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_connector_observations', function (Blueprint $table): void {
            $table->char('id', 64)->primary();
            $table->foreignUuid('session_id')->constrained('sifi_research_sessions')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->foreignUuid('object_id')->constrained('sifi_monitoring_objects');
            $table->unsignedInteger('object_revision');
            $table->char('item_id', 64);
            $table->string('canonical_url', 1000);
            $table->string('title', 300);
            $table->char('snippet_sha256', 64)->nullable();
            $table->string('published_at', 40)->nullable();
            $table->char('origin_labels_sha256', 64);
            $table->json('provider_ids_json');
            $table->string('rights_state', 100);
            $table->string('source_mode', 64);
            $table->string('evidence_admission', 24)->default('NONE');
            $table->timestamp('created_at');
            $table->unique(['session_id', 'item_id']);
            $table->index(['owner_user_id', 'object_id', 'created_at'], 'sifi_connector_observations_owner_object_date');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sifi_connector_observations')
            && DB::table('sifi_connector_observations')->exists()) {
            throw new RuntimeException('IH03_REFUSE_NONEMPTY_OBSERVATION_ROLLBACK');
        }
        Schema::dropIfExists('sifi_connector_observations');
    }
};
