<?php

namespace App\Console\Commands;

use App\Services\Sifi\Integration\GoogleNewsRssPreview;
use App\Services\Sifi\Integration\GoogleTrendsRssPreview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Installation/availability doctor; no external calls unless --live is explicitly supplied.
 * Uses the same production service objects as the two user-facing preview routes.
 */
final class SifiSourcesDoctor extends Command
{
    protected $signature = 'sifi:sources:doctor
                            {--live : Make exactly one public RSS request to each Google feed}
                            {--query=Pemerintah Kota Yogyakarta : Search term for Google News RSS}';

    protected $description = 'Verify SIFI source-center installation, optionally exercise Google public RSS previews';

    public function handle(GoogleNewsRssPreview $news, GoogleTrendsRssPreview $trends): int
    {
        $ok = true;
        foreach (['dom', 'mbstring', 'json', 'openssl'] as $extension) {
            $present = extension_loaded($extension);
            $this->line('PHP_EXTENSION '.strtoupper($extension).'='.($present ? 'OK' : 'MISSING'));
            $ok = $ok && $present;
        }
        foreach ([
            'sifi.source-catalog.index',
            'sifi.source-catalog.google-trends-preview',
            'sifi.e4.period-discovery.index',
            'sifi.e4.period-discovery.google-news-preview',
        ] as $route) {
            $present = Route::has($route);
            $this->line('ROUTE '.$route.'='.($present ? 'OK' : 'MISSING'));
            $ok = $ok && $present;
        }
        try {
            foreach (['sifi_user_source_selections', 'sifi_user_source_selection_audits'] as $table) {
                $present = Schema::hasTable($table);
                $this->line('TABLE '.$table.'='.($present ? 'OK' : 'MISSING'));
                $ok = $ok && $present;
            }
        } catch (\Throwable) {
            $this->error('DATABASE_UNAVAILABLE');
            $ok = false;
        }
        if (! $ok) {
            $this->error('SIFI_SOURCE_INSTALL_INCOMPLETE');
            return self::FAILURE;
        }
        if (! $this->option('live')) {
            $this->info('SIFI_SOURCE_INSTALL_CHECK=PASS; EXTERNAL_REQUESTS=0');
            $this->comment('To test two public Google RSS feeds, rerun with --live (two requests, no API charge).');
            return self::SUCCESS;
        }

        // --live is an explicit operator decision, never invoked by source catalog GET.
        $query = trim((string) $this->option('query'));
        $today = now('Asia/Jakarta')->toDateString();
        try {
            $items = $news->fetch($query, $today, $today);
            $this->info('GOOGLE_NEWS_RSS=OK; dated_items='.count($items['entries'])
                .'; sha256='.$items['response_sha256']);
            $trending = $trends->fetchIndonesia();
            $this->info('GOOGLE_TRENDS_RSS=OK; indonesia_trends='.count($trending['entries'])
                .'; sha256='.$trending['response_sha256']);
        } catch (\Throwable $exception) {
            $reason = $exception->getMessage();
            $safe = preg_match('/\AGOOGLE_(NEWS|TRENDS)_[A-Z0-9_]+\z/D', $reason) === 1
                ? $reason : 'PUBLIC_RSS_UPSTREAM_UNAVAILABLE';
            $this->error('SIFI_SOURCE_LIVE_CHECK=FAIL; code='.$safe);
            return self::FAILURE;
        }
        $this->info('SIFI_SOURCE_LIVE_CHECK=PASS; EXTERNAL_REQUESTS=2; EVIDENCE_ADMISSION=NONE');
        return self::SUCCESS;
    }
}
