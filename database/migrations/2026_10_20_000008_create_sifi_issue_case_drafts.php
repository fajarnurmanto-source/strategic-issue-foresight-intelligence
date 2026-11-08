<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sifi_issue_case_drafts',function(Blueprint $t):void {
   $t->uuid('id')->primary();
   $t->foreignUuid('candidate_id')->unique()->constrained('sifi_issue_candidates')->cascadeOnDelete();
   $t->foreignId('owner_user_id')->constrained('users');
   $t->foreignUuid('coverage_profile_id')->nullable()->constrained('sifi_coverage_profiles')->nullOnDelete();
   $t->string('title',180);$t->string('issue_domain',120);
   $t->string('geography_label',180)->default('UNKNOWN');
   $t->text('scope_note');
   $t->string('lifecycle_state',25)->default('DRAFT');
   $t->string('verification_state',24)->default('NOT_VERIFIED');
   $t->unsignedInteger('revision')->default(1);$t->timestamps();
   $t->index(['owner_user_id','updated_at']);
  });
  Schema::create('sifi_issue_case_audit',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('case_id')->constrained('sifi_issue_case_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');
   $t->unsignedInteger('revision');$t->string('action',30);$t->text('snapshot_json');$t->timestamp('created_at');
   $t->unique(['case_id','revision']);
  });
  Schema::create('sifi_issue_case_advisories',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('case_id')->constrained('sifi_issue_case_drafts')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->char('input_digest',64);
   $t->unsignedInteger('case_revision');$t->string('status',24)->default('ADVISORY_ONLY');
   $t->text('result_json');$t->timestamp('created_at');
   $t->unique(['case_id','input_digest']);
  });
 }
 public function down():void {
  Schema::dropIfExists('sifi_issue_case_advisories');
  Schema::dropIfExists('sifi_issue_case_audit');
  Schema::dropIfExists('sifi_issue_case_drafts');
 }
};
