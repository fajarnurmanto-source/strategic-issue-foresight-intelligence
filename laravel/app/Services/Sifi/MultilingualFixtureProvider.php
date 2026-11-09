<?php
namespace App\Services\Sifi;
use App\Contracts\SifiResearchProvider;

/** Self-authored multilingual metadata, never actual news or downloaded content. */
final class MultilingualFixtureProvider implements SifiResearchProvider
{
 public function __construct(private string $id){}
 public function search(array $c):array
 {
  $lang=$c['language'];$entity=$c['object_name'];$key=$c['object_key'];$year=(int)substr($c['window']['start']??'2025-01-01',0,4);
  $original=['id'=>['Penelitian layanan publik','Contoh sintetis untuk penelitian layanan publik dan sumber informasi. Ini bukan kejadian nyata.'],'en'=>['Public information research','This synthetic source provides public research context. The example is not a real event.'],'es'=>['Investigación de información pública','Este ejemplo sintético para investigación pública conserva la fuente y el contexto. No es un suceso real.'],'fr'=>['Recherche sur information publique','Ceci est un exemple synthétique pour la recherche publique; la source et le contexte ne sont pas des faits réels.'],'ja'=>['公開情報の調査','これは公開情報と公共サービスの調査用の合成例です。実際の出来事ではありません。']][$lang];
  $rows=[];foreach(['main','later','unknown','regional'] as $i=>$kind){$rows[]=['key'=>$lang.'-'.$kind,'title'=>'[SYNTHETIC] '.$original[0].' · '.$entity,'snippet'=>$original[1],'url'=>'https://languages.sources.invalid/'.$key.'/'.$lang.'/'.$kind.($this->id==='SYNTHETIC_LANGUAGE_B'?'?utm_source=fixture_b':''),'declared_language'=>$lang,'source_type'=>['OFFICIAL','NEWS','ACADEMIC','OPEN_DATA'][$i],'region'=>$kind==='regional'?'WIDER_REGION':'EXACT_OBJECT','scope_note'=>$kind==='regional'?'Broader region; not equivalent to target object.':'Explicit fixture scope for selected object, not verified entity resolution.','origin'=>'SYNTHETIC_ORIGIN_'.$kind,'translation_family'=>'SYNTHETIC_DECLARED_FAMILY_'.$key.'_'.$kind,'syndication_family'=>$kind==='main'?'SYNTHETIC_SYNDICATION_'.$key:null,'published_at'=>match($kind){'main'=>$year.'-06-12','later'=>($year+1).'-02-01',default=>null},'publication_precision'=>$kind==='main'||$kind==='later'?'DAY':'UNKNOWN','event_date'=>$kind==='later'?$year.'-07-01':null,'rights_state'=>'SYNTHETIC_SELF_AUTHORED_TEST_ONLY'];}
  if($c['state']==='no_result')$rows=[];$state=$c['state']==='partial'&&$this->id==='SYNTHETIC_LANGUAGE_B'?'UNAVAILABLE':($c['state']==='quota'?'QUOTA':($c['state']==='timeout'?'TIMEOUT':'OK'));if($state!=='OK')$rows=[];
  return ['version'=>'sifi-multilingual-fixture-1','provider'=>$this->id,'query_sha256'=>$c['query_sha256'],'binding_sha256'=>$c['binding_sha256'],'state'=>$state,'results'=>$rows,'network_requests'=>0,'retrieved_at'=>null,'fixture_loaded_at'=>now()->toIso8601String(),'cost'=>0];
 }
}
