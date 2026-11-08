<?php

namespace App\Services\Sifi\Integration;

/** Operator-facing platform access map. Labels are not claims of connected coverage. */
final class SocialSourceCoverage
{
    public function snapshot(string $asOf): array
    {
        $enabled = config('sifi_social.bluesky_public_search_enabled') === true;
        $reviewed = $asOf < (string) config('sifi_social.review_expires');
        $blueskyState = ! $reviewed ? 'REVIEW_EXPIRED'
            : ($enabled ? 'LOCAL_OPT_IN_ONLY' : 'OFF_BY_DEFAULT');

        return [
            [
                'id' => 'BLUESKY_PUBLIC_SEARCH',
                'label' => 'Bluesky',
                'state' => $blueskyState,
                'access' => 'Unauthenticated AppView search may return HTTP 403 due to provider policy',
                'scope' => 'Max 10 public links / explicit scan; not entire platform',
                'action' => 'Jika pencarian publik mendapat 403, hentikan percobaan; gunakan jalur resmi Jetstream untuk data langsung.',
            ],
            [
                'id' => 'INSTAGRAM',
                'label' => 'Instagram',
                'state' => 'NOT_CONNECTED',
                'access' => 'Meta professional-account permissions; no general public scraping',
                'scope' => 'Account/hashtag endpoints subject to approved API permissions',
                'action' => 'Prioritaskan koneksi resmi Meta dan batas cakupan yang benar-benar diizinkan.',
            ],
            [
                'id' => 'TIKTOK',
                'label' => 'TikTok',
                'state' => 'NOT_CONNECTED',
                'access' => 'Research/other APIs gated by eligibility and app permissions',
                'scope' => 'No unrestricted public TikTok feed access',
                'action' => 'Tentukan akses API yang memenuhi syarat, jangan bypass.',
            ],
            [
                'id' => 'X',
                'label' => 'X',
                'state' => 'NOT_CONNECTED',
                'access' => 'Developer API access and quota/charge policy',
                'scope' => 'No assumed free full-stream access',
                'action' => 'Aktifkan hanya jika paket, izin dan batas biaya diketahui.',
            ],
            [
                'id' => 'YOUTUBE',
                'label' => 'YouTube',
                'state' => 'NOT_CONNECTED',
                'access' => 'YouTube Data API; API key/OAuth and quota controls',
                'scope' => 'Search/video/comment coverage varies by endpoint',
                'action' => 'Prioritas konektor API untuk video/komentar isu.',
            ],
            [
                'id' => 'MASTODON',
                'label' => 'Mastodon',
                'state' => 'NOT_CONNECTED',
                'access' => 'Public hashtag timelines vary by instance configuration',
                'scope' => 'Only instance-visible public statuses',
                'action' => 'Pilih instans publik secara eksplisit; hormati aturan dan kuota instans.',
            ],
        ];
    }
}
