<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sifi\Integration\CrossSourceReceiptFusion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** A local HTML snapshot of reviewed receipts; no listener, network, or DB writes. */
final class SifiIh03gHtml extends Command
{
    protected $signature = 'sifi:ih03g-html
        {--owner= : Verified owner ID}
        {--crossref= : Reviewed Crossref receipt UUID}
        {--antara= : Reviewed ANTARA RSS receipt UUID}';

    protected $description = 'Render a local read-only two-source metadata dashboard';

    public function handle(CrossSourceReceiptFusion $fusion): int
    {
        try {
            if (! app()->environment(['local', 'testing']) || config('database.default') !== 'sqlite') {
                throw new \RuntimeException('IH03G_LOCAL_SQLITE_ONLY');
            }
            $id = $this->option('owner');
            $crossref = $this->option('crossref');
            $antara = $this->option('antara');
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || ! is_string($crossref) || ! Str::isUuid($crossref)
                || ! is_string($antara) || ! Str::isUuid($antara)) {
                throw new \RuntimeException('IH03G_INVALID_ARGUMENTS');
            }
            $owner = User::query()->findOrFail((int) $id);
            $snapshot = $fusion->analyze($owner, $crossref, $antara);

            // The output path is not user-controlled. Never generate a web-accessible view.
            $directory = config('sifi_ih03g.preview_dir');
            if ($directory !== '/mnt/d/Research/SIFI/05_VALIDATION'
                || ! is_dir($directory) || is_link($directory)) {
                throw new \RuntimeException('IH03G_VALIDATION_DIRECTORY_REQUIRED');
            }
            $html = view('sifi.ih03g-fusion', ['snapshot' => $snapshot])->render();
            if (strlen($html) > 200 * 1024) {
                throw new \RuntimeException('IH03G_HTML_SIZE_EXCEEDED');
            }
            $filename = 'SIFI_IH03G_FUSION_UI_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(6)).'.html';
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $handle = fopen($path, 'x');
            if ($handle === false) {
                throw new \RuntimeException('IH03G_PREVIEW_CREATE_FAILED');
            }
            try {
                chmod($path, 0600);
                if (fwrite($handle, $html) !== strlen($html) || ! fflush($handle)) {
                    throw new \RuntimeException('IH03G_PREVIEW_WRITE_FAILED');
                }
            } finally {
                fclose($handle);
            }
            $this->line('IH03G_HTML_CREATED='.$path);
            $this->line('TOTAL_METADATA_ROWS='.$snapshot['total_metadata_rows']);
            $this->line('NEW_NETWORK_REQUESTS=0');
            $this->line('MAIN_DATABASE_WRITES=0');
            $this->line('EVIDENCE_ADMISSION=NONE');
            $this->line('PRODUCTION_ADMISSION=DENIED');
            $this->info('SIFI_IH03G_PREVIEW=PASS');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('SIFI_IH03G=HELD: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
