<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Ai\AiTaskCapabilityRegistry;
use App\Services\Sifi\Ai\FreeAiCatalogue;
use App\Services\Sifi\Integration\ProviderInventory;
use App\Services\Sifi\SearchProviderRegistry;
use Tests\TestCase;

final class ProviderInventoryTest extends TestCase
{
    public function test_it_reuses_existing_catalogues_without_any_connection_probe(): void
    {
        $snapshot = (new ProviderInventory)->snapshot('2026-11-07');

        $this->assertTrue($snapshot['read_only']);
        $this->assertFalse($snapshot['grants_dispatch_authority']);
        $this->assertCount(count(FreeAiCatalogue::entries()), $snapshot['ai']);
        $this->assertCount(count(ProviderInventory::VISIBLE_RESEARCH_IDS), $snapshot['research']);
        $this->assertSame(ProviderInventory::VISIBLE_RESEARCH_IDS, array_column($snapshot['research'], 'id'));
        $this->assertContains('GDELT_DOC', array_column($snapshot['research'], 'id'));
        $this->assertContains('TAVILY_KEYLESS', array_column($snapshot['research'], 'id'));
        $this->assertSame((new AiTaskCapabilityRegistry)->tasks(), $snapshot['ai_tasks']);

        foreach ([...$snapshot['ai'], ...$snapshot['research']] as $entry) {
            $this->assertSame('NOT_RUN', $entry['connection_probe']);
            $this->assertArrayNotHasKey('key', $entry);
            $this->assertArrayNotHasKey('credential', $entry);
        }

        foreach ($snapshot['research'] as $entry) {
            $this->assertSame('NOT_EVALUATED_BY_INVENTORY', $entry['dispatch_state']);
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
                $canonical['expires_on'] <= '2026-11-07' ? 'REVIEW_EXPIRED' : 'REVIEW_IN_DATE',
                $entry['review_state'],
            );
        }

        foreach ($snapshot['research'] as $entry) {
            $date = $entry['review_until'];
            if ($date === '') {
                continue;
            }
            $atBoundary = (new ProviderInventory)->snapshot($date);
            $byId = array_column($atBoundary['research'], null, 'id');
            $this->assertSame('REVIEW_EXPIRED', $byId[$entry['id']]['review_state']);
        }
    }
}