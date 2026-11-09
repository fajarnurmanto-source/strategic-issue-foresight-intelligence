<?php

namespace Tests\Unit\Sifi;

use App\Contracts\Sifi\AiProviderAdapter;
use App\Data\Sifi\AiInferenceRequest;
use App\Data\Sifi\AiInferenceResult;
use App\Services\Sifi\Ai\AiAuditEnvelope;
use App\Services\Sifi\Ai\AiTaskCapabilityRegistry;
use App\Services\Sifi\Ai\DisabledAiProviderAdapter;
use InvalidArgumentException;
use Tests\TestCase;

class AiProviderContractTest extends TestCase
{
    public function test_container_defaults_to_disabled_provider_without_models_or_network_capability(): void
    {
        $adapter = $this->app->make(AiProviderAdapter::class);

        $this->assertInstanceOf(DisabledAiProviderAdapter::class, $adapter);
        $this->assertFalse($adapter->capabilities()->enabled);
        $this->assertSame([], $adapter->capabilities()->tasks);
        $this->assertSame([], $adapter->models());
    }

    public function test_disabled_provider_fails_closed_and_performs_no_inference(): void
    {
        $request = $this->request();
        $result = $this->app->make(AiProviderAdapter::class)->infer($request);

        $this->assertSame('UNAVAILABLE', $result->status);
        $this->assertNull($result->structuredOutput);
        $this->assertSame(['AI_DISABLED'], $result->warnings);
        $this->assertFalse($result->provenance['external_call']);
        $this->assertSame('DETERMINISTIC_ONLY', $result->provenance['mode']);
    }

    public function test_registry_excludes_evidence_truth_and_review_authority_tasks(): void
    {
        $registry = $this->app->make(AiTaskCapabilityRegistry::class);

        $this->assertTrue($registry->allows('text_extraction'));
        $this->assertTrue($registry->allows('comparison_assist'));
        $this->assertFalse($registry->allows('evidence_admission'));
        $this->assertFalse($registry->allows('factual_verification'));
        $this->assertFalse($registry->allows('review_approval'));
    }

    public function test_audit_envelope_excludes_input_and_structured_output_payloads(): void
    {
        $request = $this->request(['private://candidate/123']);
        $result = AiInferenceResult::unavailable('AI_DISABLED', [
            'external_call' => false,
            'api_key' => 'must-not-be-logged',
            'raw_response' => 'must-not-be-logged',
        ]);
        $audit = $this->app->make(AiAuditEnvelope::class)->build($request, $result);
        $serialized = json_encode($audit, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('private://candidate/123', $serialized);
        $this->assertStringNotContainsString('must-not-be-logged', $serialized);
        $this->assertArrayNotHasKey('input_references', $audit);
        $this->assertArrayNotHasKey('structured_output', $audit);
        $this->assertArrayNotHasKey('api_key', $audit['provenance']);
        $this->assertArrayNotHasKey('raw_response', $audit['provenance']);
        $this->assertSame(1, $audit['input_reference_count']);
        $this->assertSame('UNAVAILABLE', $audit['status']);
    }

    public function test_request_and_result_reject_unsafe_shapes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AiInferenceRequest('summary_draft', [], [], 99, 'PUBLIC', 'corr-1');
    }

    public function test_non_available_result_cannot_carry_output(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AiInferenceResult('ABSTAIN', ['answer' => 'unsupported'], null, null, null, 0, 0, 0, [], []);
    }

    /** @param list<string> $references */
    private function request(array $references = ['fixture://claim/1']): AiInferenceRequest
    {
        return new AiInferenceRequest(
            task: 'comparison_assist',
            inputReferences: $references,
            outputSchema: ['type' => 'object'],
            timeoutMilliseconds: 2_000,
            privacyClass: 'INTERNAL',
            correlationId: 'corr-test-1',
        );
    }
}
