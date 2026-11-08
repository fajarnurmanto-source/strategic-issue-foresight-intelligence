<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiMonitoringObject};

/** Read-only projection of existing owned research history, never evidence admission. */
final class CollectorObservations
{
 public function forObject(User $u,SifiMonitoringObject $o):array
 {
  $out=[];
  foreach(app(MultiSourceResearchWorkspace::class)->history($u,$o) as $session){
   $m=$session['multi']??null;$e=$m['envelope']??[];
   if(($e['mode']??null)!=='APPROVED_LIVE_CORPUS_METADATA')continue;
   $entries=[];foreach($e['entries']??[] as $x)$entries[]=[
    'id'=>$x['id'],'title'=>$x['original_title'],'url'=>$x['canonical_url'],
    'publisher'=>$x['origin'],'published_at'=>$x['published_at'],'date_precision'=>$x['publication_precision']??'UNKNOWN',
    'event_date'=>$x['event_date']??null,'revision_state'=>$x['revision_state']??'UNKNOWN',
    'source_language'=>$x['source_language']??'UNKNOWN','region'=>$x['region']??'UNKNOWN',
   ];
   $out[]=['session_id'=>$session['id'],'question'=>$session['question'],'state'=>$m['state'],
    'saved_at'=>$m['saved_at'],'receipt'=>$e['collector'],'entries'=>$entries,
    'filtered_count'=>$e['filtered_by_relevance_or_period']??0];
   if(count($out)===5)break;
  }
  return $out;
 }
}
