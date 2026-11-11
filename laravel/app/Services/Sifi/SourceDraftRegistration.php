<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiSourceDraft};
use App\Http\Requests\Sifi\SourceDraftRequest;
use Illuminate\Support\Facades\{DB,Gate,Validator};
use Illuminate\Support\Str;
/** Shared existing candidate registry write, always UNASSESSED / OFF. No rights transition. */
final class SourceDraftRegistration {
 /** @param array<string, mixed> $data */
 public function create(User $user,array $data):SifiSourceDraft {
  Gate::forUser($user)->authorize('create',SifiSourceDraft::class);
  $rules=(new SourceDraftRequest)->rules();$v=Validator::make($data,$rules)->validate();
  return DB::transaction(function()use($user,$v){
   $s=SifiSourceDraft::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>trim($v['name']),'source_type'=>$v['source_type'],'homepage_url'=>$v['homepage_url']??null,'source_geography'=>$v['source_geography']??null,'notes'=>$v['notes']??null,'revision'=>1]);
   DB::table('sifi_catalog_audit')->insert(['subject_type'=>'SOURCE','subject_id'=>$s->id,'actor_user_id'=>$user->id,'revision'=>1,'action'=>'CREATED','snapshot_json'=>json_encode($s->fresh()->only(['name','source_type','homepage_url','source_geography','notes','rights_state','collector_state']),JSON_THROW_ON_ERROR),'created_at'=>now()]);
   return $s->fresh();
  });
 }
}
