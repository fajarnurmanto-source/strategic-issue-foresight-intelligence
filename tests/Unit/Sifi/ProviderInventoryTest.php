<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Ai\AiTaskCapabilityRegistry;
use App\Services\Sifi\Ai\FreeAiCatalogue;
use App\Services\Sifi\Integration\ProviderInventory;
use App\Services\Sifi\SearchProviderRegistry;
use PHPUnit\Framework\TestCase;

final class ProviderInventoryTest extends TestCase
{
    public function test_it_reuses_existing_catalogues_without_any_connection_probe(): void
    {
        $snapshot = (new ProviderInventory)->snapshot('2026-11-07');

        $this->assertTrue($snapshot['read_only']);
        $this->assertCount(count(FreeAiCatalogue::entries()), $snapshot['ai']);
        $this->assertCount(count(SearchProviderRegistry::IDS), $snapshot['research']);
        $this->assertSame((new AiTaskCapabilityRegistry)->tasks(), $snapshot['ai_tasks']);

        foreach ([...$snapshot['ai'], ...$snapshot['research']] as $entry) {
            $this->assertSame('NOT_RUN', $entry['connection_probe']);
            $this->assertArrayNotHasKey('key', $entry);
            $this->assertArrayNotHasKey('credential', $entry);
        }
    }

    public function test_review_state_follows_canonical_review_dates(): void
    {
        $snapshot = (new ProviderInventory)->snapshot('2026-11-07');
        $registry = new SearchProviderRegistry;

        foreach ($snapshot['research'] as $entry) {
            $canonical = $registry->get($entry['id']);
            $this->assertSame($canonical['expires_on'], $entry['review_until']);
            $this->assertSame(
                $canonical['expires_on'] < '2026-11-07' ? 'REVIEW_EXPIRED' : 'REVIEW_IN_DATE',
                $entry['review_state'],
            );
        }

        $date = $snapshot['research'][0]['review_until'];
        $this->assertSame('REVIEW_IN_DATE',
            (new ProviderInventory)->snapshot($date)['research'][0]['review_state']);
    }
}