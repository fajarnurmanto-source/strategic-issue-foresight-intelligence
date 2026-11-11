<?php

namespace App\Services\Sifi\Integration;

/** Interpret public AppView search as social-attention observations, never verified events. */
final class BlueskyPublicSearchParser
{
    /** @return array<string, mixed> */
    public function parse(string $json, string $query): array
    {
        if (strlen($json) > 262144 || ! mb_check_encoding($json, 'UTF-8')) {
            throw new \RuntimeException('SIFI_SOCIAL_RESPONSE_BOUND');
        }

        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $posts = $data['posts'] ?? null;
        if (! is_array($posts) || ! array_is_list($posts) || count($posts) > 10) {
            throw new \RuntimeException('SIFI_SOCIAL_POSTS_INVALID');
        }

        $entries = [];
        $seen = [];
        foreach ($posts as $post) {
            if (! is_array($post) || ! is_string($post['uri'] ?? null)
                || preg_match('#\Aat://(did:(?:plc:[a-z2-7]{24}|web:[A-Za-z0-9._%:-]{1,180}))/app\.bsky\.feed\.post/([A-Za-z0-9]{5,64})\z#D', $post['uri'], $parts) !== 1) {
                continue;
            }
            $url = 'https://bsky.app/profile/'.$parts[1].'/post/'.$parts[2];
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $text = $post['record']['text'] ?? '';
            if (! is_string($text) || mb_strlen($text) > 3000) {
                $text = '';
            }
            $published = $post['record']['createdAt'] ?? null;
            if (! is_string($published) || strlen($published) > 40
                || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/D', $published)) {
                $published = null;
            }
            $signals = [];
            foreach (['sampah', 'banjir', 'jalan', 'parkir', 'pungli', 'macet', 'air', 'layanan', 'listrik', 'transportasi'] as $term) {
                if ($text !== '' && mb_stripos($text, $term) !== false) {
                    $signals[] = $term;
                }
            }
            $engagement = [];
            foreach (['likeCount', 'repostCount', 'replyCount', 'quoteCount'] as $metric) {
                $value = $post[$metric] ?? 0;
                $engagement[$metric] = is_int($value) && $value >= 0 ? $value : 0;
            }
            $entries[] = [
                'id' => hash('sha256', $url),
                'title' => 'Unggahan publik Bluesky terkait: '.mb_substr($query, 0, 80),
                'url' => $url,
                'canonical_url' => $url,
                'snippet' => '',
                'snippet_kind' => 'WITHHELD_PUBLIC_USER_CONTENT',
                'snippet_sha256' => hash('sha256', ''),
                'post_text_sha256' => $text === '' ? null : hash('sha256', $text),
                'issue_term_candidates' => $signals,
                'engagement_snapshot' => $engagement,
                'engagement_interpretation' => 'PLATFORM_ATTENTION_NOT_EVENT_PREVALENCE',
                'published_at' => $published,
                'publication_precision' => $published === null ? 'UNKNOWN' : 'POST_AUTHOR_DECLARED',
                'retrieved_at' => now()->toIso8601String(),
                'rights_state' => 'PUBLIC_POST_LINK_AND_DERIVED_METADATA_ONLY',
                'retrieval_allowed' => false,
                'evidence_admission' => 'NONE',
                'origins' => ['bsky.app'],
                'origin_state' => 'PLATFORM_NOT_INDEPENDENT_NEWS_ORIGIN',
                'lineage' => [[
                    'provider' => 'BLUESKY_PUBLIC_SEARCH',
                    'url' => $url,
                    'origin' => 'public.api.bsky.app',
                    'publication_precision' => $published === null ? 'UNKNOWN' : 'POST_AUTHOR_DECLARED',
                ]],
                'relevance' => [
                    'score' => 0,
                    'question_hits' => [],
                    'context_hits' => $signals,
                ],
                'relevance_group' => 'PUBLIC_SOCIAL_SEARCH_MATCH_UNVERIFIED',
            ];
        }

        return $entries;
    }
}
