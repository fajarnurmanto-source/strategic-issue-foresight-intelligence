<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiSourceDraft;
use App\Models\User;
use App\Services\Sifi\WorldBankAcquisitionWorkspace;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use App\Services\Sifi\WorldBankOneShotAcquisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/Support/Ae10MockWorldBankHarness.php';
final class WorldBankAcquisitionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private WorldBankOneShotAcquisition $service;

    private string $root;

    private array $receipts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->root = getenv('R17_RESULT_DIR');
        if (! app()->environment('testing') || config('database.connections.sqlite.database') !== ':memory:' || ! str_starts_with(realpath(storage_path()), '/home/fajar/.cache/sifi-validation/')) {
            throw new \RuntimeException('ISOLATED_VALIDATION_MEMORY_DATABASE_ONLY');
        }
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->app->bind(WorldBankOneShotAcquisition::class, fn () => new \Ae10MockWorldBankHarness);
        $this->service = app(WorldBankOneShotAcquisition::class);
        if (is_file($this->service->permitPath())) {
            unlink($this->service->permitPath());
        }
        foreach (glob(storage_path('app/private/sifi-worldbank-attempts/*.enc')) ?: [] as $f) {
            unlink($f);
        }
        file_put_contents($this->root.'/evidence/mock/scenario.txt', 'valid');
    }

    protected function tearDown(): void
    {
        if (is_file($this->service->permitPath())) {
            unlink($this->service->permitPath());
        }
        foreach ($this->receipts as $id) {
            $p = storage_path('app/private/sifi-worldbank-acquisitions/'.$id.'.enc');
            if (is_file($p)) {
                unlink($p);
            }
        }
        parent::tearDown();
    }

    private function grant(array $overrides = []): void
    {
        $p = array_replace(['version' => 1, 'owner_id' => (int) $this->owner->id, 'consumed' => false, 'endpoint' => WorldBankGdpSourceAdapter::ENDPOINT, 'kind' => 'MOCK_TEST_ONLY', 'max_records' => 3, 'max_bytes' => 65536, 'connect_seconds' => 2, 'total_seconds' => 5, 'retries' => 0, 'scheduler' => false, 'scope_sha256' => str_repeat('b', 64), 'approval_ref' => 'AUTOMATED_MOCK_TEST:AE11_NOT_HUMAN_APPROVAL', 'expires_at' => now()->timestamp + 300], $overrides);
        file_put_contents($this->service->permitPath(), Crypt::encryptString(json_encode($p)));
    }

    private function workspace(): WorldBankAcquisitionWorkspace
    {
        return app(WorldBankAcquisitionWorkspace::class);
    }

    private function calls(): int
    {
        return is_file($this->root.'/evidence/mock/requests.jsonl') ? count(file($this->root.'/evidence/mock/requests.jsonl')) : 0;
    }

    public function test_permission_states_are_honest_and_no_self_grant_or_network(): void
    {
        $n = $this->calls();
        $this->assertSame('MISSING', $this->workspace()->authorization($this->owner)['state']);
        foreach ([['consumed' => true, 'expires_at' => now()->timestamp - 1, 'expected' => 'CONSUMED'], ['expires_at' => now()->timestamp - 1, 'expected' => 'EXPIRED'], ['expected' => 'READY']] as $p) {
            $expected = $p['expected'];
            unset($p['expected']);
            $this->grant($p);
            $this->assertSame($expected, $this->workspace()->authorization($this->owner)['state']);
        }
        $this->assertSame('NO_SELF_GRANT', $this->workspace()->authorization($this->owner)['issuance']);
        $this->grant(['owner_id' => 999]);
        $status = $this->workspace()->authorization($this->owner);
        $this->assertSame('NOT_AVAILABLE', $status['state']);
        $this->assertNull($status['scope_sha256']);
        $this->assertNull($status['expires_at']);
        file_put_contents($this->service->permitPath(), 'tampered');
        $this->assertSame('NOT_AVAILABLE', $this->workspace()->authorization($this->owner)['state']);
        $this->assertSame($n, $this->calls());
        Http::assertNothingSent();
    }

    public function test_ui_post_records_success_and_replay_cannot_fetch_without_new_permission(): void
    {
        $this->grant();
        $n = $this->calls();
        $url = '/sifi/source-catalog/acquisition-preview';
        $this->actingAs($this->owner)->post($url, ['confirm_one_shot' => true])->assertSessionHasNoErrors()->assertRedirect();
        $id = session('ae10_acquisition.'.$this->owner->id);
        $this->receipts[] = $id;
        $w = $this->workspace()->view($this->owner);
        $this->assertSame('CONSUMED', $w['authorization']['state']);
        $this->assertSame($id, $w['receipts']['items'][0]['id']);
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $w['attempts']['items'][0]['state']);
        $this->post($url, ['confirm_one_shot' => true])->assertSessionHasErrors('acquisition')->assertRedirect('/sifi/source-catalog?scenario=wb_acquisition');
        $this->assertSame($n + 1, $this->calls());
        $this->assertCount(2, $this->workspace()->view($this->owner)['attempts']['items']);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_failed_http_attempt_is_visible_consumed_and_not_retried(): void
    {
        file_put_contents($this->root.'/evidence/mock/scenario.txt', 'rate');
        $this->grant();
        $n = $this->calls();
        $this->actingAs($this->owner)->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertSessionHasErrors('acquisition');
        $w = $this->workspace()->view($this->owner);
        $this->assertSame('CONSUMED', $w['authorization']['state']);
        $this->assertSame('RATE_LIMITED_NO_RETRY', $w['attempts']['items'][0]['reason']);
        $this->assertSame(1, $w['attempts']['items'][0]['transport']['attempts']);
        $this->assertCount(0, $w['receipts']['items']);
        $this->assertSame($n + 1, $this->calls());
    }

    public function test_owner_receipt_reopens_after_session_loss_without_fetch_and_foreign_is_hidden(): void
    {
        $this->grant();
        $p = $this->workspace()->run($this->owner);
        $id = $p['receipt_id'];
        $this->receipts[] = $id;
        $n = $this->calls();
        $this->actingAs($this->owner)->get('/sifi/source-catalog?scenario=wb_acquisition&receipt='.$id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('pipeline.receipt_id', $id)->where('pipeline.mode', 'MOCK_ACQUISITION_ONLY')->has('acquisitionWorkspace.receipts.items', 1));
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get('/sifi/source-catalog?scenario=wb_acquisition&receipt='.$id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('pipeline.reason', 'NO_OWNED_ACQUISITION')->has('acquisitionWorkspace.receipts.items', 0)->has('acquisitionWorkspace.attempts.items', 0));
        $this->assertSame($n, $this->calls());
    }

    public function test_receipt_query_validation_tamper_and_expiry_fail_closed(): void
    {
        $this->actingAs($this->owner)->getJson('/sifi/source-catalog?scenario=wb_acquisition&receipt=../../etc/passwd')->assertUnprocessable();
        $this->grant();
        $p = $this->workspace()->run($this->owner);
        $id = $p['receipt_id'];
        $this->receipts[] = $id;
        $path = storage_path('app/private/sifi-worldbank-acquisitions/'.$id.'.enc');
        $encoded = file_get_contents($path);
        file_put_contents($path, 'tampered');
        $this->assertCount(0, $this->workspace()->view($this->owner)['receipts']['items']);
        file_put_contents($path, $encoded);
        $expired = json_decode(Crypt::decryptString($encoded), true);
        $expired['retention_until'] = now()->subSecond()->toIso8601String();
        file_put_contents($path, Crypt::encryptString(json_encode($expired)));
        $w = $this->workspace()->view($this->owner);
        $this->assertSame('RETENTION_EXPIRED', $w['receipts']['items'][0]['state']);
        $this->assertSame('ACQUISITION_RETENTION_EXPIRED', $this->service->load($this->owner, $id)['reason']);
        $this->travelBack();
    }

    public function test_unauthorized_and_forged_requests_never_run_or_grant(): void
    {
        $n = $this->calls();
        $url = '/sifi/source-catalog/acquisition-preview';
        $this->post($url, ['confirm_one_shot' => true])->assertRedirect('/login');
        $this->actingAs($this->owner)->post($url, ['confirm_one_shot' => true, 'permit' => 'forged'])->assertSessionHasErrors('permit');
        $this->assertFalse(is_file($this->service->permitPath()));
        $this->assertSame($n, $this->calls());
    }

    public function test_recovered_owned_receipt_creates_existing_pipeline_draft_without_session_or_new_fetch(): void
    {
        $this->grant();
        $p = $this->workspace()->run($this->owner);
        $id = $p['receipt_id'];
        $this->receipts[] = $id;
        $n = $this->calls();
        $source = SifiSourceDraft::create(['id' => (string) Str::uuid(), 'user_id' => $this->owner->id, 'name' => 'AE11 MOCK source', 'source_type' => 'OPEN_DATA', 'homepage_url' => WorldBankGdpSourceAdapter::DOCUMENTATION, 'revision' => 1]);
        $this->assertNull(session('ae10_acquisition.'.$this->owner->id));
        $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft', ['scenario' => 'wb_acquisition', 'receipt_id' => $id, 'analyst_note' => 'MOCK test, not statistics or factual verification.', 'confirm_archive' => true, 'source_draft_id' => $source->id, 'source_revision' => 1, 'input_digest' => $p['packet_hash']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_evidence_drafts', 3);
        $this->assertSame($n, $this->calls());
    }

    public function test_foreign_receipt_cannot_create_draft_even_with_owned_registered_source(): void
    {
        $this->grant();
        $p = $this->workspace()->run($this->owner);
        $id = $p['receipt_id'];
        $this->receipts[] = $id;
        $n = $this->calls();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $source = SifiSourceDraft::create(['id' => (string) Str::uuid(), 'user_id' => $other->id, 'name' => 'AE11 other mock source', 'source_type' => 'OPEN_DATA', 'homepage_url' => WorldBankGdpSourceAdapter::DOCUMENTATION, 'revision' => 1]);
        $this->actingAs($other)->post('/sifi/source-catalog/pipeline-draft', ['scenario' => 'wb_acquisition', 'receipt_id' => $id, 'analyst_note' => 'Cannot reuse a foreign account receipt for a draft.', 'confirm_archive' => true, 'source_draft_id' => $source->id, 'source_revision' => 1, 'input_digest' => $p['packet_hash']])->assertSessionHasErrors('scenario');
        $this->assertDatabaseCount('sifi_issue_candidates',0);
        $this->assertDatabaseCount('sifi_evidence_drafts',0);
        $this->assertSame($n,$this->calls());
    }
}
