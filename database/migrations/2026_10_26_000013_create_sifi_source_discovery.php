<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sifi_discovery_candidates',function(Blueprint $t){
   $t->uuid('id')->primary();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();
   $t->string('identity_sha256',64);$t->json('descriptor_json');$t->string('descriptor_sha256',64);
   $t->uuid('source_draft_id')->nullable();$t->foreign('source_draft_id')->references('id')->on('sifi_source_drafts')->restrictOnDelete();
   $t->timestamps();$t->unique(['user_id','identity_sha256']);
  });
  Schema::create('sifi_discovery_selections',function(Blueprint $t){
   $t->uuid('id')->primary();$t->uuid('object_id');$t->uuid('candidate_id');$t->unsignedInteger('revision')->default(1);
   $t->uuid('registered_source_id')->nullable();$t->foreign('registered_source_id')->references('id')->on('sifi_source_drafts')->restrictOnDelete();$t->unsignedInteger('object_revision');$t->json('context_json');$t->timestamp('expires_at');$t->timestamps();
   $t->foreign('object_id')->references('id')->on('sifi_monitoring_objects')->restrictOnDelete();
   $t->foreign('candidate_id')->references('id')->on('sifi_discovery_candidates')->restrictOnDelete();
   $t->unique(['object_id','candidate_id']);
  });
  Schema::create('sifi_discovery_audit',function(Blueprint $t){
   $t->id();$t->uuid('selection_id');$t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
   $t->unsignedInteger('revision');$t->string('action',40);$t->json('snapshot_json');$t->timestamp('created_at');
   $t->foreign('selection_id')->references('id')->on('sifi_discovery_selections')->restrictOnDelete();$t->unique(['selection_id','revision']);
  });
 }
 public function down():void {Schema::dropIfExists('sifi_discovery_audit');Schema::dropIfExists('sifi_discovery_selections');Schema::dropIfExists('sifi_discovery_candidates');}
};
