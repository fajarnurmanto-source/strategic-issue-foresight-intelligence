<?php

namespace App\Services\Sifi;

use App\Contracts\SifiResearchProvider;

/** Second interchangeable in-process provider; shared/syndicated origins are intentional. */
final class BulletinMultiResearchProvider implements SifiResearchProvider
{
    public const ID = 'SYNTHETIC_BULLETIN';

    public function search(array $context): array
    {
        $out = [];
        foreach (app(DiscoveryCatalog::class)->entries() as $index => $entry) {
            if (($entry['synthetic'] ?? null) !== true || ! MultiSourceFixtureSupport::matches($entry, $context)) {
                continue;
            }
            $copy = $entry;
            $copy['key'] = 'copy-'.$entry['key'];
            $copy['url'] .= '?utm_source=synthetic_bulletin';
            $out[] = $copy;
            $mirror = $entry;
            $mirror['key'] = 'bulletin-'.$entry['key'];
            $mirror['url'] = 'https://bulletin.sources.invalid/'.$entry['key'];
            $mirror['title'] = '[SYNTHETIC] Catatan pembanding '.$entry['title'];
            $mirror['origin'] = $index % 2 === 0 ? $entry['origin'] : 'bulletin-'.$entry['origin'];
            if ($index % 2 === 1) {
                $mirror['snippet'] = '[SYNTHETIC] '.$entry['snippet'].' Catatan simulasi berbeda: status tinjauan, bukan kesimpulan kondisi nyata.';
            }
            $out[] = $mirror;
        }

        return MultiSourceFixtureSupport::packet(self::ID, $context, $out);
    }
}
