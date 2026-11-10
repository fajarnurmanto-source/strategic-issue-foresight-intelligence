<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sifi_trajectory_drafts',function(Blueprint $t):void {
   $t->uuid('id')->primary();
   $t->foreignUuid('case_id')->unique()->constrained('sifi_issue_case_drafts')->cascadeOnDelete();
   $t->foreignId('owner_user_id')->constrained('users');
   $t->string('horizon',20)->default('UNSPECIFIED');
   foreach(['trajectory_hypothesis','risk_hypothesis','opportunity_hypothesis','watch_condition','uncertainty_note'] as $col) $t->text($col);
   $t->unsignedInteger('revision')->default(1);$t->timestamps();
  });
  Schema::create('sifi_trajectory_audit',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('draft_id')->constrained('sifi_trajectory_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->unsignedInteger('revision');
   $t->string('action',30);$t->text('snapshot_json');$t->timestamp('created_at');
   $t->unique(['draft_id','revision']);
  });
  Schema::create('sifi_trajectory_advisories',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('draft_id')->constrained('sifi_trajectory_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->unsignedInteger('draft_revision');
   $t->char('input_digest',64);$t->string('status',24)->default('ADVISORY_ONLY');
   $t->text('result_json');$t->timestamp('created_at');
   $t->unique(['draft_id','input_digest']);
  });
 }
 public function down():void {
  Schema::dropIfExists('sifi_trajectory_advisories');
  Schema::dropIfExists('sifi_trajectory_audit');
  Schema::dropIfExists('sifi_trajectory_drafts');
 }
};
