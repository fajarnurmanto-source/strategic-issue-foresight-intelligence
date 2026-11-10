<?php
namespace App\Services\Sifi\Ai;

class ProviderEndpointPolicy
{
    public function validate(string $endpoint, string $protocol): string
    {
        $endpoint=rtrim($endpoint,'/');
        $entry=FreeAiCatalogue::provider($protocol);
        if (!$entry || !$entry['available'] || $endpoint!==$entry['endpoint']) throw new \InvalidArgumentException('ENDPOINT_NOT_ALLOWED');
        return $endpoint;
    }

    private function eligible(array $rule,array $provider,string $model): bool
    {
        $entry=FreeAiCatalogue::provider($provider['protocol']??'');
        return $entry && $entry['available'] && in_array($model,$entry['models'],true)
            && ($rule['owner_id']??null)===($provider['owner_id']??-1)
            && is_int($rule['owner_id']??null) && $rule['owner_id']>0
            && ($rule['provider_id']??null)===($provider['id']??null)
            && ($rule['endpoint']??null)===($provider['endpoint']??null)
            && ($rule['model']??null)===$model
            && ($rule['credential_sha256']??null)===hash('sha256',$provider['key']??'')
            && ($rule['free_verified']??false)===true
            && ($rule['billing_disabled']??false)===true
            && ($rule['paid_fallback']??true)===false
            && ($rule['privacy_class']??null)==='PUBLIC'
            && is_string($rule['account_project']??null) && trim($rule['account_project'])!==''
            && preg_match('/\A[a-f0-9]{64}\z/',$rule['eligibility_evidence_sha256']??'')===1
            && ($rule['verified_at']??0)>time()-900 && ($rule['verified_at']??PHP_INT_MAX)<=time()
            && ($rule['expires_at']??0)>time() && $rule['expires_at']<=($rule['verified_at']+900)
            && (($provider['protocol']??'')!=='ollama' || ($rule['local_cloud_disabled']??false)===true);
    }

    public function freeStatus(array $provider,string $model): string
    {
        $entry=FreeAiCatalogue::provider($provider['protocol']??'');
        if (($entry['status']??null)==='PAID') return 'PAID';
        if (!$entry || !$entry['available'] || !in_array($model,$entry['models'],true)) return 'UNAVAILABLE';
        $path=storage_path('app/private/sifi-ai-free-grants.json');
        try {
            $rules=is_file($path)?json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR):[];
            foreach ($rules as $rule) if ($this->eligible($rule,$provider,$model)) return 'FREE VERIFIED';
        } catch (\Throwable) { /* Missing/corrupt evidence never establishes eligibility. */ }
        return 'FREE ELIGIBILITY UNKNOWN';
    }

    public function authorize(array $provider,string $model,string $purpose): void
    {
        if (($provider['protocol']??'')==='chat') throw new \RuntimeException('PAID_MODEL_REJECTED');
        $this->validate($provider['endpoint'],$provider['protocol']);
        $entry=FreeAiCatalogue::provider($provider['protocol']);
        if (!in_array($model,$entry['models'],true)) throw new \RuntimeException('MODEL_NOT_ALLOWED');
        if ($purpose!=='probe') throw new \RuntimeException('PUBLIC_SYNTHETIC_ONLY');
        // Separate from old paid grants. This file is server-installed after specific approval.
        $path=storage_path('app/private/sifi-ai-free-grants.json');
        $handle=is_file($path)?fopen($path,'r+'):false;
        if (!$handle) throw new \RuntimeException('FREE_ELIGIBILITY_UNKNOWN');
        try {
            if (!flock($handle,LOCK_EX)) throw new \RuntimeException('GRANT_LOCK_FAILED');
            $rules=json_decode(stream_get_contents($handle),true,16,JSON_THROW_ON_ERROR);
            foreach ($rules as &$rule) {
                if (!$this->eligible($rule,$provider,$model)) continue;
                if (($rule['used']??true)!==false || ($rule['max_requests']??0)!==1) throw new \RuntimeException('GRANT_EXHAUSTED');
                // Reserve the single attempt before network I/O. Timeout/failure never refunds it.
                $rule['used']=true; $rule['used_at']=time();
                $json=json_encode($rules,JSON_THROW_ON_ERROR);
                rewind($handle);
                if (fwrite($handle,$json)!==strlen($json) || !ftruncate($handle,strlen($json)) || !fflush($handle)) throw new \RuntimeException('GRANT_WRITE_FAILED');
                return;
            }
            throw new \RuntimeException('FREE_ELIGIBILITY_UNKNOWN');
        } finally { flock($handle,LOCK_UN); fclose($handle); }
    }

    public function probeAvailable(array $provider,string $model): bool
    {
        $path=storage_path('app/private/sifi-ai-free-grants.json');
        try {
            $rules=is_file($path)?json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR):[];
            foreach ($rules as $rule) if ($this->eligible($rule,$provider,$model) && ($rule['used']??true)===false && ($rule['max_requests']??0)===1) return true;
        } catch (\Throwable) { }
        return false;
    }
}
