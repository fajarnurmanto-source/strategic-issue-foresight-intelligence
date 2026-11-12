<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_monitoring_objects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('name', 180);
            $t->text('description');
            $t->string('category', 100);
            $t->json('aliases_json');
            $t->json('keywords_json');
            $t->json('exclusions_json');
            $t->string('geography', 180)->nullable();
            $t->text('objective');
            $t->string('horizon', 160);
            $t->string('state', 20)->default('CONFIGURED');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->index(['user_id', 'updated_at']);
        });
        Schema::create('sifi_monitoring_object_sources', function (Blueprint $t) {
            $t->uuid('object_id');
            $t->uuid('source_id');
            $t->primary(['object_id', 'source_id']);
            $t->foreign('object_id')->references('id')->on('sifi_monitoring_objects')->restrictOnDelete();
            $t->foreign('source_id')->references('id')->on('sifi_source_drafts')->restrictOnDelete();
        });
        Schema::create('sifi_monitoring_object_audit', function (Blueprint $t) {
            $t->id();
            $t->uuid('object_id');
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->string('action', 30);
            $t->json('snapshot_json');
            $t->timestamp('created_at');
            $t->foreign('object_id')->references('id')->on('sifi_monitoring_objects')->restrictOnDelete();
            $t->unique(['object_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_monitoring_object_audit');
        Schema::dropIfExists('sifi_monitoring_object_sources');
        Schema::dropIfExists('sifi_monitoring_objects');
    }
};
