<?php
namespace App\Services\Sifi;

use App\Contracts\SifiResearchProvider;
use App\Models\{User,SifiMonitoringObject};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;

final class MultiSourceResearchWorkspace
{
    public const PROVIDERS=[CatalogMultiResearchProvider::ID,BulletinMultiResearchProvider::ID];
    public const SCENARIOS=['normal','no_result','quota','timeout','partial','api_fixture','api_no_result','api_quota','api_timeout','api_partial','api_web_2025','multilingual','feed','crossref_live'];
    /** @param array<array-key,mixed> $value */
    public function digest(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    private function fail(string $message):never{throw ValidationException::withMessages(['research'=>$message]);}

    /** Server-owned registry only. External providers are not registered or enabled. */
    /** @return array<string,CatalogMultiResearchProvider|BulletinMultiResearchProvider> */
    public function providers():array{return [CatalogMultiResearchProvider::ID=>new CatalogMultiResearchProvider(),BulletinMultiResearchProvider::ID=>new BulletinMultiResearchProvider()];}

    public function canonical(string $url):string
    {
        $url=app(DiscoveryCatalog::class)->safeUrl($url,true);$p=parse_url($url);
        if(($p['scheme']??'')!=='https'||!str_ends_with($p['host']??'','.sources.invalid')||isset($p['port'])||isset($p['user'])||isset($p['pass']))$this->fail('URL hasil fixture ditolak.');
        $query=[];foreach(explode('&',$p['query']??'') as $part){if($part==='')continue;[$key,$value]=array_pad(explode('=',$part,2),2,'');if(str_starts_with(strtolower(rawurldecode($key)),'utm_'))continue;$query[]=$key.'='.$value;}sort($query);
        return 'https://'.strtolower($p['host']).($p['path']??'/').($query?'?'.implode('&',$query):'');
    }

    /** @param array<string,mixed> $v */
    public function create(User $u,SifiMonitoringObject $object,array $v):string
    {
        if($v['scenario']==='crossref_live')return app(RealCollectorWorkspace::class)->collect($u,$object,$v);
        return DB::transaction(function()use($u,$object,$v){
            DB::table('users')->where('id',$u->id)->lockForUpdate()->firstOrFail();
            $o=SifiMonitoringObject::whereKey($object->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);
            if($o->state!=='CONFIGURED'||$o->revision!==(int)$v['object_revision'])$this->fail('Objek berubah atau dijeda; muat ulang.');
            $requestHash=$this->digest(['feed_options'=>$v['feed_options']??null,'multilingual_options'=>$v['multilingual_options']??null,'object'=>$o->id,'revision'=>$o->revision,'question'=>$v['question'],'scenario'=>$v['scenario'],'queries'=>$v['query_limit'],'results'=>$v['result_limit']]);
            $existing=DB::table('sifi_research_session_audit')->where('actor_user_id',$u->id)->where('action','MULTI_SEARCH_COMPLETED')->where('snapshot_json->submission_key',$v['submission_key'])->first();
            if($existing){$audit=json_decode($existing->snapshot_json,true);if(($audit['request_sha256']??null)!==$requestHash)$this->fail('Kunci pengiriman sudah dipakai untuk pertanyaan berbeda.');app(ResearchRetrievalWorkspace::class)->session($u,$o,$existing->session_id);return $existing->session_id;}
            $id=app(ResearchSessionWorkspace::class)->create($u,$o,['question'=>$v['question'],'object_revision'=>$o->revision,'query_limit'=>$v['query_limit'],'result_limit'=>$v['result_limit'],'language'=>null,'source_type'=>null,'time_window'=>'UNSPECIFIED']);
            $session=app(ResearchRetrievalWorkspace::class)->session($u,$o,$id);$plan=json_decode($session->plan_json,true,32,JSON_THROW_ON_ERROR);
            if($v['scenario']==='feed'){
                $options=($v['feed_options']??[])+['sources'=>FeedSourcePack::IDS,'pages'=>2,'language'=>'ALL','region'=>'ALL','period'=>'ALL','provider_state'=>'normal'];
                $plan['feed']=['options'=>$options,'query_plan'=>app(MultilingualQueryPlan::class)->build($o,$plan,['question'=>$v['question'],'start_date'=>$options['start_date']??null,'end_date'=>$options['end_date']??null])];
                DB::table('sifi_research_sessions')->where('id',$id)->update(['plan_json'=>json_encode($plan,JSON_THROW_ON_ERROR)]);
            }
            if($v['scenario']==='multilingual'){$plan['multilingual']=app(MultilingualQueryPlan::class)->build($o,$plan,($v['multilingual_options']??[])+['question'=>$v['question']]);DB::table('sifi_research_sessions')->where('id',$id)->update(['plan_json'=>json_encode($plan,JSON_THROW_ON_ERROR)]);}
            if(str_starts_with($v['scenario'],'api_'))SearchWireFixture::install($v['scenario']);$envelope=$this->execute($o,$plan,$v['scenario'],['owner_id'=>$u->id,'object_id'=>$o->id,'session_id'=>$id,'object_revision'=>$o->revision]);
            $envelope['insights']=app(ResearchSignalInsights::class)->summarize($envelope);
            $results=json_decode($session->results_json,true,32,JSON_THROW_ON_ERROR);$results['multi_source']=['state'=>'DRAFT','envelope'=>$envelope,'envelope_sha256'=>$this->digest($envelope),'saved_at'=>null,'analyst_note'=>null,'selected_ids'=>[]];
            DB::table('sifi_research_sessions')->where('id',$id)->update(['results_json'=>json_encode($results,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
            app(\App\Services\Sifi\Integration\ConnectorObservationProjector::class)->record($u,$o,$id,$envelope);
            $this->audit($u,$id,'MULTI_SEARCH_COMPLETED',['submission_key'=>$v['submission_key'],'request_sha256'=>$requestHash,'envelope_sha256'=>$this->digest($envelope),'network_requests'=>0,'documents_retrieved'=>0]);return $id;
        });
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $binding
     * @return array<string,mixed>
     */
    public function execute(SifiMonitoringObject $object,array $plan,string $scenario,array $binding=[]):array
    {
        if($scenario==='feed')return app(FeedDiscovery::class)->execute($object,$plan,$binding);
        if($scenario==='multilingual')return app(MultilingualDiscovery::class)->execute($object,$plan,$binding);
        if(str_starts_with($scenario,'api_'))return app(RealSearchWorkspace::class)->execute($object,$plan,$binding,$scenario);
        if(!in_array($scenario,self::SCENARIOS,true))$this->fail('Skenario fixture tidak dikenal.');
        $budget=['query_limit'=>min(2,(int)$plan['limits']['query_limit']),'provider_limit'=>2,'simulated_call_limit'=>4,'result_limit'=>min(10,(int)$plan['limits']['result_limit']),'metadata_byte_limit'=>65536,'time_limit_ms'=>1000,'network_request_limit'=>0,'cost_limit'=>0];
        $started=hrtime(true);$entries=[];$seen=[];$calls=[];$usedBytes=0;$stop='COMPLETED';$rawCount=0;
        $providers=$this->providers();if(array_keys($providers)!==self::PROVIDERS)$this->fail('Registry provider berubah.');
        foreach(array_slice($plan['queries'],0,$budget['query_limit']) as $qi=>$query){foreach($providers as $providerId=>$provider){
            if(count($calls)>=$budget['simulated_call_limit']){$stop='SIMULATED_CALL_LIMIT';break 2;}
            if((hrtime(true)-$started)/1e6>=$budget['time_limit_ms']){$stop='TIME_LIMIT';break 2;}
            $state=match($scenario){'quota'=>'QUOTA','timeout'=>'TIMEOUT','partial'=>$providerId===BulletinMultiResearchProvider::ID?'UNAVAILABLE':'OK',default=>'OK'};
            $context=['query'=>$query['query'],'query_sha256'=>$this->digest($query),'question_terms'=>$scenario==='no_result'?['zzznomatchzzz']:$plan['question_terms'],'exclusions'=>$plan['exclusions'],'fixture_state'=>$state];
            try{$packet=$provider->search($context);}catch(\Throwable){$packet=MultiSourceFixtureSupport::packet($providerId,$context+[],[]);$packet['state']='UNAVAILABLE';}
            if((hrtime(true)-$started)/1e6>=$budget['time_limit_ms']){$stop='TIME_LIMIT';$calls[]=['provider'=>$providerId,'query_index'=>$qi,'state'=>'TIME_LIMIT','simulated_call'=>true];break 2;}
            $size=strlen(json_encode($packet,JSON_THROW_ON_ERROR));
            if($size>32768||$usedBytes+$size>$budget['metadata_byte_limit']){$stop='METADATA_BYTE_LIMIT';$calls[]=['provider'=>$providerId,'query_index'=>$qi,'state'=>'BYTE_LIMIT','simulated_call'=>true];break 2;}
            $usedBytes+=$size;
            if(($packet['provider']??null)!==$providerId||($packet['query_sha256']??null)!==$context['query_sha256']||($packet['version']??null)!=='sifi-multi-search-1'||($packet['network_requests']??null)!==0||($packet['cost']??null)!==0||!in_array($packet['state']??null,['OK','QUOTA','TIMEOUT','UNAVAILABLE'],true)||!is_array($packet['results']??null)||!array_is_list($packet['results'])||count($packet['results'])>20)$this->fail('Provenance/batas provider tidak valid.');
            if($packet['state']!=='OK'&&$packet['results'])$this->fail('Provider gagal tidak boleh mengirim hasil.');
            $calls[]=['provider'=>$providerId,'query_index'=>$qi,'query'=>$query['query'],'query_sha256'=>$context['query_sha256'],'state'=>$packet['state'],'response_sha256'=>$this->digest($packet),'fixture_metadata_bytes'=>$size,'simulated_call'=>true,'fixture_loaded_at'=>$packet['fixture_loaded_at'],'retrieved_at'=>null];
            foreach($packet['results'] as $entry){
                app(DiscoveryCatalog::class)->assertDescriptor($entry);$canonical=$this->canonical($entry['url']);$identity=hash('sha256',$canonical);
                foreach(['title'=>300,'snippet'=>1500,'origin'=>100,'key'=>80] as $field=>$limit)if(!is_string($entry[$field]??null)||mb_strlen($entry[$field])>$limit)$this->fail('Field hasil melewati batas.');
                $rawCount++;$seen[$identity]=true;$lineage=['provider'=>$providerId,'query_index'=>$qi,'result_key'=>$entry['key'],'url'=>$entry['url'],'origin'=>$entry['origin'],'snippet'=>$entry['snippet'],'response_sha256'=>$this->digest($packet)];
                if(isset($entries[$identity])){$entries[$identity]['lineage'][]=$lineage;$entries[$identity]['origins']=array_values(array_unique([...$entries[$identity]['origins'],$entry['origin']]));continue;}
                if(count($entries)>=$budget['result_limit']){$stop='RESULT_LIMIT';continue;}
                $hay=$entry['title'].' '.$entry['snippet'].' '.implode(' ',$entry['topics']).' '.($entry['geography']??'');
                $hits=array_values(array_intersect($plan['question_terms'],MultiSourceFixtureSupport::words($hay)));
                $contextTerms=array_values(array_diff(MultiSourceFixtureSupport::words($object->name.' '.implode(' ',$object->aliases_json).' '.implode(' ',$object->keywords_json)),['synthetic','fiktif','fixture']));
                $contextHits=array_values(array_intersect($contextTerms,MultiSourceFixtureSupport::words($hay)));
                $entries[$identity]=['id'=>$identity,'title'=>$entry['title'],'url'=>$entry['url'],'canonical_url'=>$canonical,'snippet'=>$entry['snippet'],'origins'=>[$entry['origin']],'lineage'=>[$lineage],'published_at'=>$entry['published_at'],'retrieved_at'=>null,'fixture_loaded_at'=>now()->toIso8601String(),'rights_state'=>'SYNTHETIC_TEST_ONLY_LIVE_UNASSESSED','retrieval_allowed'=>false,'evidence_admission'=>'NONE','snippet_sha256'=>hash('sha256',$entry['snippet']),'relevance'=>['score'=>count($hits)*3+count($contextHits),'question_hits'=>$hits,'context_hits'=>$contextHits]];
            }
        }}
        $entries=array_values($entries);usort($entries,fn($a,$b)=>($b['relevance']['score']<=>$a['relevance']['score'])?:strcmp($a['id'],$b['id']));
        foreach($entries as &$entry){$entry['relevance_group']=$entry['relevance']['context_hits']?'QUESTION_AND_OBJECT_MATCH':'QUESTION_MATCH';$entry['origin_state']=count($entry['origins'])>1?'AMBIGUOUS':'DECLARED_NOT_VERIFIED';}unset($entry);
        if($stop==='COMPLETED'){
            $failed=(bool)array_filter($calls,fn($call)=>$call['state']!=='OK');
            if($failed)$stop=$entries?'PARTIAL_COVERAGE':'NO_RESULTS_PROVIDER_ERRORS';
            elseif(!$entries)$stop='NO_RESULTS';
        }
        return ['version'=>'sifi-multi-research-1','mode'=>'SYNTHETIC_METADATA_ONLY','entries'=>$entries,'comparisons'=>$this->compare($entries),'calls'=>$calls,'plan_sha256'=>$this->digest($plan),'provider_ids'=>self::PROVIDERS,'budget'=>$budget,'usage'=>['simulated_calls'=>count($calls),'fixture_metadata_bytes'=>$usedBytes,'network_requests'=>0,'documents_retrieved'=>0,'cost'=>0],'raw_result_count'=>$rawCount,'unique_result_count'=>count($entries),'collapsed_hits'=>max(0,$rawCount-count($seen)),'omitted_unique_count'=>count($seen)-count($entries),'stopping_reason'=>$stop,'coverage'=>'Dua provider fixture .invalid, bukan pencarian internet. Tanggal yang tidak diketahui tetap UNKNOWN; coverage tidak lengkap.','independence'=>'NOT_VERIFIED','claim_verification'=>'NOT_PERFORMED_METADATA_ONLY','ai'=>'OFF','fixture_scenario'=>$scenario];
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<array<string,mixed>>
     */
    private function compare(array $entries):array
    {
        $pairs=[];foreach($entries as $i=>$a){foreach(array_slice($entries,$i+1) as $b){
            $left=MultiSourceFixtureSupport::words($a['snippet']);$right=MultiSourceFixtureSupport::words($b['snippet']);$sameOrigin=(bool)array_intersect($a['origins'],$b['origins']);
            $pairs[]=['left'=>$a['id'],'right'=>$b['id'],'shared_terms'=>array_slice(array_values(array_intersect($left,$right)),0,12),'left_only_terms'=>array_slice(array_values(array_diff($left,$right)),0,12),'right_only_terms'=>array_slice(array_values(array_diff($right,$left)),0,12),'possible_republication'=>$sameOrigin||$a['snippet_sha256']===$b['snippet_sha256'],'shared_declared_origin'=>$sameOrigin,'independence'=>'NOT_VERIFIED','conclusion'=>'Perbandingan kata pada snippet, bukan verifikasi fakta atau bukti dokumen.'];
        }}return $pairs;
    }

    /** @param array<string,mixed> $v */
    public function save(User $u,SifiMonitoringObject $o,string $id,array $v):void
    {
        $v['analyst_note']=(string)($v['analyst_note']??'');
        DB::transaction(function()use($u,$o,$id,$v){
            $o=SifiMonitoringObject::whereKey($o->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);if($o->state!=='CONFIGURED'||$o->revision!==(int)$v['object_revision'])$this->fail('Objek berubah atau dijeda.');
            $session=app(ResearchRetrievalWorkspace::class)->session($u,$o,$id);$session=DB::table('sifi_research_sessions')->where('id',$session->id)->lockForUpdate()->firstOrFail();
            $results=json_decode($session->results_json,true,32,JSON_THROW_ON_ERROR);$multi=$results['multi_source']??null;if(!$multi)$this->fail('Sesi bukan penelitian multi-sumber.');
            if(!hash_equals($multi['envelope_sha256'],$this->digest($multi['envelope']))||!hash_equals($multi['envelope_sha256'],$v['envelope_sha256']))$this->fail('Hasil berubah atau integritas gagal.');
            $ids=array_values($v['selected_ids']);sort($ids);$allowed=array_column($multi['envelope']['entries'],'id');if(array_diff($ids,$allowed))$this->fail('Pilihan bukan hasil sesi ini.');
            if($multi['state']==='SAVED'){if($multi['analyst_note']===$v['analyst_note']&&$multi['selected_ids']===$ids)return;$this->fail('Penelitian sudah tersimpan; buat pertanyaan baru untuk revisi.');}
            if((int)$session->revision!==(int)$v['revision'])$this->fail('Revisi sesi berubah.');
            $multi['state']='SAVED';$multi['saved_at']=now()->toIso8601String();$multi['analyst_note']=$v['analyst_note'];$multi['selected_ids']=$ids;$results['multi_source']=$multi;
            DB::table('sifi_research_sessions')->where('id',$id)->update(['revision'=>(int)$session->revision+1,'results_json'=>json_encode($results,JSON_THROW_ON_ERROR),'updated_at'=>now()]);$this->audit($u,$id,'MULTI_RESEARCH_SAVED',['envelope_sha256'=>$multi['envelope_sha256'],'selected_ids'=>$ids,'evidence_admission'=>'NONE']);
        });
    }

    /** @return list<array<string,mixed>> */
    public function history(User $u,SifiMonitoringObject $o):array
    {
        return array_values(array_filter(array_map(function($s){if($s['expired'])return $s+['multi'=>null];$m=$s['results']['multi_source']??null;if(!$m)return null;if(!hash_equals($m['envelope_sha256'],$this->digest($m['envelope'])))$this->fail('Integritas riwayat gagal.');return ['id'=>$s['id'],'question'=>$s['question'],'revision'=>$s['revision'],'created_at'=>$s['created_at'],'expires_at'=>$s['expires_at'],'expired'=>false,'multi'=>$m];},app(ResearchSessionWorkspace::class)->history($u,$o))));
    }
    /** @param array<string,mixed> $snapshot */
    private function audit(User $u,string $id,string $action,array $snapshot):void{DB::table('sifi_research_session_audit')->insert(['session_id'=>$id,'actor_user_id'=>$u->id,'action'=>$action,'snapshot_json'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now()]);}
}
