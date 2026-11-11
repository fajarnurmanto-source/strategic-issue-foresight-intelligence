<?php
namespace App\Services\Sifi;

use App\Models\{User,SifiMonitoringObject};
use App\Contracts\SifiResearchProvider;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResearchRetrievalWorkspace
{
    private function fail(string $message):never{throw ValidationException::withMessages(['research'=>$message]);}
    /** @param array<array-key, mixed> $value */
    private function digest(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}

    public function session(User $user,SifiMonitoringObject $object,string $id):\stdClass
    {
        Gate::forUser($user)->authorize('view',$object);
        $row=DB::table('sifi_research_sessions')->where('id',$id)->where('user_id',$user->id)->where('object_id',$object->id)->first();if($row===null)abort(404);
        if(now()->gte($row->expires_at))$this->fail('Sesi riset kedaluwarsa.');
        if((int)$row->object_revision!==$object->revision)$this->fail('Konteks objek berubah. Buat sesi baru.');
        return $row;
    }

    /** @return array<string, mixed> */
    public function search(User $user,SifiMonitoringObject $object,string $session):array
    {
        $row=$this->session($user,$object,$session);$plan=json_decode($row->plan_json,true,32,JSON_THROW_ON_ERROR);$results=json_decode($row->results_json,true,32,JSON_THROW_ON_ERROR);
        $catalogHash=hash_file('sha256',resource_path('sifi/source-discovery-catalog.json'));
        if($catalogHash===false || !hash_equals($plan['provenance']['catalog_sha256'],$catalogHash))$this->fail('Katalog berubah. Buat sesi baru.');
        $context=['query_sha256'=>$this->digest($plan),'entries'=>$results['entries']];
        // Interface is swappable in tests; product resolver remains fixed to local fixture.
        $provider=app()->bound(SifiResearchProvider::class)?app(SifiResearchProvider::class):new FixtureResearchProvider();
        return $provider->search($context)+['session_id'=>$row->id,'object_revision'=>$object->revision];
    }

    /** @param array<string, mixed> $v */
    public function decide(User $u,SifiMonitoringObject $o,array $v):void
    {
        DB::transaction(function()use($u,$o,$v){
            $o=SifiMonitoringObject::whereKey($o->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);
            if($o->state!=='CONFIGURED'||$o->revision!==(int)$v['object_revision'])$this->fail('Objek berubah atau dijeda.');
            $session=$this->session($u,$o,$v['session_id']);$envelope=$this->search($u,$o,$session->id);
            if(!hash_equals($envelope['query_sha256'],$v['query_sha256']))$this->fail('Digest rencana berubah.');
            $results = $envelope['results'] ?? null;
            if (!is_array($results)) $this->fail('Daftar hasil sesi tidak valid.');
            $entry = null;
            foreach ($results as $candidate) {
                if (is_array($candidate) && ($candidate['key'] ?? null) === $v['result_key']) {
                    $entry = $candidate;
                    break;
                }
            }
            if($entry===null)$this->fail('Hasil tidak ada pada sesi ini.');
            $existing=DB::table('sifi_research_retrievals')->where('session_id',$session->id)->where('result_key',$entry['key'])->first();if($existing)return;
            if(DB::table('sifi_research_retrievals')->where('user_id',$u->id)->count()>=100)$this->fail('Batas 100 keputusan akun tercapai.');
            $id=(string)Str::uuid();$result=$entry+['session_id'=>$session->id,'query_sha256'=>$envelope['query_sha256'],'provider'=>$envelope['provider'],'object_revision'=>$o->revision];
            DB::table('sifi_research_retrievals')->insert(['id'=>$id,'user_id'=>$u->id,'object_id'=>$o->id,'session_id'=>$session->id,'result_key'=>$entry['key'],'object_revision'=>$o->revision,'revision'=>1,'state'=>'DECISION_DRAFT','result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'result_sha256'=>$this->digest($result),'expires_at'=>$session->expires_at,'created_at'=>now(),'updated_at'=>now()]);
            $this->audit($id,$u,1,'DECISION_DRAFT',['query_sha256'=>$envelope['query_sha256'],'result_sha256'=>$this->digest($result)]);
        });
    }

    public function locked(User $u,SifiMonitoringObject $o,string $id,int $revision,int $objectRevision):\stdClass
    {
        $object=SifiMonitoringObject::whereKey($o->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$object);
        if($object->state!=='CONFIGURED'||$object->revision!==$objectRevision)$this->fail('Objek berubah atau dijeda.');
        $r=DB::table('sifi_research_retrievals')->where('id',$id)->where('user_id',$u->id)->where('object_id',$o->id)->lockForUpdate()->first();if($r===null)abort(404);
        if((int)$r->revision!==$revision||(int)$r->object_revision!==$objectRevision||now()->gte($r->expires_at))$this->fail('Keputusan berubah/kedaluwarsa. Muat ulang.');
        if(!hash_equals($r->result_sha256,hash('sha256',$r->result_json)))$this->fail('Integritas hasil gagal.');
        return $r;
    }

    /** @param array<string, mixed> $v */
    public function snapshot(User $u,SifiMonitoringObject $o,string $id,array $v):void
    {
        DB::transaction(function()use($u,$o,$id,$v){$r=$this->locked($u,$o,$id,(int)$v['revision'],(int)$v['object_revision']);
            if($r->state!=='DECISION_DRAFT')$this->fail('Snapshot sudah ada; tidak diambil ulang.');
            $entry=json_decode($r->result_json,true,32,JSON_THROW_ON_ERROR);app(DiscoveryCatalog::class)->assertDescriptor($entry);
            // Self-authored fixture text is separate from the provider snippet, with its own hash/span.
            $body='[SYNTHETIC DOCUMENT / NOT VERIFIED] '.$entry['title']."\n".'Pernyataan simulasi: status pengembangan belum dapat diverifikasi. Tidak ada temuan dunia nyata.';
            $quote='status pengembangan belum dapat diverifikasi';$start=strpos($body,$quote);
            if(strlen($body)>4096||$start===false)$this->fail('Batas dokumen fixture.');
            $doc=['mode'=>'SELF_AUTHORED_DOCUMENT_FIXTURE','url'=>$entry['url'],'body'=>$body,'bytes'=>strlen($body),'body_sha256'=>hash('sha256',$body),'parser_version'=>'utf8-fixture-span-1','retrieved_at'=>null,'fixture_loaded_at'=>now()->toIso8601String(),'published_at'=>null,'quote'=>['text'=>$quote,'byte_start'=>$start,'byte_end'=>$start+strlen($quote)],'rights'=>'SYNTHETIC_TEST_ONLY','live_rights'=>'UNASSESSED','network_requests'=>0,'evidence_admission'=>'NOT_EVALUATED','claim_verified'=>false,'query_sha256'=>$entry['query_sha256'],'session_id'=>$r->session_id,'object_revision'=>(int)$r->object_revision];
            DB::table('sifi_research_retrievals')->where('id',$id)->update(['document_json'=>json_encode($doc,JSON_THROW_ON_ERROR),'document_sha256'=>$this->digest($doc),'state'=>'FIXTURE_SNAPSHOT','revision'=>2,'updated_at'=>now()]);$this->audit($id,$u,2,'FIXTURE_SNAPSHOT',['document_sha256'=>$this->digest($doc),'body_sha256'=>$doc['body_sha256']]);
        });
    }

    /** @return array<string, mixed> */
    public function pipelinePreview(User $u,string $id):array
    {
        $r=DB::table('sifi_research_retrievals')->where('id',$id)->where('user_id',$u->id)->first();if($r===null)abort(404);
        $object=SifiMonitoringObject::whereKey($r->object_id)->firstOrFail();Gate::forUser($u)->authorize('update',$object);
        if($object->state!=='CONFIGURED'||$object->revision!==(int)$r->object_revision)$this->fail('Objek berubah atau dijeda.');
        if(now()->gte($r->expires_at)||!$r->document_json||!hash_equals((string)$r->document_sha256,hash('sha256',$r->document_json))||!hash_equals($r->result_sha256,hash('sha256',$r->result_json)))$this->fail('Snapshot tidak tersedia atau integritas gagal.');
        $doc=json_decode($r->document_json,true,32,JSON_THROW_ON_ERROR);$q=$doc['quote'];
        if(!hash_equals($doc['body_sha256'],hash('sha256',$doc['body']))||substr($doc['body'],$q['byte_start'],$q['byte_end']-$q['byte_start'])!==$q['text'])$this->fail('Span/hash dokumen tidak cocok.');
        $result=json_decode($r->result_json,true,32,JSON_THROW_ON_ERROR);$idHash=hash('sha256','ae14|'.$r->object_id.'|'.$r->session_id.'|'.$doc['body_sha256']);
        return ['mode'=>'SYNTHETIC_FIXTURE_ONLY','connector'=>'research_fixture','status'=>'READY_FOR_ANALYST_DRAFT','reason'=>null,'packet_hash'=>$doc['body_sha256'],'source'=>['publisher'=>'SIFI self-authored fixture','documentation_url'=>$doc['url'],'attribution'=>'SIFI self-authored SYNTHETIC document; no external publication','limitation'=>'SYNTHETIC / NOT VERIFIED. Tidak ada sumber nyata, dokumen internet, hak live, corroboration atau evidence admission.','rights_state'=>'UNASSESSED','collector_state'=>'OFF'],'observations'=>[['id'=>$idHash,'description'=>'Kutipan dokumen sintetis: '.$q['text'],'valid_at'=>'UNKNOWN','issued_at'=>'UNKNOWN','provenance'=>['document'=>$doc,'research_session_id'=>$r->session_id,'object_id'=>$r->object_id,'object_revision'=>(int)$r->object_revision,'result_identity'=>$result['result_identity'],'synthetic'=>true,'independence_verified'=>false]]],'duplicates'=>[],'conflicts'=>[],'changes'=>[],'candidate_reason'=>'Telaah dokumen sintetis; belum ada bukti kondisi aktual.','evidence_admission'=>'NOT_EVALUATED','claim_verified'=>false];
    }

    /** @param array<string, mixed> $v */
    public function import(User $u,SifiMonitoringObject $o,string $id,array $v):void
    {
        DB::transaction(function()use($u,$o,$id,$v){$r=$this->locked($u,$o,$id,(int)$v['revision'],(int)$v['object_revision']);if($r->candidate_id)return;if($r->state!=='FIXTURE_SNAPSHOT')$this->fail('Buat snapshot fixture terlebih dahulu.');
            $this->pipelinePreview($u,$id);$candidate=app(SourceObservationPipeline::class)->createDraft($u,'research_fixture',$v['analyst_note'],null,null,$id);
            DB::table('sifi_research_retrievals')->where('id',$id)->update(['candidate_id'=>$candidate->id,'state'=>'DRAFT_LINKED','revision'=>3,'updated_at'=>now()]);$this->audit($id,$u,3,'DRAFT_LINKED',['candidate_id'=>$candidate->id]);
        });
    }

    /** @param array<array-key, mixed> $snapshot */
    private function audit(string $id,User $u,int $revision,string $action,array $snapshot):void{DB::table('sifi_research_retrieval_audit')->insert(['retrieval_id'=>$id,'actor_user_id'=>$u->id,'revision'=>$revision,'action'=>$action,'snapshot_json'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now()]);}

    /** @return list<array<string, mixed>> */
    public function history(User $u,SifiMonitoringObject $o):array
    {
        Gate::forUser($u)->authorize('view',$o);return array_values(DB::table('sifi_research_retrievals')->where('user_id',$u->id)->where('object_id',$o->id)->orderByDesc('created_at')->limit(30)->get()->map(function($r){$expired=now()->gte($r->expires_at);return ['id'=>$r->id,'revision'=>(int)$r->revision,'state'=>$r->state,'candidate_id'=>$r->candidate_id,'expired'=>$expired,'result'=>$expired?null:json_decode($r->result_json,true),'document'=>$expired?null:json_decode($r->document_json??'null',true)];})->values()->all());
    }
}
