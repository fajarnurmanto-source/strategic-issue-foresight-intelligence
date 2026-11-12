<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiIssueCandidate;
use App\Models\SifiMonitoringObject;
use App\Models\SifiSourceDraft;
use App\Models\User;
use App\Services\Sifi\ExecutiveBriefSource;
use App\Services\Sifi\ResearchSessionWorkspace;
use App\Services\Sifi\ResearchWorldBankAcquisition;
use App\Services\Sifi\SourceDraftRegistration;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use App\Services\Sifi\WorldBankResearchWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/Support/Net02InProcessWorldBankHarness.php';

final class WorldBankResearchTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    private SifiMonitoringObject $o;

    private string $session;

    private WorldBankResearchWorkspace $w;

    private ResearchWorldBankAcquisition $t;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->app->bind(ResearchWorldBankAcquisition::class, fn ($app, $v) => new \Net02InProcessWorldBankHarness($v['context']));
        $this->u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->u);
        $this->o = SifiMonitoringObject::create(['id' => (string) Str::uuid(), 'user_id' => $this->u->id, 'name' => '[SYNTHETIC] Konteks makro', 'description' => 'Objek fixture untuk source pack GDP nasional.', 'category' => 'Ekonomi', 'aliases_json' => ['PDB'], 'keywords_json' => ['pertumbuhan'], 'exclusions_json' => [], 'geography' => 'Indonesia', 'objective' => 'Menelaah konteks makro historis.', 'horizon' => 'historical', 'state' => 'CONFIGURED', 'revision' => 1]);
        $this->session = app(ResearchSessionWorkspace::class)->create($this->u, $this->o, ['question' => 'Bagaimana konteks PDB nasional 2022 sampai 2024?', 'object_revision' => 1, 'query_limit' => 3, 'result_limit' => 5]);
        $this->w = app(WorldBankResearchWorkspace::class);
        $this->context = $this->w->context($this->u, $this->o, $this->session);
        $this->t = $this->w->transport($this->context);
    }

    private function url(): string
    {
        return '/sifi/monitoring-objects/'.$this->o->id.'/research-worldbank';
    }

    private function grant(array $bad = []): void
    {
        $p = array_replace(['version' => 1, 'research_authorization_version' => 2, 'research_context' => $this->context, 'nonce' => bin2hex(random_bytes(32)), 'method' => 'GET', 'owner_id' => (int) $this->u->id, 'consumed' => false, 'endpoint' => WorldBankGdpSourceAdapter::ENDPOINT, 'kind' => 'MOCK_TEST_ONLY', 'max_records' => 3, 'max_bytes' => 65536, 'connect_seconds' => 2, 'total_seconds' => 5, 'retries' => 0, 'scheduler' => false, 'scope_sha256' => str_repeat('a', 64), 'approval_ref' => 'AUTOMATED_MOCK_TEST:NET02_NOT_HUMAN_AUTHORITY', 'expires_at' => now()->timestamp + 300], $bad);
        $f = $this->t->permitPath();
        if (! is_dir(dirname($f))) {
            mkdir(dirname($f), 0700, true);
        }file_put_contents($f, Crypt::encryptString(json_encode($p, JSON_THROW_ON_ERROR)));
    }

    private function decision(): object
    {
        $this->w->decide($this->u, $this->o, ['session_id' => $this->session, 'object_revision' => 1, 'context_sha256' => ResearchWorldBankAcquisition::digest($this->context)]);

        return DB::table('sifi_research_retrievals')->where('session_id', $this->session)->first();
    }

    private function fetch(string $scenario = 'valid'): object
    {
        config(['sifi_net02.scenario' => $scenario]);
        $r = $this->decision();
        $this->grant();
        $this->w->acquire($this->u, $this->o, $r->id, ['revision' => 1, 'object_revision' => 1]);

        return DB::table('sifi_research_retrievals')->find($r->id);
    }

    private function source(): SifiSourceDraft
    {
        return app(SourceDraftRegistration::class)->create($this->u, ['name' => '[SYNTHETIC NET02] World Bank schema fixture', 'source_type' => 'OPEN_DATA', 'homepage_url' => WorldBankGdpSourceAdapter::DOCUMENTATION, 'source_geography' => 'Indonesia schema fixture', 'notes' => 'SYNTHETIC TEST ONLY, not actual statistics.']);
    }

    private function import(object $r, SifiSourceDraft $s): void
    {
        $this->w->import($this->u, $this->o, $r->id, ['revision' => $r->revision, 'object_revision' => 1, 'document_sha256' => $r->document_sha256, 'source_id' => $s->id, 'source_revision' => $s->revision, 'analyst_note' => 'Pengujian otomatis sintetis, bukan statistik atau validasi manusia.']);
    }

    public function test_missing_ready_consumed_states_and_exact_context(): void
    {
        $this->assertSame('MISSING', $this->t->authorityState($this->u));
        $r = $this->decision();
        try {
            $this->w->acquire($this->u, $this->o, $r->id, ['revision' => 1, 'object_revision' => 1]);
            $this->fail('No authority');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }$this->grant();
        $this->assertSame('READY', $this->t->authorityState($this->u));
        $this->w->acquire($this->u, $this->o, $r->id, ['revision' => 1, 'object_revision' => 1]);
        $this->assertSame('CONSUMED', $this->t->authorityState($this->u));
        $r = DB::table('sifi_research_retrievals')->find($r->id);
        $this->assertSame('WB_DOCUMENT_SNAPSHOT', $r->state);
        $d = json_decode($r->document_json, true);
        $this->assertSame(hash('sha256', $d['body']), $d['body_sha256']);
        $q = $d['quote'];
        $this->assertSame($q['text'], substr($d['body'], $q['byte_start'], $q['byte_end'] - $q['byte_start']));
        $this->assertSame('MOCK_ACQUISITION_ONLY', $d['mode']);
        $this->assertNull($d['published_at']);
        Http::assertNothingSent();
    }

    public function test_expired_foreign_and_legacy_grants_fail_closed(): void
    {
        foreach ([['expires_at' => now()->timestamp - 1], ['owner_id' => 999], ['research_authorization_version' => 1], ['research_context' => array_replace($this->context, ['session_id' => (string) Str::uuid()])], ['method' => 'POST'], ['endpoint' => 'https://127.0.0.1/'], ['max_bytes' => 65537], ['retries' => 1]] as $bad) {
            $this->grant($bad);
            $this->assertNotSame('READY', $this->t->authorityState($this->u));
        }$this->grant(['consumed' => true]);
        $this->assertSame('CONSUMED', $this->t->authorityState($this->u));
    }

    public function test_redirect_dns_oversize_timeout_json_mime_partial_duplicate_stop_without_retry(): void
    {
        foreach (['redirect', 'dns', 'oversize', 'timeout', 'malformed', 'mime', 'partial', 'duplicate', 'encoding', 'rate'] as $scenario) {
            config(['sifi_net02.scenario' => $scenario]);
            $this->grant();
            $p = $this->t->acquire($this->u);
            $this->assertSame('BLOCKED', $p['status'], $scenario);
            $this->assertSame('CONSUMED', $this->t->authorityState($this->u));
            $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->t->acquire($this->u)['reason']);
        }Http::assertNothingSent();
    }

    public function test_dns_policy_rejects_private_reserved_and_mixed_sets(): void
    {
        foreach ([[], ['127.0.0.1'], ['10.0.0.1'], ['169.254.169.254'], ['::1'], ['8.8.8.8', '192.168.1.1']] as $ips) {
            try {
                $this->t->publicAddress($ips);
                $this->fail('Unsafe DNS');
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }$this->assertSame('8.8.8.8', $this->t->publicAddress(['8.8.8.8']));
    }

    public function test_foreign_owner_and_stale_object_or_plan_are_denied(): void
    {
        $r = $this->decision();
        $this->grant();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $this->get($this->url())->assertForbidden();
        $this->actingAs($this->u);
        DB::table('sifi_research_sessions')->where('id', $this->session)->update(['revision' => 2]);
        $this->post($this->url().'/'.$r->id.'/acquire', ['revision' => 1, 'object_revision' => 1, 'confirm_one_shot' => true])->assertSessionHasErrors('research');
        $this->assertSame('READY', $this->t->authorityState($this->u));
    }

    public function test_rights_unknown_blocks_before_consumption(): void
    {
        $this->grant();
        $path = resource_path('sifi/ae09-wb-rights.json');
        $old = file_get_contents($path);
        try {
            $rights = json_decode($old, true);
            $rights['state'] = 'UNKNOWN';
            file_put_contents($path, json_encode($rights));
            $this->assertSame('RIGHTS_NOT_SATISFIED', $this->t->acquire($this->u)['reason']);
        } finally {
            file_put_contents($path, $old);
        }$this->assertSame('READY', $this->t->authorityState($this->u));
    }

    public function test_receipt_tamper_is_detected_and_recovery_does_not_refetch(): void
    {
        $r = $this->fetch();
        $this->w->pipelinePreview($this->u, $r->id);
        $id = $this->t->expectedReceiptId();
        $path = storage_path('app/private/sifi-research-worldbank-receipts/'.$id.'.enc');
        file_put_contents($path, 'tampered');
        $this->expectException(ValidationException::class);
        $this->w->pipelinePreview($this->u, $r->id);
    }

    public function test_full_document_to_existing_pipeline_saved_downloaded_brief(): void
    {
        $r = $this->fetch();
        $source = $this->source();
        $this->import($r, $source);
        $c = SifiIssueCandidate::firstOrFail();
        $svc = app(ExecutiveBriefSource::class);
        [$case,$trajectory,$foresight] = $svc->parents($c, $this->u->id);
        $input = $svc->assemble($c, $case, $trajectory, $foresight);
        $url = '/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->post($url, ['candidate_revision' => 1, 'case_revision' => 1, 'trajectory_revision' => 1, 'foresight_revision' => 1, 'input_digest' => $svc->digest($input), 'title' => '[MOCK] Research document to brief', 'audience' => 'Pengujian otomatis', 'executive_summary' => 'SYNTHETIC MOCK ONLY. Tiga nilai uji lokal bukan statistik resmi atau kesimpulan aktual.', 'decision_questions' => 'Data primer apa yang diperlukan untuk menilai kondisi aktual?', 'verification_priorities' => 'Periksa rights, provenance, periode, original body hash dan source publication UNKNOWN.', 'limitations' => 'SYNTHETIC MOCK, NOT VERIFIED, evidence NOT_EVALUATED, tidak ada otorisasi keputusan.', 'review_state' => 'DRAFT'])->assertSessionHasNoErrors();
        $download = $this->get($url.'/download')->assertOk();
        $doc = json_decode($r->document_json, true);
        $download->assertSee($doc['body_sha256'], false)->assertSee($this->session, false)->assertSee('NOT VERIFIED', false);
        $this->assertDatabaseCount('sifi_claim_drafts', 3);
        $this->assertDatabaseMissing('sifi_claim_drafts', ['verification_state' => 'VERIFIED']);
        $this->assertDatabaseCount('jobs', 0);
        if (getenv('R17_RESULT_DIR')) {
            file_put_contents(getenv('R17_RESULT_DIR').'/evidence/NET02_SOURCE_TO_BRIEF.md', $download->getContent());
        }
    }

    public function test_downstream_failure_preserves_consumed_authority_and_snapshot(): void
    {
        $r = $this->fetch();
        $s = $this->source();
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "sifi_foresight_drafts"')) {
                throw new \RuntimeException('NET02_FAILURE');
            }
        });
        try {
            $this->import($r, $s);
            $this->fail('Failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('NET02_FAILURE', $e->getMessage());
        }$this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseHas('sifi_research_retrievals', ['id' => $r->id, 'state' => 'WB_DOCUMENT_SNAPSHOT']);
        $this->assertSame('CONSUMED', $this->t->authorityState($this->u));
    }

    public function test_url_overrides_cannot_grant_or_fetch_and_get_is_readonly(): void
    {
        $r = $this->decision();
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $p) => $p->where('authorization.state', 'MISSING'));
        $this->post($this->url().'/'.$r->id.'/acquire', ['revision' => 1, 'object_revision' => 1, 'confirm_one_shot' => true, 'endpoint' => 'http://127.0.0.1/'])->assertSessionHasErrors('endpoint');
        $this->assertDatabaseCount('sifi_research_retrieval_audit', 1);
        Http::assertNothingSent();
    }

    public function test_consumed_receipt_recovers_after_unsaved_snapshot_without_request(): void
    {
        $r = $this->decision();
        $this->grant();
        $preview = $this->t->acquire($this->u);
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $preview['status']);
        $this->assertSame('CONSUMED', $this->t->authorityState($this->u));
        config(['sifi_net02.scenario' => 'timeout']);
        $this->w->acquire($this->u, $this->o, $r->id, ['revision' => 1, 'object_revision' => 1], true);
        $saved = DB::table('sifi_research_retrievals')->find($r->id);
        $this->assertSame('WB_DOCUMENT_SNAPSHOT', $saved->state);
        $this->assertSame($preview['document'], json_decode($saved->document_json, true));
        $this->assertDatabaseCount('sifi_research_retrieval_audit', 2);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        Http::assertNothingSent();
    }

    public function test_additional_reserved_dns_ranges_are_rejected(): void
    {
        foreach (['100.64.0.1', '192.0.0.1', '192.0.2.1', '198.18.0.1', '198.51.100.1', '203.0.113.1', '224.0.0.1', '240.0.0.1'] as $ip) {
            try {
                $this->t->publicAddress([$ip]);
                $this->fail('Reserved DNS '.$ip);
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_session_mismatch_expiry_and_foreign_owner_cannot_consume_authority(): void
    {
        $r = $this->decision();
        $this->grant();
        $other = $this->o->replicate();
        $other->id = (string) Str::uuid();
        $other->save();
        $this->get('/sifi/monitoring-objects/'.$other->id.'/research-worldbank?session='.$this->session)->assertNotFound();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $this->post($this->url().'/'.$r->id.'/acquire', ['revision' => 1, 'object_revision' => 1, 'confirm_one_shot' => true])->assertForbidden();
        $this->actingAs($this->u);
        DB::table('sifi_research_sessions')->where('id', $this->session)->update(['expires_at' => now()->subSecond()]);
        $this->post($this->url().'/'.$r->id.'/acquire', ['revision' => 1, 'object_revision' => 1, 'confirm_one_shot' => true])->assertSessionHasErrors('research');
        $this->assertSame('READY', $this->t->authorityState($this->u));
        $this->assertDatabaseCount('sifi_research_retrieval_audit', 1);
        Http::assertNothingSent();
    }

    public function test_document_hash_and_span_tamper_cannot_enter_pipeline(): void
    {
        $r = $this->fetch();
        $doc = json_decode($r->document_json, true);
        foreach (['body', 'span', 'quote', 'rights'] as $kind) {
            $bad = $doc;
            if ($kind === 'body') {
                $bad['body'] .= 'tampered';
            }
            if ($kind === 'span') {
                $bad['quote']['byte_start']++;
            }
            if ($kind === 'quote') {
                $bad['quote']['text'] = 'forged';
            }
            if ($kind === 'rights') {
                $bad['rights'] = 'APPROVED_FOR_EVIDENCE';
            }
            $raw = json_encode($bad, JSON_THROW_ON_ERROR);
            DB::table('sifi_research_retrievals')->where('id', $r->id)->update(['document_json' => $raw, 'document_sha256' => hash('sha256', $raw)]);
            try {
                $this->w->pipelinePreview($this->u, $r->id);
                $this->fail('Tampered '.$kind);
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    public function test_consumed_authority_cannot_replay_and_mock_document_rights_stay_synthetic(): void
    {
        $r = $this->fetch();
        $doc = json_decode($r->document_json, true);
        $this->assertSame('SIFI_SYNTHETIC_TEST_ONLY', $doc['rights']);
        $this->assertSame('NOT_EVALUATED', $doc['admission']);
        $this->assertTrue(mb_check_encoding($doc['quote']['text'], 'UTF-8'));
        $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->t->acquire($this->u)['reason']);
        $this->post($this->url().'/'.$r->id.'/acquire', ['revision' => $r->revision, 'object_revision' => 1, 'confirm_one_shot' => true])->assertSessionHasErrors('research');
        $this->assertDatabaseCount('sifi_research_retrieval_audit', 2);
        Http::assertNothingSent();
    }

    public function test_malformed_nonce_fails_closed_without_consumption_or_receipt_identity(): void
    {
        foreach ([null, [], 123, 'short'] as $nonce) {
            $this->grant(['nonce' => $nonce]);
            $this->assertSame('BLOCKED', $this->t->authorityState($this->u));
            $this->assertNull($this->t->expectedReceiptId());
            $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->t->acquire($this->u)['reason']);
        }
        Http::assertNothingSent();
    }

    public function test_provider_grant_and_admission_spoofing_are_rejected_before_decision(): void
    {
        $this->post($this->url(), [
            'session_id' => $this->session, 'object_revision' => 1, 'context_sha256' => ResearchWorldBankAcquisition::digest($this->context),
            'provider' => 'ATTACKER', 'grant' => 'forged', 'permit' => 'forged', 'receipt' => 'forged', 'rights' => 'VERIFIED', 'verified' => true, 'evidence_admitted' => true, 'cost' => 1, 'retry' => 1, 'scheduler' => true,
        ])->assertSessionHasErrors(['provider', 'grant', 'permit', 'receipt', 'rights', 'verified', 'evidence_admitted', 'cost', 'retry', 'scheduler']);
        $this->assertDatabaseCount('sifi_research_retrievals',0);
        $this->assertDatabaseCount('sifi_research_retrieval_audit',0);
        $this->assertSame('MISSING',$this->t->authorityState($this->u));
        Http::assertNothingSent();
    }
}
