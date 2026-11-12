<?php

namespace Tests\Feature\Sifi;

use App\Contracts\Sifi\AiProviderAdapter;
use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;
use App\Data\Sifi\AiModelDescriptor;
use App\Data\Sifi\AiProviderCapabilities;
use App\Models\User;
use App\Services\Sifi\Ai\AiTaskCapabilityRegistry;
use App\Services\Sifi\Ai\SecureAiRuntime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecureAiRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private LocalAiSimulator $sim;

    private SecureAiRuntime $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->sim = new LocalAiSimulator;
        $this->runtime = new SecureAiRuntime($this->sim, new AiTaskCapabilityRegistry);
        RateLimiter::clear('sifi-ai:'.$this->owner->id);
    }

    private function invokeFixture(array $selection = [], string $task = 'summary_draft'): AiInferenceResult
    {
        return $this->runtime->invoke($this->owner, (int) $this->owner->id, $selection ?: ['enabled' => true, 'provider' => 'local-test', 'model' => 'fixture-1'], new AiInferenceRequest($task, ['private://never-log'], ['type' => 'object', 'properties' => ['summary' => ['type' => 'string']], 'required' => ['summary'], 'additionalProperties' => false], 1000, 'INTERNAL', 'secret-correlation'));
    }

    public function test_authorized_fixture_result_preserves_identity(): void
    {
        $r = $this->invokeFixture();
        $this->assertSame('AVAILABLE', $r->status);
        $this->assertSame(['summary' => 'SYNTHETIC ONLY'], $r->structuredOutput);
        $this->assertSame(1, $this->sim->calls);
        Http::assertNothingSent();
    }

    public function test_switching_models_rebinds_identity_and_disable_prevents_further_calls(): void
    {
        $this->sim->selectedModel = 'fixture-2';
        $r = $this->invokeFixture(['enabled' => true, 'provider' => 'local-test', 'model' => 'fixture-2']);
        $this->assertSame('fixture-2', $r->modelId);
        $this->assertSame('AVAILABLE', $r->status);
        $this->assertSame(['AI_DISABLED'], $this->invokeFixture(['enabled' => false])->warnings);
        $this->assertSame(1, $this->sim->calls);
    }

    public function test_unverified_user_cannot_invoke(): void
    {
        $this->owner->email_verified_at = null;
        $this->expectException(AuthorizationException::class);
        $this->invokeFixture();
    }

    public function test_unsupported_schema_is_an_explicit_failure(): void
    {
        $r = $this->runtime->invoke($this->owner, (int) $this->owner->id, ['enabled' => true, 'provider' => 'local-test', 'model' => 'fixture-1'], new AiInferenceRequest('summary_draft', [], ['type' => 'object', 'properties' => ['answer' => ['type' => 'object']], 'required' => ['answer'], 'additionalProperties' => false], 1000, 'PUBLIC', 'x'));
        $this->assertSame(['UNSUPPORTED_SCHEMA'], $r->warnings);
        $this->assertSame(0, $this->sim->calls);
    }

    public function test_foreign_owner_is_denied_before_adapter(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->runtime->invoke($this->owner, 999, ['enabled' => true], new AiInferenceRequest('summary_draft', [], [], 1000, 'PUBLIC', 'x'));
    }

    public function test_disabled_and_missing_configuration_do_not_invoke(): void
    {
        $this->assertSame(['AI_DISABLED'], $this->invokeFixture(['enabled' => false])->warnings);
        $this->assertSame(['UNSUPPORTED_MODEL'], $this->invokeFixture(['enabled' => true])->warnings);
        $this->assertSame(0, $this->sim->calls);
    }

    public function test_unsupported_task_and_model_fail_closed(): void
    {
        $this->assertSame(['UNSUPPORTED_TASK'], $this->invokeFixture(task: 'evidence_admission')->warnings);
        $this->assertSame(['UNSUPPORTED_MODEL'], $this->invokeFixture(['enabled' => true, 'provider' => 'other', 'model' => 'fixture-1'])->warnings);
    }

    public function test_disabled_provider_and_capability_fail_closed(): void
    {
        $this->sim->enabled = false;
        $this->assertSame(['PROVIDER_DISABLED'], $this->invokeFixture()->warnings);
        $this->sim->enabled = true;
        $this->assertSame(['UNSUPPORTED_CAPABILITY'], $this->invokeFixture(task: 'text_extraction')->warnings);
    }

    public function test_malformed_output_and_wrong_identity_rejected(): void
    {
        $this->sim->mode = 'malformed';
        $this->assertSame(['MALFORMED_OUTPUT'], $this->invokeFixture()->warnings);
        $this->sim->mode = 'identity';
        $this->assertSame(['MODEL_IDENTITY_MISMATCH'], $this->invokeFixture()->warnings);
    }

    public function test_timeout_retries_are_bounded(): void
    {
        $this->sim->mode = 'timeout';
        $this->assertSame(['CONNECTION_TIMEOUT'], $this->invokeFixture()->warnings);
        $this->assertSame(2, $this->sim->calls);
    }

    public function test_rate_limit_is_per_user_and_blocks_adapter(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->invokeFixture();
        }$this->assertSame(['RATE_LIMITED'], $this->invokeFixture()->warnings);
        $this->assertSame(10, $this->sim->calls);
        $other = User::factory()->create();
        $this->owner = $other;
        $this->assertSame('AVAILABLE', $this->invokeFixture()->status);
    }

    public function test_exception_secrets_are_not_logged(): void
    {
        Log::spy();
        $this->sim->mode = 'exception';
        $this->assertSame(['PROVIDER_FAILURE'], $this->invokeFixture()->warnings);
        Log::shouldHaveReceived('info')->with('sifi_ai_invocation', ['status' => 'UNAVAILABLE', 'task' => 'summary_draft', 'result_code' => 'PROVIDER_FAILURE'])->once();
    }

    public function test_settings_authorization_and_disable_are_session_scoped(): void
    {
        $this->get('/settings/ai-models')->assertRedirect('/login');
        $this->actingAs($this->owner)->get('/settings/ai-models')->assertOk()->assertInertia(fn ($p) => $p->component('settings/ai-models')->has('providers', 0));
        $this->withSession(['sifi_ai_selection' => ['enabled' => true]])->post('/settings/ai-models/disable')->assertSessionHas('sifi_ai_selection', ['enabled' => false]);
        Http::assertNothingSent();
    }
}

final class LocalAiSimulator implements AiProviderAdapter
{
    public int $calls = 0;

    public bool $enabled = true;

    public string $mode = 'valid';

    public string $selectedModel = 'fixture-1';

    public function capabilities(): AiProviderCapabilities
    {
        return new AiProviderCapabilities($this->enabled, ['summary_draft'], ['json'], false, false);
    }

    public function models(): array
    {
        return [new AiModelDescriptor('local-test', 'fixture-1', 'test', ['summary_draft']), new AiModelDescriptor('local-test', 'fixture-2', 'test', ['summary_draft'])];
    }

    public function infer(AiInferenceRequest $request): AiInferenceResult
    {
        $this->calls++;
        if ($this->mode === 'timeout') {
            throw new ConnectionException('secret endpoint');
        }
        if ($this->mode === 'exception') {
            throw new \RuntimeException('api_key=never-log');
        }

        return new AiInferenceResult('AVAILABLE',$this->mode === 'malformed' ? ['unknown' => 'bad'] : ['summary' => 'SYNTHETIC ONLY'],'local-test',$this->mode === 'identity' ? 'other' : $this->selectedModel,'test',0,0,0,[],['mode' => 'SYNTHETIC_ONLY']);
    }
}
