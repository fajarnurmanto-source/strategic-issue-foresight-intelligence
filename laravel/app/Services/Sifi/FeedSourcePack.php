<?php

namespace App\Services\Sifi;

/** Server-owned, synthetic-only pack. Real endpoint proposals never enter this registry. */
final class FeedSourcePack
{
    public const IDS = ['SYNTHETIC_RSS', 'SYNTHETIC_ATOM', 'SYNTHETIC_OPEN_NEWS'];

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $out = [];
        foreach (['SYNTHETIC_RSS' => ['RSS', 'Buletin RSS sintetis', 'rss'], 'SYNTHETIC_ATOM' => ['ATOM', 'Buletin Atom sintetis', 'atom'], 'SYNTHETIC_OPEN_NEWS' => ['GDELT_JSON', 'Agregator berita sintetis (schema GDELT)', 'news']] as $id => [$format,$label,$file]) {
            $out[$id] = ['id' => $id, 'label' => $label, 'format' => $format, 'endpoint' => 'https://'.$file.'.sources.invalid/feed', 'fixture_file' => $file, 'rights_state' => 'UNASSESSED', 'collector_state' => 'OFF', 'fixture_use' => 'SELF_AUTHORED_SYNTHETIC_ONLY', 'attribution' => 'SIFI self-authored SYNTHETIC; not external publication', 'terms' => 'Real access, retention and commercial reuse NOT_APPROVED', 'live_enabled' => false, 'max_pages' => $format === 'GDELT_JSON' ? 1 : 2];
        }

return $out;
    }

    /** @return array<string, mixed> */
    public function source(string $id): array
    {
        return $this->all()[$id] ?? throw new \RuntimeException('UNKNOWN_SOURCE');
    }

    public function bytes(string $id, int $page, string $object): string
    {
        $p = $this->source($id);
        if ($page < 1 || $page > $p['max_pages']) {
            throw new \RuntimeException('PAGE_BOUND');
        }
        $path = resource_path('sifi/feeds/'.$p['fixture_file'].'-'.$page.($p['format'] === 'GDELT_JSON' ? '.json' : '.xml'));
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('FIXTURE_READ_FAILED');
        }if (strlen($raw) > 32768) {
            throw new \RuntimeException('FIXTURE_SIZE');
        }

        return str_replace('{{OBJECT}}', $p['format'] === 'GDELT_JSON' ? substr(json_encode($object, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 1, -1) : htmlspecialchars($object, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $raw);
    }
}
