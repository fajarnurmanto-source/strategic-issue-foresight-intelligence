<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sifi_executive_briefs',function(Blueprint $t):void {
   $t->uuid('id')->primary();$t->foreignUuid('candidate_id')->unique()->constrained('sifi_issue_candidates')->cascadeOnDelete();
   $t->foreignId('owner_user_id')->constrained('users');
   $t->string('title',180);$t->string('audience',120);
   foreach(['executive_summary','decision_questions','verification_priorities','limitations'] as $f)$t->text($f);
   $t->string('review_state',20)->default('DRAFT');$t->char('input_digest',64);
   $t->longText('input_snapshot_json');$t->unsignedInteger('revision')->default(1);$t->timestamps();
  });
  Schema::create('sifi_executive_brief_audit',function(Blueprint $t):void {
   $t->id();$t->foreignUuid('brief_id')->constrained('sifi_executive_briefs')->cascadeOnDelete();
   $t->foreignId('actor_user_id')->constrained('users');$t->unsignedInteger('revision');
   $t->string('action',36);$t->longText('snapshot_json');$t->timestamp('created_at');
   $t->unique(['brief_id','revision']);
  });
 }
 public function down():void {Schema::dropIfExists('sifi_executive_brief_audit');Schema::dropIfExists('sifi_executive_briefs');}
};
