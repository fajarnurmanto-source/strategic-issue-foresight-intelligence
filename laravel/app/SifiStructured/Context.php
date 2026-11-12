<?php

namespace App\SifiStructured;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Explicit fixture context only; no NLP inference or silent modality conversion. */
final readonly class Context
{
    /** @param array<array-key, mixed> $data */
    private function __construct(public array $data) {}

    /** @param array<array-key, mixed> $a */
    public static function from(array $a): self
    {
        $keys = 'schema_version,candidate_id,claim_id,claim_revision,event_id,subject_id,predicate_id,object_id,place_id,scope_id,time_start,time_end,modality,polarity,observed_at,fetched_at,provenance_mode,signal_ref';
        $rules = ['context' => ['required', 'array:'.$keys]];
        foreach (['schema_version', 'candidate_id', 'claim_id', 'claim_revision', 'event_id', 'subject_id', 'predicate_id', 'object_id', 'place_id', 'scope_id', 'time_start', 'time_end', 'modality', 'polarity', 'observed_at', 'fetched_at', 'provenance_mode', 'signal_ref'] as $k) {
            $rules['context.'.$k] = ['present'];
        }
        $rules['context.schema_version'] = ['required', Rule::in(['sifi.structured.context.v1'])];
        foreach (['candidate_id', 'claim_id'] as $k) {
            $rules['context.'.$k] = ['required', 'uuid'];
        }
        $rules['context.claim_revision'] = ['required', 'integer', 'between:1,1000000'];
        foreach (['event_id', 'subject_id', 'predicate_id', 'object_id', 'place_id', 'scope_id'] as $k) {
            $rules['context.'.$k] = ['required', 'string', 'max:80', 'regex:/\A[A-Z0-9_:-]+\z/D'];
        }
        foreach (['time_start', 'time_end', 'observed_at'] as $k) {
            $rules['context.'.$k] = ['required', 'date_format:Y-m-d\TH:i:s.u\Z'];
        }
        $rules['context.fetched_at'] = ['present', 'nullable', 'date_format:Y-m-d\TH:i:s.u\Z'];
        $rules['context.modality'] = ['required', Rule::in(['ASSERTED', 'REPORTED', 'ALLEGED', 'UNKNOWN'])];
        $rules['context.polarity'] = ['required', Rule::in(['POSITIVE', 'NEGATIVE'])];
        $rules['context.provenance_mode'] = ['required', Rule::in(['SYNTHETIC_EXPLICIT', 'UNATTESTED_LEGACY'])];
        $rules['context.signal_ref'] = ['required', 'array:authority,signal_id,signal_version,metadata_digest,receipt_ref'];
        $rules['context.signal_ref.authority'] = ['required', Rule::in(['AQ01'])];
        foreach (['signal_id', 'metadata_digest'] as $k) {
            $rules['context.signal_ref.'.$k] = ['required', 'regex:/\A[a-f0-9]{64}\z/D'];
        }
        $rules['context.signal_ref.signal_version'] = ['required', 'integer', 'between:1,1000000'];
        $rules['context.signal_ref.receipt_ref'] = ['required', 'string', 'max:100', 'regex:/\AFX-R-[A-Z0-9-]+\z/D'];
        $v = Validator::make(['context' => $a], $rules);
        $v->after(function ($v) use ($a) {
            if (! is_int($a['claim_revision'] ?? null) || ! is_int($a['signal_ref']['signal_version'] ?? null)) {
                $v->errors()->add('context', 'Strict integer required');
            }
            if (isset($a['time_start'],$a['time_end']) && $a['time_start'] > $a['time_end']) {
                $v->errors()->add('context.time_end', 'Invalid interval');
            }
        });
        $v->validate();

        return new self(self::sort($a));
    }

    /**
     * @param  array<array-key, mixed>  $a
     * @return array<array-key, mixed>
     */
    private static function sort(array $a): array
    {
        ksort($a);
        foreach ($a as &$v) {
            if (is_array($v)) {
                $v = self::sort($v);
            }
        }

        return $a;
    }

    public function json(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function digest(): string
    {
        return hash('sha256', $this->json());
    }
}
