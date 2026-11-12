<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiForesightDraft;
use App\Models\SifiIssueCandidate;
use App\Models\SifiSourceDraft;
use App\Models\User;
use App\Services\Sifi\ExecutiveBriefSource;
use App\Services\Sifi\SourceObservationPipeline;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WorldBankGdpSourceAdapterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private WorldBankGdpSourceAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        // Replay the audited archive at its original review date; test expiry separately.
        $this->travelTo(Carbon::parse('2026-10-26T12:00:00Z'));
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->adapter = app(WorldBankGdpSourceAdapter::class);
    }

    private function inputs(): array
    {
        return [file_get_contents(resource_path('sifi/ae09-wb-response.json')),
            json_decode(file_get_contents(resource_path('sifi/ae09-wb-retrieval.json')), true, 32, JSON_THROW_ON_ERROR),
            json_decode(file_get_contents(resource_path('sifi/ae09-wb-rights.json')), true, 32, JSON_THROW_ON_ERROR)];
    }

    /** All modified values in negative/revision cases are test-only, never represented as public observations. */
    private function modified(callable $mutate): array
    {
        [$raw,$m,$rights] = $this->inputs();
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        $mutate($data);
        $raw = json_encode($data, JSON_THROW_ON_ERROR);
        $m['sha256'] = hash('sha256', $raw);

        return $this->adapter->normalizeResponse($raw, $m, $rights, strtotime('2026-10-25T12:00:00Z'));
    }

    private function register(?User $user = null): SifiSourceDraft
    {
        $user ??= $this->owner;
        $this->actingAs($user)->post('/sifi/source-catalog/sources', ['name' => 'World Bank GDP archive automated test', 'source_type' => 'OPEN_DATA',
            'homepage_url' => WorldBankGdpSourceAdapter::DOCUMENTATION, 'source_geography' => 'Indonesia national', 'notes' => 'CC BY 4.0, attribution and dataset terms; no live collection'])->assertRedirect();

        return SifiSourceDraft::where('user_id', $user->id)->firstOrFail();
    }

    private function fields(SifiSourceDraft $s): array
    {
        return ['scenario' => 'wb_snapshot', 'source_draft_id' => $s->id, 'source_revision' => $s->revision, 'input_digest' => $this->adapter->snapshot()['packet_hash'],
            'confirm_archive' => true, 'analyst_note' => 'Automated acceptance using three real archived public records, not a human review or factual verification.'];
    }

    private function create(SifiSourceDraft $s): SifiIssueCandidate
    {
        $response = $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft', $this->fields($s));
        $response->assertSessionHasNoErrors()->assertRedirect();
        $id = basename(dirname(parse_url($response->headers->get('Location'), PHP_URL_PATH)));

        return SifiIssueCandidate::findOrFail($id);
    }

    public function test_real_archive_matches_original_wire_digest_without_network(): void
    {
        $p = $this->adapter->snapshot();
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $p['status']);
        $this->assertSame('REAL_PUBLIC_ARCHIVE', $p['mode']);
        $this->assertCount(3, $p['observations']);
        $this->assertCount(2, $p['changes']);
        $this->assertFalse($p['claim_verified']);
        $this->assertSame('NOT_EVALUATED', $p['evidence_admission']);
        $this->assertSame('2026-10-08', $p['source']['source_updated_at']);
        $this->assertSame(WorldBankGdpSourceAdapter::RESPONSE_SHA, $p['source']['wire_sha256']);
        $this->assertSame('HISTORICAL_ANNUAL_ARCHIVE_NOT_CURRENT', $p['source']['freshness']);
        foreach ($p['observations'] as $o) {
            $this->assertFalse($o['provenance']['synthetic']);
            $this->assertNull($o['issued_at']);
            $this->assertNull($o['valid_at']);
            $this->assertSame('', $o['raw_unit']);
            $this->assertSame('% per tahun', $o['unit']);
            $this->assertStringContainsString('The World Bank', $o['provenance']['attribution']);
        }
        Http::assertNothingSent();
    }

    public function test_schema_and_period_coverage_fail_closed(): void
    {
        foreach ([
            fn (&$d) => $d[1][0]['indicator']['id'] = 'OTHER', fn (&$d) => $d[1][0]['countryiso3code'] = 'USA', fn (&$d) => $d[1][0]['date'] = '2025',
            fn (&$d) => $d[1][0]['unknown'] = 'new', fn (&$d) => $d[1][0]['unit'] = 'dollars', fn (&$d) => $d[1][0]['obs_status'] = 'F',
            fn (&$d) => $d[0]['unexpected'] = 'new', fn (&$d) => $d[0]['lastupdated'] = '2026-02-30', fn (&$d) => $d[0]['lastupdated'] = '2027-01-01',
        ] as $mutate) {
            $p = $this->modified($mutate);
            $this->assertSame('BLOCKED', $p['status']);
            $this->assertCount(0, $p['observations']);
        }
    }

    public function test_pagination_is_proved_complete_or_rejected_never_silently_truncated(): void
    {
        foreach ([fn (&$d) => $d[0]['pages'] = 2, fn (&$d) => $d[0]['total'] = 30, fn (&$d) => array_pop($d[1]), fn (&$d) => $d[0]['page'] = 2] as $m) {
            $this->assertSame('INCOMPLETE_OR_UNSUPPORTED_PAGINATION', $this->modified($m)['reason']);
        }
    }

    public function test_missing_null_string_and_out_of_schema_values_are_explicit(): void
    {
        foreach ([null, '5.03', true, []] as $value) {
            $this->assertSame('VALUE_UNAVAILABLE', $this->modified(function (&$d) use ($value) {
                $d[1][0]['value'] = $value;
            })['reason']);
        }
        $this->assertSame('VALUE_UNAVAILABLE', $this->modified(function (&$d) {
            unset($d[1][0]['value']);
        })['reason']);
    }

    public function test_duplicate_period_and_same_period_conflict_do_not_form_partial_candidates(): void
    {
        $p = $this->modified(function (&$d) {
            $d[1][2] = $d[1][1];
        });
        $this->assertSame('INCOMPLETE_PERIOD_COVERAGE', $p['reason']);
        $this->assertCount(0, $p['observations']);
        $p = $this->modified(function (&$d) {
            $d[1][2] = $d[1][1];
            $d[1][2]['value'] = 1.0;
        });
        $this->assertSame('SAME_PERIOD_CONFLICT', $p['reason']);
    }

    public function test_content_revision_changes_identity_without_relabeling_annual_comparison_as_revision(): void
    {
        $original = $this->adapter->snapshot();
        $changed = $this->modified(function (&$d) {
            $d[1][0]['value'] = 4.0;
        });
        $this->assertSame($original['observations'][0]['record_key'], $changed['observations'][0]['record_key']);
        $this->assertNotSame($original['observations'][0]['id'], $changed['observations'][0]['id']);
        $this->assertSame($original['observations'][1]['id'], $changed['observations'][1]['id']);
        foreach ($changed['changes'] as $change) {
            $this->assertSame('ANNUAL_PERIOD_COMPARISON_NOT_REVISION_OR_CAUSATION', $change['kind']);
        }
    }

    public function test_rights_revoked_unassessed_noncommercial_and_missing_attribution_are_denied(): void
    {
        [$raw,$m,$r] = $this->inputs();
        foreach ([['state' => 'REVOKED'], ['state' => 'UNASSESSED'], ['commercial_reuse' => false], ['attribution' => ''], ['license' => 'https://creativecommons.org/licenses/by-nc/4.0/'], ['source_id' => 'other']] as $change) {
            $this->assertSame('RIGHTS_NOT_SATISFIED', $this->adapter->normalizeResponse($raw, $m, array_replace($r, $change))['reason']);
        }
        unset($r['reviewed_on']);
        $this->assertSame('RIGHTS_METADATA_INCOMPLETE', $this->adapter->normalizeResponse($raw, $m, $r)['reason']);
    }

    public function test_stale_review_future_or_missing_retrieval_do_not_imply_freshness(): void
    {
        [$raw,$m,$r] = $this->inputs();
        $this->assertSame('RIGHTS_REVIEW_EXPIRED', $this->adapter->normalizeResponse($raw, $m, $r, strtotime('2026-11-25'))['reason']);
        $r['review_until'] = '2027-01-01';
        $this->assertSame('ARCHIVE_REVIEW_REQUIRED', $this->adapter->normalizeResponse($raw, $m, $r, strtotime('2026-11-26'))['reason']);
        $m['retrieved_at'] = '2026-10-26T12:00:00Z';
        $this->assertSame('FUTURE_RETRIEVAL', $this->adapter->normalizeResponse($raw, $m, $r, strtotime('2026-10-25'))['reason']);
        unset($m['retrieved_at']);
        $this->assertSame('RETRIEVAL_TIME_MISSING', $this->adapter->normalizeResponse($raw, $m, $r)['reason']);
    }

    public function test_http_timeout_rate_limit_redirect_and_wrong_endpoint_are_not_retried(): void
    {
        [$raw,$m,$r] = $this->inputs();
        foreach ([429 => 'RATE_LIMITED_NO_RETRY', 500 => 'HTTP_FAILURE_NO_RETRY', 302 => 'HTTP_FAILURE_NO_RETRY', 'TIMEOUT' => 'TIMEOUT_NO_RETRY'] as $status => $reason) {
            $this->assertSame($reason, $this->adapter->normalizeResponse($raw, array_replace($m, ['status' => $status]), $r)['reason']);
        }
        $this->assertSame('SOURCE_NOT_SUPPORTED', $this->adapter->normalizeResponse($raw, array_replace($m, ['url' => 'http://169.254.169.254/']), $r)['reason']);
        $this->assertSame('LIVE_ACQUISITION_NOT_AUTHORIZED', $this->adapter->fetchLive()['reason']);
        Http::assertNothingSent();
    }

    public function test_malformed_body_digest_content_type_and_size_fail_closed(): void
    {
        [$raw,$m,$r] = $this->inputs();
        $this->assertSame('CONTENT_DIGEST_MISMATCH', $this->adapter->normalizeResponse($raw.'x', $m, $r)['reason']);
        $this->assertSame('CONTENT_TYPE_NOT_SUPPORTED', $this->adapter->normalizeResponse($raw, array_replace($m, ['content_type' => 'text/html']), $r)['reason']);
        foreach (['{bad' => 'MALFORMED_JSON', str_repeat('x', 65537) => 'PAYLOAD_TOO_LARGE', '[]' => 'SCHEMA_DRIFT'] as $body => $reason) {
            $this->assertSame($reason, $this->adapter->normalizeResponse($body, array_replace($m, ['sha256' => hash('sha256', $body)]), $r)['reason']);
        }
    }

    public function test_semantic_identity_survives_key_order_and_manual_retrieval_time_changes(): void
    {
        [$raw,$m,$r] = $this->inputs();
        $original = $this->adapter->snapshot();
        $d = json_decode($raw, true);
        foreach ($d[1] as &$row) {
            $row = array_reverse($row, true);
            $row['country'] = array_reverse($row['country'], true);
            $row['indicator'] = array_reverse($row['indicator'], true);
        }unset($row);
        $raw = json_encode($d, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $m['sha256'] = hash('sha256', $raw);
        $m['retrieved_at'] = '2026-10-25T07:00:00Z';
        $changed = $this->adapter->normalizeResponse($raw, $m, $r, strtotime('2026-10-25T12:00:00Z'));
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $changed['status']);
        $this->assertSame(array_column($original['observations'], 'id'), array_column($changed['observations'], 'id'));
        $this->assertNotSame($original['packet_hash'], $changed['packet_hash']);
    }

    public function test_real_archive_csrf_and_injected_authority_or_records_are_rejected(): void
    {
        $source = $this->register();
        $v = $this->fields($source);
        foreach (['user_id' => $this->owner->id, 'verified' => true, 'evidence_admitted' => true, 'collector_state' => 'ON', 'source_url' => 'http://127.0.0.1', 'records' => [['fake' => true]]] as $key => $value) {
            $this->post('/sifi/source-catalog/pipeline-draft', $v + [$key => $value])->assertSessionHasErrors($key);
        }
        $this->app->instance('env', 'local');
        $this->post('/sifi/source-catalog/pipeline-draft', $v)->assertStatus(419);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        Http::assertNothingSent();
    }

    public function test_creation_failure_rolls_back_only_new_chain_in_isolated_database(): void
    {
        $source = $this->register();
        SifiForesightDraft::creating(function () {
            throw new \RuntimeException('ISOLATED_FAILURE');
        });
        try {
            $this->withoutExceptionHandling()->post('/sifi/source-catalog/pipeline-draft', $this->fields($source));
            $this->fail('Must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('ISOLATED_FAILURE', $e->getMessage());
        } finally {
            SifiForesightDraft::flushEventListeners();
        }
        foreach (['sifi_issue_candidates', 'sifi_investigations', 'sifi_evidence_drafts', 'sifi_claim_drafts', 'sifi_trajectory_drafts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('sifi_source_drafts', 1);
    }

    public function test_lineage_survives_session_loss_and_expired_review_without_new_ingestion(): void
    {
        $source = $this->register();
        $c = $this->create($source);
        $pipeline = app(SourceObservationPipeline::class);
        $old = $pipeline->lineage($c);
        session()->flush();
        $this->travelTo(Carbon::parse('2026-12-01T12:00:00Z'));
        $this->assertSame('RIGHTS_REVIEW_EXPIRED', $this->adapter->snapshot()['reason']);
        $this->assertEquals($old, $pipeline->lineage($c));
        $this->travelBack();
        $this->assertDatabaseCount('sifi_event_hypotheses', 0);
        $this->assertDatabaseCount('sifi_evidence_review_notes', 0);
    }

    public function test_unauthenticated_access_does_not_create_records(): void
    {
        $this->get('/sifi/source-catalog?scenario=wb_snapshot')->assertRedirect('/login');
        $this->post('/sifi/source-catalog/pipeline-draft', [])->assertRedirect('/login');
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        Http::assertNothingSent();
    }

    public function test_real_archive_requires_correct_confirmation_registered_owner_revision_and_digest(): void
    {
        $s = $this->register();
        $v = $this->fields($s);
        $this->post('/sifi/source-catalog/pipeline-draft', array_replace($v, ['confirm_archive' => false, 'confirm_synthetic' => true]))->assertSessionHasErrors('confirm_archive');
        $this->post('/sifi/source-catalog/pipeline-draft', array_replace($v, ['input_digest' => str_repeat('0', 64)]))->assertSessionHasErrors('input_digest');
        $this->post('/sifi/source-catalog/pipeline-draft', array_replace($v, ['source_revision' => 99]))->assertSessionHasErrors('source_revision');
        $s->homepage_url = 'https://example.org';
        $s->save();
        $this->post('/sifi/source-catalog/pipeline-draft', $v)->assertSessionHasErrors('source_draft_id');
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_cross_owner_source_and_candidate_are_denied(): void
    {
        $s = $this->register();
        $c = $this->create($s);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->post('/sifi/source-catalog/pipeline-draft', $this->fields($s))->assertForbidden();
        $this->get('/sifi/issue-candidates/'.$c->id.'/executive-brief')->assertForbidden();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        Http::assertNothingSent();
    }

    public function test_replay_preserves_user_edits_and_history_is_owner_scoped(): void
    {
        $s = $this->register();
        $c = $this->create($s);
        $c->title = 'User edited archive candidate';
        $c->revision++;
        $c->save();
        $replay = $this->create($s);
        $this->assertSame($c->id, $replay->id);
        $this->assertSame('User edited archive candidate', $replay->title);
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 3);
        $p = app(SourceObservationPipeline::class)->preview('wb_snapshot', null, $this->owner->id);
        $this->assertSame(3, $p['history']['already_seen']);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $q = app(SourceObservationPipeline::class)->preview('wb_snapshot', null, $other->id);
        $this->assertSame(0, $q['history']['already_seen']);
    }

    public function test_real_source_to_existing_candidate_evidence_trajectory_brief_download_is_faithful(): void
    {
        $s = $this->register();
        $c = $this->create($s);
        $p = app(SourceObservationPipeline::class)->lineage($c);
        $this->assertSame('REAL_PUBLIC_ARCHIVE', $p['mode']);
        $this->assertCount(3, $p['records']);
        $this->assertFalse($p['formal_review_performed']);
        $this->assertSame('AE09_PUBLIC_ARCHIVE_NOT_AQ01_CUSTODY', $p['reference_namespace']);
        foreach ($p['records'] as $o) {
            $this->assertCount(1, $o['draft_links']);
            $this->assertSame('NOT_VERIFIED', $o['draft_links'][0]->verification_state);
        }
        $this->get('/sifi/workbench?candidate='.$c->id)->assertOk()->assertInertia(fn ($page) => $page->where('mode', 'REAL_PUBLIC_ARCHIVE')->where('focus.source_mode', 'REAL_PUBLIC_ARCHIVE'));
        $this->assertSame('UNASSESSED', $s->fresh()->rights_state);
        $this->assertSame('OFF', $s->fresh()->collector_state);
        $a = DB::table('sifi_issue_candidate_audit')->where('candidate_id', $c->id)->first();
        $this->assertSame('SOURCE_SNAPSHOT_IMPORTED', $a->action);
        $svc = app(ExecutiveBriefSource::class);
        [$case,$tr,$fs] = $svc->parents($c, $this->owner->id);
        $briefSource = $svc->assemble($c, $case, $tr, $fs);
        $this->assertStringNotContainsString('SIMULASI', $briefSource['trajectory']['direction']);
        $this->assertStringContainsString('percentage points', $briefSource['trajectory']['direction']);
        $this->get('/sifi/issue-candidates/'.$c->id.'/executive-brief')->assertOk();
        // Native composer/save/export route, no new publication channel.
        $brief = ['title' => 'Arsip pertumbuhan PDB Indonesia 2022–2024', 'audience' => 'Automated isolated acceptance',
            'executive_summary' => $briefSource['trajectory']['direction'], 'decision_questions' => 'Bagaimana definisi dan periode mempengaruhi interpretasi?',
            'verification_priorities' => 'Periksa rilis primer terbaru dan sumber lokal sebelum keputusan.',
            'limitations' => $p['source']['limitation'], 'review_state' => 'DRAFT', 'candidate_revision' => $c->revision, 'case_revision' => $case->revision, 'trajectory_revision' => $tr->revision, 'foresight_revision' => $fs->revision, 'input_digest' => $svc->digest($briefSource)];
        $this->post('/sifi/issue-candidates/'.$c->id.'/executive-brief', $brief)->assertSessionHasNoErrors()->assertRedirect();
        $out = $this->get('/sifi/issue-candidates/'.$c->id.'/executive-brief/download');
        $out->assertOk();
        foreach (['REAL PUBLIC ARCHIVE', 'The World Bank', 'creativecommons.org/licenses/by/4.0', '2022', '2023', '2024', '91a84ffd', 'NOT_VERIFIED'] as $text) {
            $this->assertStringContainsString($text,$out->getContent());
        }
        foreach (['Bukan data BMKG aktual', 'SYNTHETIC FIXTURE ONLY', 'SIMULASI', 'angka dibuat SIFI'] as $bad) {
            $this->assertStringNotContainsString($bad,$out->getContent());
        }
        $dir = getenv('R17_RESULT_DIR');
        $dir = $dir ? $dir.'/evidence' : null;
        if ($dir) {
            file_put_contents($dir.'/SOURCE_TO_BRIEF_DOWNLOAD.md',$out->getContent());
            file_put_contents($dir.'/SOURCE_TO_BRIEF_TRACE.json',json_encode($p,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        Http::assertNothingSent();
    }
}
