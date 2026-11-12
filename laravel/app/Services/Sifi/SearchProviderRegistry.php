<?php

namespace App\Services\Sifi;

use App\Services\Sifi\Integration\CrossrefLocalPermission;
use App\Services\Sifi\Integration\GdeltLocalPermission;

/** Reviewed corpus APIs. None is an open-web index or document-retrieval grant. */
final class SearchProviderRegistry
{
    public const IDS = ['WIKIPEDIA_ID', 'CROSSREF'];

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $entry = match ($id) {
            'WIKIPEDIA_ID' => ['id' => $id, 'endpoint' => 'https://id.wikipedia.org/w/rest.php/v1/search/page', 'host' => 'id.wikipedia.org', 'corpus' => 'Wikipedia bahasa Indonesia', 'rights' => 'CC_BY_SA_4_0_ATTRIBUTION_SHAREALIKE', 'terms' => 'https://foundation.wikimedia.org/wiki/Policy:Terms_of_Use', 'license' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'rate_per_minute' => 60, 'reviewed_on' => '2026-10-29', 'expires_on' => '2026-11-05'],
            'CROSSREF' => ['id' => $id, 'endpoint' => 'https://api.crossref.org/works', 'host' => 'api.crossref.org', 'corpus' => 'Metadata bibliografi Crossref, tanpa abstrak/full text', 'rights' => 'BIBLIOGRAPHIC_METADATA_REUSE_NO_ABSTRACT', 'terms' => 'https://www.crossref.org/documentation/retrieve-metadata/rest-api', 'license' => null, 'rate_per_minute' => 60, 'reviewed_on' => '2026-10-29', 'expires_on' => '2026-11-05'],
            'GDELT_DOC' => ['id' => $id, 'endpoint' => 'https://api.gdeltproject.org/api/v2/doc/doc', 'host' => 'api.gdeltproject.org', 'corpus' => 'GDELT Project DOC 2.0 news article list, metadata only', 'rights' => 'NEWS_LINK_METADATA_ONLY_ARTICLE_RIGHTS_UNASSESSED', 'terms' => 'https://www.gdeltproject.org/about.html', 'license' => null, 'rate_per_minute' => 1, 'reviewed_on' => '2026-11-08', 'expires_on' => '2026-11-08'],
            'TAVILY_KEYLESS' => ['id' => $id, 'endpoint' => 'https://api.tavily.com/search', 'host' => 'api.tavily.com', 'method' => 'POST', 'corpus' => 'Web search Tavily, keyless experimental', 'rights' => 'UNASSESSED_METADATA_LINKS_ONLY_NO_SNIPPET_RETENTION', 'terms' => 'https://www.tavily.com/terms', 'license' => null, 'rate_per_minute' => 1, 'provider_quota' => 'UNSPECIFIED_KEYLESS_RATE_LIMIT', 'reviewed_on' => '2026-10-29', 'expires_on' => '2026-11-05'],
            default => throw new \RuntimeException('PROVIDER_NOT_REGISTERED')
        };
        // This review applies only to the public Crossref metadata endpoint in an
        // explicitly opted-in, isolated local SQLite environment. Do not renew
        // Wikipedia or keyless search eligibility by inference.
        if ($id === 'CROSSREF' && app(CrossrefLocalPermission::class)->eligible()) {
            $entry['reviewed_on'] = (string) config('sifi_ih03b.crossref_reviewed_on');
            $entry['expires_on'] = (string) config('sifi_ih03b.crossref_review_expires');
            $entry['rate_per_minute'] = 1;
            $entry['review_scope'] = 'IH03B_LOCAL_PUBLIC_CROSSREF_METADATA_ONLY';
        }
        if ($id === 'GDELT_DOC' && app(GdeltLocalPermission::class)->eligible()) {
            $entry['reviewed_on'] = (string) config('sifi_ih03d.gdelt_reviewed_on');
            $entry['expires_on'] = (string) config('sifi_ih03d.gdelt_review_expires');
            $entry['review_scope'] = 'IH03D_LOCAL_GDELT_NEWS_LINK_METADATA_ONLY';
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function parameters(string $id, string $query, int $limit, array $context = []): array
    {
        $this->get($id);
        if ($query === '' || mb_strlen($query) > 600 || $limit < 1 || $limit > 5) {
            throw new \RuntimeException('QUERY_BOUND');
        }
        if ($id === 'GDELT_DOC') {
            return ['query' => $query, 'mode' => 'artlist', 'format' => 'json', 'maxrecords' => $limit, 'timespan' => '1day', 'sort' => 'datedesc'];
        }
        if ($id === 'TAVILY_KEYLESS') {
            $window = $context['date_window'] ?? null;
            if (! is_array($window) || array_keys($window) !== ['start', 'end']) {
                throw new \RuntimeException('DATE_WINDOW_REQUIRED');
            }foreach ($window as $d) {
                if (! is_string($d) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $d) || ! checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
                    throw new \RuntimeException('DATE_WINDOW_INVALID');
                }
            }if ($window['start'] >= $window['end']) {
                throw new \RuntimeException('DATE_WINDOW_INVALID');
            }

            return ['query' => $query, 'max_results' => $limit, 'search_depth' => 'basic', 'topic' => 'general', 'start_date' => $window['start'], 'end_date' => $window['end'], 'include_published_date' => true, 'filter_by_published_date' => true, 'include_answer' => false, 'include_raw_content' => false, 'include_images' => false, 'auto_parameters' => false, 'include_usage' => true];
        }
        if ($id === 'CROSSREF' && isset($context['date_window'])) {
            $window = $context['date_window'];
            if (! is_array($window) || array_keys($window) !== ['start', 'end']) {
                throw new \RuntimeException('DATE_WINDOW_REQUIRED');
            }foreach ($window as $date) {
                if (! is_string($date) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                    throw new \RuntimeException('DATE_WINDOW_INVALID');
                }
            }if ($window['start'] >= $window['end']) {
                throw new \RuntimeException('DATE_WINDOW_INVALID');
            }
            $until = (new \DateTimeImmutable($window['end'], new \DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');

            return ['query' => $query, 'rows' => $limit, 'select' => 'DOI,title,publisher,published,URL,type', 'filter' => 'from-pub-date:'.$window['start'].',until-pub-date:'.$until];
        }

        return $id === 'WIKIPEDIA_ID' ? ['q' => $query, 'limit' => $limit] : ['query' => $query, 'rows' => $limit, 'select' => 'DOI,title,publisher,published,URL,type'];
    }
}
