<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sifi_foresight_drafts',function(Blueprint $t):void {
   $t->uuid('id')->primary();
   $t->foreignUuid('trajectory_id')->unique()->constrained('sifi_trajectory_drafts')->cascadeOnDelete();
   $t->foreignId('owner_user_id')->constrained('users');
   foreach(['baseline_scenario','alternative_scenario','stress_scenario','critical_drivers',
     'falsification_checks','decision_options','uncertainty_note'] as $col)$t->text($col);
   $t->unsignedInteger('revision')->default(1);$t->timestamps();
  });
  Schema::create('sifi_foresight_audit',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('draft_id')->constrained('sifi_foresight_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->unsignedInteger('revision');
   $t->string('action',36);$t->text('snapshot_json');$t->timestamp('created_at');
   $t->unique(['draft_id','revision']);
  });
  Schema::create('sifi_foresight_advisories',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('draft_id')->constrained('sifi_foresight_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->unsignedInteger('draft_revision');
   $t->char('input_digest',64);$t->string('status',24)->default('ADVISORY_ONLY');
   $t->text('result_json');$t->timestamp('created_at');
   $t->unique(['draft_id','input_digest']);
  });
 }
 public function down():void {
  Schema::dropIfExists('sifi_foresight_advisories');
  Schema::dropIfExists('sifi_foresight_audit');
  Schema::dropIfExists('sifi_foresight_drafts');
 }
};
