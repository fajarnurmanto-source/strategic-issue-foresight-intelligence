<?php
namespace App\Services\Sifi;
use App\Models\SifiMonitoringObject;

final class MultilingualDiscovery
{
 public const PROVIDERS=['SYNTHETIC_LANGUAGE_A','SYNTHETIC_LANGUAGE_B'];
 /** @param array<string, array<string, mixed>> $entries
  * @param list<string> $languages
  * @return list<array<string, mixed>>
  */
 private function rank(array $entries,array $languages,int $limit):array
 {
  $remaining=array_values($entries);$selected=[];$origins=[];
  $take=function(?string $language=null)use(&$remaining,&$selected,&$origins){$best=null;$bestScore=-PHP_INT_MAX;foreach($remaining as $i=>$e){if($language!==null&&$e['source_language']!==$language)continue;$score=$e['relevance']['score']-4*($origins[$e['origin']]??0);if($best===null||$score>$bestScore||($score===$bestScore&&strcmp($e['id'],$remaining[$best]['id'])<0)){$best=$i;$bestScore=$score;}}if($best===null)return false;$entry=$remaining[$best];unset($remaining[$best]);$entry['relevance']['diversity_adjusted_score']=$bestScore;$entry['relevance']['rank']=count($selected)+1;$selected[]=$entry;$origins[$entry['origin']]=($origins[$entry['origin']]??0)+1;return true;};
  foreach($languages as $language)if(count($selected)<$limit)$take($language);while(count($selected)<$limit&&$take()){}return $selected;
 }
 /** @param array<string, mixed> $e
  * @param array<string, mixed> $window
  */
 public function classifyDate(array $e,array $window):string
 {
  $start=$window['start'];$end=$window['end_exclusive'];$pub=$e['published_at'];$event=$e['event_date'];if(!$start||!$end)return 'WINDOW_UNSPECIFIED';
  if($pub&&$pub>=$start&&$pub<$end)return 'PUBLISHED_IN_WINDOW';if($event&&$event>=$start&&$event<$end&&$pub&&$pub>=$end)return 'LATER_REPORTING_ABOUT_WINDOW';if($event&&$event>=$start&&$event<$end)return 'EVENT_IN_WINDOW';return $pub===null?'PUBLICATION_UNKNOWN':'OUTSIDE_WINDOW';
 }
 /** @param array<string, mixed> $base
  * @param array<string, mixed> $binding
  * @return array<string, mixed>
  */
 public function execute(SifiMonitoringObject $o,array $base,array $binding):array
 {
  $p=$base['multilingual']??throw new \RuntimeException('MULTILINGUAL_PLAN_REQUIRED');$bind=hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR));if(($binding['owner_id']??null)!==$o->user_id||($binding['object_id']??null)!==$o->id||($binding['object_revision']??null)!==$o->revision||empty($binding['session_id']))throw new \RuntimeException('CONTEXT_MISMATCH');
  $start=hrtime(true);$entries=[];$seen=[];$calls=[];$raw=0;$bytes=0;$filtered=['language'=>0,'region'=>0,'period'=>0,'source_type'=>0,'exclusion'=>0];$stop='COMPLETED';$planner=app(MultilingualQueryPlan::class);
  foreach($p['queries'] as $qi=>$q)foreach(self::PROVIDERS as $id){
   if(count($calls)>=$p['limits']['simulated_calls']){$stop='SIMULATED_CALL_LIMIT';break 2;}if((hrtime(true)-$start)/1e6>$p['limits']['time_ms']){$stop='TIME_LIMIT';break 2;}
   $context=['query'=>$q['query'],'query_sha256'=>$q['query_sha256'],'binding_sha256'=>$bind,'language'=>$q['language'],'object_name'=>$o->name,'object_key'=>substr(hash('sha256',$o->id),0,12),'window'=>$p['window'],'state'=>$p['scenario']];$packet=(new MultilingualFixtureProvider($id))->search($context);$size=strlen(json_encode($packet,JSON_THROW_ON_ERROR));
   if($bytes+$size>$p['limits']['metadata_bytes']){$stop='METADATA_BYTE_LIMIT';break 2;}$bytes+=$size;
   if($packet['provider']!==$id||$packet['query_sha256']!==$q['query_sha256']||$packet['binding_sha256']!==$bind||$packet['network_requests']!==0||$packet['cost']!==0||count($packet['results'])>8)throw new \RuntimeException('PROVIDER_CONTRACT');
   $calls[]=['provider'=>$id,'query_index'=>$qi,'query'=>$q['query'],'query_sha256'=>$q['query_sha256'],'language'=>$q['language'],'state'=>$packet['state'],'response_sha256'=>hash('sha256',json_encode($packet,JSON_THROW_ON_ERROR)),'simulated_call'=>true,'retrieved_at'=>null];
   foreach($packet['results'] as $e){
    $hay=$e['title'].' '.$e['snippet'];foreach($p['exclusions'] as $x)if(mb_stripos($hay,$x)!==false){$filtered['exclusion']++;continue 2;}
    $detection=$planner->detect($hay);$language=$detection['code'];$time=$this->classifyDate($e,$p['window']);$f=$p['filters'];
    if($f['language']!=='ALL'&&$f['language']!==$language){$filtered['language']++;continue;}
    if($f['region']!=='ALL'&&$f['region']!==$e['region']){$filtered['region']++;continue;}
    if($f['source_type']!=='ALL'&&$f['source_type']!==$e['source_type']){$filtered['source_type']++;continue;}
    if($f['period']!=='ALL_CONTEXT'&&!(match($f['period']){'PUBLISHED'=>$time==='PUBLISHED_IN_WINDOW','EVENT'=>in_array($time,['EVENT_IN_WINDOW','LATER_REPORTING_ABOUT_WINDOW'],true),'LATER'=>$time==='LATER_REPORTING_ABOUT_WINDOW','UNKNOWN'=>$time==='PUBLICATION_UNKNOWN',default=>false})){$filtered['period']++;continue;}
    $url=app(MultiSourceResearchWorkspace::class)->canonical($e['url']);$key=hash('sha256',$url);$raw++;$seen[$key]=true;
    $lineage=['provider'=>$id,'query_index'=>$qi,'query_sha256'=>$q['query_sha256'],'query_language'=>$q['language'],'binding_sha256'=>$bind,'result_key'=>$e['key'],'url'=>$e['url'],'origin'=>$e['origin'],'snippet'=>$e['snippet'],'original_title'=>$e['title'],'original_language'=>$language,'response_sha256'=>end($calls)['response_sha256']];
    if(isset($entries[$key])){$entries[$key]['lineage'][]=$lineage;continue;}if(count($entries)>=24){$stop='RESULT_LIMIT';continue;}
    $questionHits=array_values(array_intersect(MultiSourceFixtureSupport::words($p['question_original']),MultiSourceFixtureSupport::words($hay)));$entityHits=array_values(array_diff(array_intersect(MultiSourceFixtureSupport::words($o->name),MultiSourceFixtureSupport::words($hay)),['synthetic','fixture','fiktif']));$score=count($entityHits)*3+count($questionHits)+($time==='PUBLISHED_IN_WINDOW'?2:0)+($e['region']==='EXACT_OBJECT'?2:0);
    $entries[$key]=$e+['id'=>$key,'canonical_url'=>$url,'origins'=>[$e['origin']],'origin_state'=>'DECLARED_NOT_VERIFIED','lineage'=>[$lineage],'retrieved_at'=>null,'fixture_loaded_at'=>$packet['fixture_loaded_at'],'retrieval_allowed'=>false,'evidence_admission'=>'NONE','snippet_sha256'=>hash('sha256',$e['snippet']),'source_language'=>$language,'language_detection'=>$detection,'time_classification'=>$time,'original_title'=>$e['title'],'original_snippet'=>$e['snippet'],'derived_translation'=>null,'independence'=>'INDEPENDENCE_NOT_VERIFIED','relevance_group'=>'QUESTION_AND_OBJECT_MATCH','relevance'=>['score'=>$score,'question_hits'=>$questionHits,'context_hits'=>$entityHits,'topic_match'=>'NOT_VERIFIED_CONTEXT_METADATA_ONLY','intent'=>'RESEARCH_CANDIDATE_NOT_ANSWER','geography'=>$e['region']]];
   }
  }
  if(count($entries)>$p['limits']['results'])$stop='RESULT_LIMIT';$entries=$this->rank($entries,$p['requested_languages'],$p['limits']['results']);$pairs=[];foreach($entries as $i=>$a)foreach(array_slice($entries,$i+1) as $b){$cross=$a['source_language']!==$b['source_language'];$family=$a['translation_family']===$b['translation_family'];$same=(bool)array_intersect($a['origins'],$b['origins']);$pairs[]=['left'=>$a['id'],'right'=>$b['id'],'shared_terms'=>$cross?[]:array_values(array_intersect(MultiSourceFixtureSupport::words($a['snippet']),MultiSourceFixtureSupport::words($b['snippet']))),'left_only_terms'=>[],'right_only_terms'=>[],'shared_declared_origin'=>$same,'possible_republication'=>$family||$same,'translated_republication_candidate'=>$cross&&$family,'syndication_candidate'=>$a['syndication_family']!==null&&$a['syndication_family']===$b['syndication_family'],'similar_story_candidate'=>$family,'independence'=>'INDEPENDENCE_NOT_VERIFIED','conclusion'=>$cross?'Teks asli lintas bahasa dipertahankan. Kelompok publikasi ulang hanya metadata deklaratif; bukan verifikasi kesamaan makna atau bukti independen.':'Perbandingan fixture satu bahasa, bukan bukti fakta atau independensi.'];}
  if($stop==='COMPLETED'){$failed=(bool)array_filter($calls,fn($x)=>$x['state']!=='OK');$stop=$failed?($entries?'PARTIAL_COVERAGE':'NO_RESULTS_PROVIDER_ERRORS'):($entries?'COMPLETED':'NO_RESULTS');}
  return ['version'=>'sifi-multilingual-discovery-1','mode'=>'SYNTHETIC_MULTILINGUAL_METADATA_ONLY','entries'=>$entries,'comparisons'=>$pairs,'calls'=>$calls,'multilingual_plan'=>$p,'provider_ids'=>self::PROVIDERS,'plan_sha256'=>hash('sha256',json_encode($base,JSON_THROW_ON_ERROR)),'binding_sha256'=>$bind,'budget'=>['simulated_call_limit'=>$p['limits']['simulated_calls']]+$p['limits'],'usage'=>['simulated_calls'=>count($calls),'fixture_metadata_bytes'=>$bytes,'network_requests'=>0,'documents_retrieved'=>0,'cost'=>0],'raw_result_count'=>$raw,'unique_result_count'=>count($entries),'collapsed_hits'=>max(0,$raw-count($seen)),'omitted_unique_count'=>count($seen)-count($entries),'stopping_reason'=>$stop,'coverage'=>'SYNTHETIC multilingual corpus; bukan internet search. Original language dan period classifications tidak membuktikan kredibilitas atau fakta.','coverage_languages'=>array_values(array_unique(array_column($entries,'source_language'))),'filtered_counts'=>$filtered,'independence'=>'INDEPENDENCE_NOT_VERIFIED','claim_verification'=>'NOT_PERFORMED_METADATA_ONLY','ai'=>'OFF','fixture_scenario'=>'multilingual'];
 }
}
