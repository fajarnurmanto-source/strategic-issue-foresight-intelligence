<?php
namespace App\Services\Sifi\Ai;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

final class ProviderHttpTransport
{
    public function __construct(private ProviderEndpointPolicy $policy){}
    public function request(array $provider,string $path,?array $body,string $model,string $purpose,int $timeoutMs=10000): array
    {
        $protocol=$provider['protocol'];
        $expected=match($protocol){
            'gemini'=>['/models/'.$model.':generateContent'],
            'groq'=>['/chat/completions'],
            'chat'=>['/models','/chat/completions'],
            'ollama'=>['/api/tags','/api/chat'],
            default=>[],
        };
        if (!in_array($path,$expected,true)) throw new \RuntimeException('INVALID_OPERATION');
        // Production operations are exact synthetic bodies, never arbitrary caller payloads.
        $entry=FreeAiCatalogue::provider($protocol);
        if ($entry && $provider['endpoint']===$entry['endpoint']) {
            if ($purpose!=='probe' || $body!==ConfiguredHttpAiAdapter::syntheticBody($protocol,$model)) throw new \RuntimeException('PUBLIC_SYNTHETIC_ONLY');
            if ($protocol!=='ollama' && empty($provider['key'])) throw new \RuntimeException('MISSING_CREDENTIAL');
        }
        $this->policy->authorize($provider,$model,$purpose);
        $client=Http::acceptJson()->connectTimeout(2)->timeout(min(10,max(0.1,$timeoutMs/1000)))->withoutRedirecting()->withOptions([
            'proxy'=>'','stream'=>true,
            'on_headers'=>function(ResponseInterface $response){ if ((int)$response->getHeaderLine('Content-Length')>65536) throw new \RuntimeException('RESPONSE_TOO_LARGE'); },
        ]);
        if ($protocol==='gemini') $client=$client->withHeaders(['x-goog-api-key'=>$provider['key']??'']);
        elseif (in_array($protocol,['chat','groq'],true)) $client=$client->withToken($provider['key']??'');
        // No retry or fallback: a failed attempt consumes its single authorization.
        $response=$body===null?$client->get($provider['endpoint'].$path):$client->post($provider['endpoint'].$path,$body);
        if ($response->status()===429) throw new \RuntimeException('QUOTA_EXHAUSTED');
        if ($response->status()===402) throw new \RuntimeException('BILLING_REQUIRED');
        if (!$response->successful()) throw new \RuntimeException('PROVIDER_UNAVAILABLE');
        $stream=$response->toPsrResponse()->getBody();$raw='';
        while (!$stream->eof()) { $raw.=$stream->read(4096); if (strlen($raw)>65536) { $stream->close(); throw new \RuntimeException('RESPONSE_TOO_LARGE'); } }
        $stream->close();
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('INVALID_RESPONSE');
        return $data;
    }
}
