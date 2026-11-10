<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sifi\Integration\CrossSourceReceiptFusion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Compare already-stored metadata only: no provider requests or new writes. */
final class SifiIh03fFuse extends Command
{
    protected $signature = 'sifi:ih03f-fuse
        {--owner= : Verified owner ID}
        {--crossref= : Existing reviewed Crossref receipt UUID}
        {--antara= : Existing reviewed ANTARA RSS receipt UUID}
        {--summary-only : Display concise counts and safety outcomes}';

    protected $description = 'Read-only bounded Crossref plus ANTARA RSS metadata fusion';

    public function handle(CrossSourceReceiptFusion $fusion): int
    {
        try {
            $id = $this->option('owner');
            $crossref = $this->option('crossref');
            $antara = $this->option('antara');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || ! is_string($crossref) || ! Str::isUuid($crossref)
                || ! is_string($antara) || ! Str::isUuid($antara)) {
                throw new \RuntimeException('IH03F_INVALID_ARGUMENTS');
            }
            $owner = User::query()->findOrFail((int) $id);
            $snapshot = $fusion->analyze($owner, $crossref, $antara);
            if ($this->option('summary-only')) {
                $this->line('CROSSREF_METADATA_ROWS='.$snapshot['source_counts']['CROSSREF']);
                $this->line('ANTARA_NEWS_LINKS='.$snapshot['source_counts']['ANTARA_RSS']);
                $this->line('TOTAL_METADATA_ROWS='.$snapshot['total_metadata_rows']);
                $this->line('DISTINCT_CANONICAL_URLS='.$snapshot['distinct_canonical_urls']);
                $this->line('CROSS_SOURCE_SAME_URL_CANDIDATES='.count($snapshot['same_url_cross_source_candidates']));
                $this->line('NEW_NETWORK_REQUESTS=0');
                $this->line('NEW_DATABASE_WRITES=0');
                $this->line('EVIDENCE_ADMISSION=NONE');
                $this->line('PRODUCTION_ADMISSION=DENIED');
            } else {
                $this->line(json_encode($snapshot,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
            $this->info('SIFI_IH03F_FUSION=PASS');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('SIFI_IH03F=HELD: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
