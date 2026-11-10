<?php
namespace App\Services\Sifi;

/** Shared metadata-only fixture boundary. Contains no transport, credentials or DNS. */
final class MultiSourceFixtureSupport
{
    public static function words(string $text): array
    {
        return array_slice(array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($text))?:[],fn($v)=>mb_strlen($v)>=2))),0,80);
    }

    public static function matches(array $entry,array $context): bool
    {
        $text=mb_strtolower($entry['title'].' '.$entry['snippet'].' '.implode(' ',$entry['topics']).' '.($entry['geography']??''));
        $words=self::words($text);
        foreach($context['exclusions'] as $term)if(mb_stripos($text,mb_strtolower($term))!==false)return false;
        return (bool)array_intersect($context['question_terms'],$words);
    }

    public static function packet(string $provider,array $context,array $entries): array
    {
        $state=$context['fixture_state'];
        return ['version'=>'sifi-multi-search-1','provider'=>$provider,'query'=>$context['query'],
            'query_sha256'=>$context['query_sha256'],'state'=>$state,'results'=>$state==='OK'?$entries:[],
            'mode'=>'SELF_AUTHORED_SYNTHETIC_FIXTURE','network_requests'=>0,'documents_retrieved'=>0,
            'retrieved_at'=>null,'fixture_loaded_at'=>now()->toIso8601String(),'cost'=>0,'fallback'=>'NONE'];
    }
}
