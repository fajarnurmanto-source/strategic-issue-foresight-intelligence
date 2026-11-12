<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\CollectorObservations;
use App\Services\Sifi\Integration\AntaraRssTransport;
use App\Services\Sifi\Integration\BlueskySearchTransport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Ih03jOwnerCollectionTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-ih03j-review-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'sifi_ih03.shadow_ingest_enabled' => true,
            'sifi_ih03j.owner_collection_enabled' => false,
            'sifi_ih03b.crossref_metadata_enabled' => false,
            'sifi_ih03e.antara_rss_enabled' => false,
            'sifi_social.bluesky_public_search_enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-ih03j-review-')) {
            (new Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    private function object(User $owner): SifiMonitoringObject
    {
        return SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] IH03J Owner', 'description' => 'Disposable QA',
            'category' => 'Research', 'objective' => 'Source metadata QA',
            'horizon' => '90 hari', 'geography' => null,
            'aliases_json' => [], 'keywords_json' => ['misinformation'],
            'exclusions_json' => [], 'state' => 'CONFIGURED', 'revision' => 1,
        ]);
    }

    private function requestPayload(): array
    {
        return [
            'provider' => 'ANTARA_RSS', 'contact_email' => 'maintainer@example.org',
            'question' => '', 'object_revision' => 1,
            'submission_key' => (string) Str::uuid(),
        ];
    }

    public function test_default_off_refuses_before_any_external_http(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = $this->object($owner);
        $this->actingAs($owner)->getJson(route('sifi.ih03j.status', $object))
            ->assertOk()->assertJsonPath('enabled', false)
            ->assertJsonPath('crossref_allowed', false)
            ->assertJsonPath('antara_allowed', false);
        $this->post(route('sifi.ih03j.collect', $object), $this->requestPayload())
            ->assertSessionHasErrors('real_source');
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_other_owner_cannot_read_or_dispatch(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $object = $this->object($owner);
        $this->actingAs($other)->getJson(route('sifi.ih03j.status', $object))->assertForbidden();
        $this->post(route('sifi.ih03j.collect', $object), $this->requestPayload())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unexpected_user_controlled_endpoint_is_rejected(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = $this->object($owner);
        $this->actingAs($owner)->post(route('sifi.ih03j.collect', $object), [
            ...$this->requestPayload(), 'endpoint' => 'https://example.com/private',
        ])->assertSessionHasErrors('real_source');
        Http::assertNothingSent();
    }

    public function test_owner_can_launch_one_social_watch_scan_from_existing_form_without_per_post_review(): void
    {
        config([
            'sifi_social.bluesky_public_search_enabled' => true,
            'sifi_social.contact_email' => 'maintainer@example.org',
        ]);
        $transport = new class extends BlueskySearchTransport
        {
            public int $calls = 0;

            public function fetch(string $query, string $contact): array
            {
                $this->calls++;
                $url = 'https://bsky.app/profile/did:plc:u5cwb2mwiv2bfq53cjufe6yn/post/3k4duaz5vfs2b';

                return [
                    'state' => 'OK', 'network_requests' => 1,
                    'http_status' => 200, 'response_bytes' => 150,
                    'response_sha256' => str_repeat('b', 64), 'elapsed_ms' => 120,
                    'entries' => [[
                        'id' => hash('sha256', $url),
                        'title' => 'Unggahan publik Bluesky terkait: misinformation',
                        'canonical_url' => $url, 'url' => $url,
                        'snippet' => '', 'snippet_sha256' => hash('sha256', ''),
                        'published_at' => '2026-11-09T04:00:00Z',
                        'rights_state' => 'PUBLIC_POST_LINK_AND_DERIVED_METADATA_ONLY',
                        'origins' => ['bsky.app'],
                        'lineage' => [['provider' => 'BLUESKY_PUBLIC_SEARCH']],
                        'evidence_admission' => 'NONE',
                    ]],
                ];
            }
        };
        app()->instance(BlueskySearchTransport::class, $transport);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = $this->object($owner);
        $this->actingAs($owner)->getJson(route('sifi.ih03j.status', $object))
            ->assertOk()->assertJsonPath('bluesky_allowed', true)
            ->assertJsonPath('social_contact_configured', true);

        $this->post(route('sifi.ih03j.collect', $object), [
            'provider' => 'BLUESKY_PUBLIC_SEARCH',
            'contact_email' => '',
            'question' => 'misinformation',
            'object_revision' => $object->revision,
            'submission_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame(1, $transport->calls);
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    public function test_isolated_owner_can_collect_only_bounded_antara_metadata(): void
    {
        config([
            'sifi_ih03j.owner_collection_enabled' => true,
            'sifi_ih03e.antara_rss_enabled' => true,
        ]);
        $transport = new class extends AntaraRssTransport
        {
            public int $calls = 0;

            public function fetch(string $contact): array
            {
                $this->calls++;
                $url = 'https://www.antaranews.com/berita/123/berita-qa';

                return [
                    'state' => 'OK', 'network_requests' => 1,
                    'http_status' => 200, 'response_bytes' => 180,
                    'response_sha256' => str_repeat('a', 64), 'elapsed_ms' => 50,
                    'entries' => [[
                        'id' => hash('sha256', $url), 'title' => 'Judul berita QA',
                        'canonical_url' => $url, 'url' => $url,
                        'snippet' => '', 'snippet_kind' => 'WITHHELD_PENDING_RIGHTS',
                        'snippet_sha256' => hash('sha256', ''),
                        'published_at' => '2026-11-09T05:00:00Z',
                        'publication_precision' => 'RSS_DECLARED_SECOND',
                        'rights_state' => 'RSS_TITLE_LINK_ONLY_ARTICLE_RIGHTS_UNASSESSED',
                        'origins' => ['www.antaranews.com'],
                        'origin_state' => 'DECLARED_NOT_VERIFIED',
                        'lineage' => [['provider' => 'ANTARA_RSS']],
                        'evidence_admission' => 'NONE',
                    ]],
                ];
            }
        };
        app()->instance(AntaraRssTransport::class, $transport);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = $this->object($owner);
        $this->actingAs($owner)->getJson(route('sifi.ih03j.status', $object))
            ->assertOk()->assertJsonPath('antara_allowed', true);
        $this->post(route('sifi.ih03j.collect', $object), $this->requestPayload())
            ->assertRedirect();
        $this->assertSame(1, $transport->calls);
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);

        $observations = app(CollectorObservations::class)->forObject($owner, $object);
        $this->assertCount(1, $observations);
        $this->assertSame(['ANTARA_RSS'], $observations[0]['provider_ids']);
        $this->assertSame('PUBLISHER_RSS_HEADLINES', $observations[0]['source_kind']);
        $this->assertSame('Judul berita QA', $observations[0]['entries'][0]['title']);
        $this->assertSame('www.antaranews.com', $observations[0]['entries'][0]['publisher']);
        $this->assertSame('RSS_TITLE_LINK_ONLY_ARTICLE_RIGHTS_UNASSESSED',
            $observations[0]['entries'][0]['rights_state']);
        $this->assertSame('NONE', $observations[0]['entries'][0]['evidence_admission']);
        $this->assertSame('NOT_VERIFIED', $observations[0]['independent_origin_state']);
        $this->assertSame(str_repeat('a', 64), $observations[0]['receipt']['response_sha256']);
        $this->get('/sifi/workbench?object='.$object->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('collectorObservations', 1)
                ->where('collectorObservations.0.source_kind', 'PUBLISHER_RSS_HEADLINES'));
        Http::assertNothingSent();
    }
}
