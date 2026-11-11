<?php

namespace App\Services\Sifi;

use App\Models\{User,SifiSourceDraft};
use GuzzleHttp\Psr7\{StreamDecoratorTrait,Utils};
use Illuminate\Support\Facades\{Crypt,Gate,Http,Log};
use Illuminate\Support\Str;
use Psr\Http\Message\{ResponseInterface,StreamInterface};

/** One fixed acquisition, encrypted owner approval consumed before I/O. No retry/scheduler. */
class WorldBankOneShotAcquisition
{
    public const MAX_BYTES=65536;
    public const CONNECT_SECONDS=2;
    public const TOTAL_SECONDS=5;
    public const SCENARIOS=['wb_acquisition'=>'World Bank · satu pengambilan terkontrol (izin terpisah)'];

    private function authorize(User $user):void
    {
        abort_unless($user->hasVerifiedEmail(),403);
        Gate::forUser($user)->authorize('viewAny',SifiSourceDraft::class);
    }
    public function permitPath():string {return storage_path('app/private/sifi-worldbank-one-shot-permit.enc');}
    /** @return array<string,mixed> */
    private function rights():array {
        $contents=file_get_contents(resource_path('sifi/ae09-wb-rights.json'));
        if($contents===false)throw new \RuntimeException('RIGHTS_METADATA_UNAVAILABLE');
        $rights=json_decode($contents,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($rights))throw new \RuntimeException('RIGHTS_METADATA_INVALID');
        return $rights;
    }
    /** @param array<string,mixed> $rights */
    private function rightsFailure(array $rights):?string
    {
        // Reuse the closed decoder's rights gate; this local proof is not a new retrieval.
        $raw=file_get_contents(resource_path('sifi/ae09-wb-response.json'));
        if($raw===false)throw new \RuntimeException('RIGHTS_PROOF_UNAVAILABLE');
        $meta=['url'=>WorldBankGdpSourceAdapter::ENDPOINT,'status'=>200,'content_type'=>'application/json','sha256'=>hash('sha256',$raw),'retrieved_at'=>now()->utc()->format('Y-m-d\TH:i:s\Z')];
        $p=app(WorldBankGdpSourceAdapter::class)->normalizeResponse($raw,$meta,$rights);
        return $p['status']==='READY_FOR_ANALYST_DRAFT'?null:$p['reason'];
    }
    /** @return array<string,mixed>|null */
    protected function permit(User $user,bool $consume):?array
    {
        $path=$this->permitPath();
        if(!is_file($path)||is_link($path)||filesize($path)>16384)return null;
        $handle=fopen($path,$consume?'r+':'r');if(!$handle)return null;
        try {
            if(!flock($handle,$consume?LOCK_EX:LOCK_SH))return null;
            $encoded=stream_get_contents($handle,16385);if($encoded===false||strlen($encoded)>16384)return null;
            try {$p=json_decode(Crypt::decryptString($encoded),true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){return null;}
            if(!is_array($p)||!$this->permitMatchesContext($p)||($p['version']??null)!==1||($p['owner_id']??null)!==(int)$user->id||($p['consumed']??null)!==false
                ||($p['endpoint']??null)!==WorldBankGdpSourceAdapter::ENDPOINT||($p['max_records']??null)!==3||($p['max_bytes']??null)!==self::MAX_BYTES
                ||($p['connect_seconds']??null)!==2||($p['total_seconds']??null)!==5||($p['retries']??null)!==0||($p['scheduler']??null)!==false
                ||!is_string($p['scope_sha256']??null)||!preg_match('/\A[0-9a-f]{64}\z/',$p['scope_sha256'])
                ||!is_string($p['approval_ref']??null)||!str_starts_with($p['approval_ref'],($p['kind']??null)==='MOCK_TEST_ONLY'?'AUTOMATED_MOCK_TEST:':'HUMAN_EXACT_ONE_GET:')
                ||!is_int($p['expires_at']??null)||$p['expires_at']<now()->timestamp||$p['expires_at']>(int)now()->timestamp+600
                ||!in_array($p['kind']??null,['OWNER_APPROVED_EXTERNAL_GET','MOCK_TEST_ONLY'],true))return null;
            if($p['kind']==='MOCK_TEST_ONLY'&&!app()->environment('testing'))return null;
            if($consume){$p['consumed']=true;$p['consumed_at']=now()->utc()->toIso8601String();$cipher=Crypt::encryptString(json_encode($p,JSON_THROW_ON_ERROR));
                rewind($handle);if(!ftruncate($handle,0)||fwrite($handle,$cipher)!==strlen($cipher)||!fflush($handle))throw new \RuntimeException('PERMIT_CONSUMPTION_FAILED');
                if(function_exists('fsync'))fsync($handle);
            }
            return $p;
        } finally {flock($handle,LOCK_UN);fclose($handle);}
    }
    /** @param array<string,mixed> $permit */
    protected function permitMatchesContext(array $permit):bool{return true;}
    public function canAcquire(User $user):bool
    {
        $this->authorize($user);
        return extension_loaded('curl')&&$this->rightsFailure($this->rights())===null&&$this->permit($user,false)!==null;
    }
    /** @return array<string,mixed> */
    public function emptyPreview(string $reason='ONE_GET_APPROVAL_REQUIRED'):array
    {
        return ['connector'=>'wb_acquisition','mode'=>'ACQUISITION_NOT_PERFORMED','status'=>'BLOCKED','reason'=>$reason,
            'source'=>['id'=>WorldBankGdpSourceAdapter::SOURCE_ID,'publisher'=>'World Bank WDI — endpoint belum dipanggil','endpoint'=>WorldBankGdpSourceAdapter::ENDPOINT,
                'rights_state'=>'REVIEW_REQUIRED_BEFORE_EXTERNAL_GET','limitation'=>'Tidak ada pengambilan tanpa izin satu GET tersendiri. Arsip AE09 tetap tersedia terpisah.','collector_state'=>'OFF'],
            'packet_hash'=>hash('sha256','NO_ACQUISITION'),'observations'=>[],'duplicates'=>[],'conflicts'=>[],'changes'=>[],'candidate_reason'=>null,'pages_processed'=>0];
    }
    protected function supportsLocalMock():bool {return false;}
    /** @param array<string,mixed> $options */
    protected function request(string $endpoint,array $options):\Illuminate\Http\Client\Response
    {
        return Http::withOptions($options)->connectTimeout(self::CONNECT_SECONDS)->timeout(self::TOTAL_SECONDS)->get($endpoint);
    }
    /** @return array<string,mixed> */
    public function acquire(User $user,string $endpoint=WorldBankGdpSourceAdapter::ENDPOINT):array
    {
        $this->authorize($user);
        if($endpoint!==WorldBankGdpSourceAdapter::ENDPOINT)return $this->emptyPreview('UNAUTHORIZED_ENDPOINT');
        $rights=$this->rights();if($failure=$this->rightsFailure($rights))return $this->emptyPreview($failure);
        if(!extension_loaded('curl'))return $this->emptyPreview('BOUNDED_CURL_TRANSPORT_REQUIRED');
        $permit=$this->permit($user,true);if(!$permit)return $this->emptyPreview();
        if($permit['kind']==='MOCK_TEST_ONLY'&&!$this->supportsLocalMock())return $this->emptyPreview('MOCK_TRANSPORT_REQUIRED');
        if($permit['kind']!=='MOCK_TEST_ONLY'&&$this->supportsLocalMock())return $this->emptyPreview('MOCK_ACCEPTS_MOCK_PERMISSION_ONLY');
        $started=(int) hrtime(true);$headers=[];$attempts=1;
        $buffer=fopen('php://temp/maxmemory:65536','w+b');
        if($buffer===false)throw new \RuntimeException('ACQUISITION_BUFFER_UNAVAILABLE');
        $stream=new class(Utils::streamFor($buffer)) implements StreamInterface {
            use StreamDecoratorTrait;
            private StreamInterface $stream;
            public function __construct(StreamInterface $stream){$this->stream=$stream;}
            public int $maxBufferedBytes=0;
            public function write(string $string):int {
                if($this->tell()+strlen($string)>WorldBankOneShotAcquisition::MAX_BYTES)throw new \LengthException('AE10_BODY_LIMIT');
                $written=$this->stream->write($string);$this->maxBufferedBytes=max($this->maxBufferedBytes,$this->tell());return $written;
            }
        };
        // Audited installed CurlFactory::retryFailedRewind rejects immediately at counter2.
        // Fresh HTTP/1.1 connection also avoids pooled-connection/HTTP2 replay paths.
        $options=['_curl_retries'=>2,'transport_sharing'=>'none','version'=>'1.1','curl'=>[CURLOPT_FRESH_CONNECT=>true,CURLOPT_FORBID_REUSE=>true],'allow_redirects'=>false,'verify'=>true,'proxy'=>'','decode_content'=>false,'http_errors'=>false,'sink'=>$stream,
            'headers'=>['Accept'=>'application/json','Accept-Encoding'=>'identity','User-Agent'=>'SIFI-Controlled-OneShot/AE10'],
            'protocols'=>['https'],
            'on_headers'=>function(ResponseInterface $response)use(&$headers):void {
                $status=$response->getStatusCode();$headers=['status'=>$status,'content_type'=>$response->getHeaderLine('Content-Type')];
                if($status>=300&&$status<400)throw new \RuntimeException('AE10_REDIRECT_BLOCKED');
                if($status===429)throw new \RuntimeException('AE10_RATE_LIMITED');
                if($status!==200)throw new \RuntimeException('AE10_HTTP_FAILURE');
                if(!preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/i',$headers['content_type']))throw new \RuntimeException('AE10_CONTENT_TYPE');
                $encoding=$response->getHeaderLine('Content-Encoding');if($encoding!==''&&strtolower($encoding)!=='identity')throw new \RuntimeException('AE10_CONTENT_ENCODING');
                $length=$response->getHeaderLine('Content-Length');
                if($length!==''&&(!ctype_digit($length)||(float)$length>self::MAX_BYTES))throw new \LengthException('AE10_BODY_LIMIT');
            }];
        try {
            $response=$this->request($endpoint,$options);
            if(((int) hrtime(true)-$started)/1e9>self::TOTAL_SECONDS)throw new \RuntimeException('AE10_TIMEOUT');
            if($response->status()!==200||$headers===[])throw new \RuntimeException('AE10_HTTP_FAILURE');
            $stream->rewind();$raw=$stream->getContents();
            $meta=['url'=>$endpoint,'status'=>200,'content_type'=>$headers['content_type'],'retrieved_at'=>now()->utc()->format('Y-m-d\TH:i:s\Z'),'sha256'=>hash('sha256',$raw)];
            $id=$this->newReceiptId($permit);
            $record=['id'=>$id,'owner_id'=>(int)$user->id,'permit_kind'=>$permit['kind'],'approval_scope_sha256'=>$permit['scope_sha256'],
                'raw'=>$raw,'retrieval'=>$meta,'rights'=>$rights,'retention_until'=>now()->utc()->addDays(30)->toIso8601String(),
                'transport'=>['attempts'=>$attempts,'retry_count'=>0,'scheduler'=>false,'bytes'=>strlen($raw),'max_buffered_bytes'=>$stream->maxBufferedBytes,
                    'elapsed_ms'=>(int)(((int) hrtime(true)-$started)/1e6),'connect_timeout_seconds'=>2,'total_timeout_seconds'=>5,'redirects_followed'=>0,
                    'tls_verification'=>$permit['kind']==='MOCK_TEST_ONLY'?'NOT_APPLICABLE_LOOPBACK_HTTP':'ENABLED_REQUIRED','network'=>$permit['kind']==='MOCK_TEST_ONLY'?'LOCAL_TEST_HARNESS_ONLY':'FIXED_HTTPS_WORLD_BANK']];
            $record=$this->decorateRecord($record,$permit);
            $preview=$this->decode($record);if($preview['status']!=='READY_FOR_ANALYST_DRAFT')return $preview+['transport'=>$record['transport']];
            $file=$this->receiptPath($id);$dir=dirname($file);
            if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('RECEIPT_STORAGE_FAILED');
            $cipher=Crypt::encryptString(json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            $this->writeReceipt($file,$cipher);
            Log::info('sifi_source_acquisition',['status'=>'READY','source_id'=>$preview['source']['id'],'mode'=>$preview['mode'],'attempts'=>1,'bytes'=>strlen($raw)]);
            return $preview+['receipt_id'=>$id,'transport'=>$record['transport']];
        } catch(\Throwable $e) {
            $code=($headers['status']??0)===429?'RATE_LIMITED_NO_RETRY':(($headers['status']??0)>=400?'HTTP_FAILURE_NO_RETRY':'NETWORK_DENIED_OR_UNAVAILABLE');
            for($cause=$e;$cause!==null;$cause=$cause->getPrevious()){
                foreach(['BODY_LIMIT'=>'RESPONSE_TOO_LARGE','REDIRECT_BLOCKED'=>'REDIRECT_BLOCKED','RATE_LIMITED'=>'RATE_LIMITED_NO_RETRY','HTTP_FAILURE'=>'HTTP_FAILURE_NO_RETRY','CONTENT_TYPE'=>'CONTENT_TYPE_NOT_SUPPORTED','CONTENT_ENCODING'=>'CONTENT_ENCODING_NOT_SUPPORTED','TIMEOUT'=>'TIMEOUT_NO_RETRY'] as $marker=>$label)
                    if(str_contains($cause->getMessage(),'AE10_'.$marker)){$code=$label;break 2;}
                if($cause instanceof \GuzzleHttp\Exception\ConnectTimeoutException || $cause instanceof \GuzzleHttp\Exception\ResponseTimeoutException || $cause instanceof \GuzzleHttp\Exception\NetworkTimeoutException || preg_match('/cURL error 28:/',$cause->getMessage())){$code='TIMEOUT_NO_RETRY';break;}
                if(method_exists($cause,'getHandlerContext')&&($cause->getHandlerContext()['errno']??null)===28){$code='TIMEOUT_NO_RETRY';break;}
            }
            Log::info('sifi_source_acquisition',['status'=>'BLOCKED','code'=>$code,'attempts'=>1]);
            return $this->emptyPreview($code)+['transport'=>['attempts'=>1,'retry_count'=>0,'max_buffered_bytes'=>$stream->maxBufferedBytes,'elapsed_ms'=>(int)(((int) hrtime(true)-$started)/1e6)]];
        } finally {$stream->close();}
    }
    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    protected function decode(array $record):array
    {
        $preview=app(WorldBankGdpSourceAdapter::class)->normalizeResponse($record['raw'],$record['retrieval'],$record['rights']);
        if($preview['status']!=='READY_FOR_ANALYST_DRAFT')return array_replace($preview,['connector'=>'wb_acquisition']);
        $updated=strtotime($preview['source']['source_updated_at'].'T00:00:00Z');
        if($updated===false)return $this->emptyPreview('SOURCE_UPDATE_REVIEW_REQUIRED');
        if(now()->timestamp-$updated>30*86400)return $this->emptyPreview('SOURCE_UPDATE_REVIEW_REQUIRED');
        $mock=$record['permit_kind']==='MOCK_TEST_ONLY';
        $preview['connector']='wb_acquisition';$preview['mode']=$mock?'MOCK_ACQUISITION_ONLY':'REAL_PUBLIC_ACQUISITION_HISTORICAL';
        $preview['source']['retrieval_kind']=$mock?'LOCAL_MOCK_HTTP_TEST_ONLY':'OWNER_APPROVED_ONE_SHOT';
        $preview['source']['freshness_policy']='LOCAL_30_DAY_REVIEW_NOT_PUBLISHER_RELEASE_SCHEDULE';
        $preview['source']['record_publication_at']=null;$preview['source']['record_publication_state']='UNKNOWN';$preview['source']['current_observation']=false;$preview['source']['observation_currency']='HISTORICAL_PERIOD_NOT_CURRENT';
        $preview['source']['source_review_age_days']=(int)floor((now()->timestamp-$updated)/86400);
        $preview['source']['limitation'].=' Perolehan bukan penerbitan; pembaruan dataset bukan waktu publikasi record. Tidak menyatakan CURRENT_OBSERVATION.';
        if($mock){
            $preview['source']['id']='SRC-SIFI-WB-MOCK';$preview['source']['publisher']='SIMULASI SIFI — local HTTP mock, World Bank schema reference only';
            $preview['source']['attribution']='SIMULASI SIFI; bukan angka atau respons World Bank aktual.';
            $preview['source']['rights_state']='SIFI_SYNTHETIC_TEST_ONLY';$preview['source']['limitation']='MOCK/SYNTHETIC PERMANENT. Tiga nilai uji lokal meniru schema World Bank; bukan respons/angka statistik resmi atau kondisi Indonesia/Yogyakarta. Retrieved-at adalah waktu fetch mock; source-updated adalah metadata simulasi, publikasi record UNKNOWN. Tidak ada CURRENT_OBSERVATION, custody AQ01, verifikasi fakta, admission, forecast atau authority. Rujukan lisensi World Bank hanya untuk metadata/schema sumber; payload mock dibuat SIFI.';
            $preview['candidate_reason']='[MOCK/SIMULASI] Uji satu pengambilan lokal dengan schema World Bank. Bukan statistik resmi, isu terkini, kondisi Yogyakarta, verifikasi atau keputusan.';
            foreach($preview['observations'] as &$o){
                $o['record_key']=hash('sha256','MOCK|'.$o['record_key']);$o['id']=hash('sha256',$o['record_key'].'|'.$o['content_hash']);
                $o['description']='[MOCK/SIMULASI] '.$o['period_label'].': '.$o['value'].'%; nilai uji lokal, bukan angka World Bank.';
                $o['provenance']['synthetic']=true;$o['provenance']['source_id']='SRC-SIFI-WB-MOCK';$o['provenance']['attribution']=$preview['source']['attribution'];
            }unset($o);
            $preview['changes']=[]; // Do not preserve IDs from the real namespace.
        }
        $preview['receipt_id']=$record['id'];$preview['transport']=$record['transport'];
        return $preview;
    }

    /** @param array<string,mixed> $permit */
    protected function newReceiptId(array $permit):string{return (string)Str::uuid();}
    /**
     * @param array<string,mixed> $record
     * @param array<string,mixed> $permit
     * @return array<string,mixed>
     */
    protected function decorateRecord(array $record,array $permit):array{return $record;}
    protected function receiptPath(string $id):string{return storage_path('app/private/sifi-worldbank-acquisitions/'.$id.'.enc');}
    protected function writeReceipt(string $file,string $cipher):void
    {
        if(is_file($file)||is_link($file))throw new \RuntimeException('RECEIPT_EXISTS');
        $tmp=$file.'.tmp-'.Str::random(16);$handle=fopen($tmp,'xb');
        if(!$handle)throw new \RuntimeException('RECEIPT_STORAGE_FAILED');
        try{chmod($tmp,0600);if(fwrite($handle,$cipher)!==strlen($cipher)||!fflush($handle))throw new \RuntimeException('RECEIPT_STORAGE_FAILED');if(function_exists('fsync'))fsync($handle);}finally{fclose($handle);}
        if(!rename($tmp,$file))throw new \RuntimeException('RECEIPT_STORAGE_FAILED');
    }

    /** @return array<string,mixed> */
    public function load(User $user,?string $id):array
    {
        $this->authorize($user);
        if(!is_string($id)||!Str::isUuid($id))return $this->emptyPreview('NO_OWNED_ACQUISITION');
        $path=$this->receiptPath($id);
        if(!is_file($path)||is_link($path)||filesize($path)>262144)return $this->emptyPreview('ACQUISITION_RECEIPT_UNAVAILABLE');
        $encrypted=file_get_contents($path);
        if($encrypted===false)return $this->emptyPreview('ACQUISITION_RECEIPT_INVALID');
        try {$r=json_decode(Crypt::decryptString($encrypted),true,24,JSON_THROW_ON_ERROR);}catch(\Throwable){return $this->emptyPreview('ACQUISITION_RECEIPT_INVALID');}
        if(!is_array($r))return $this->emptyPreview('ACQUISITION_RECEIPT_INVALID');
        if(($r['owner_id']??null)!==(int)$user->id||($r['id']??null)!==$id)return $this->emptyPreview('NO_OWNED_ACQUISITION');
        if(($r['permit_kind']??null)==='MOCK_TEST_ONLY'&&!app()->environment('testing'))return $this->emptyPreview('MOCK_NOT_AVAILABLE');
        if(!in_array($r['permit_kind']??null,['MOCK_TEST_ONLY','OWNER_APPROVED_EXTERNAL_GET'],true))return $this->emptyPreview('ACQUISITION_RECEIPT_INVALID');
        if($failure=$this->rightsFailure($this->rights()))return $this->emptyPreview($failure);
        if(strtotime($r['retention_until']??'')<now()->timestamp)return $this->emptyPreview('ACQUISITION_RETENTION_EXPIRED');
        return $this->decode($r);
    }
}
