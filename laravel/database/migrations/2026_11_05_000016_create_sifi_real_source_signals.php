<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sifi_real_source_signals', function (Blueprint $table): void {
            $table->string('id',64)->primary();
            $table->foreignUuid('session_id')->unique()->constrained('sifi_research_sessions')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->foreignUuid('object_id')->constrained('sifi_monitoring_objects');
            $table->unsignedInteger('object_revision');
            $table->string('source_url',1000);
            $table->string('source_sha256',64);
            $table->string('image_sha256',64);
            $table->string('brief_sha256',64);
            $table->string('provenance_mode',48)->default('REAL_SOURCE_SNAPSHOT');
            $table->string('review_state',40)->default('NOT_EVALUATED');
            $table->timestamp('created_at');
            $table->index(['owner_user_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('sifi_real_source_signals'); }
};
