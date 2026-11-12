<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ClaimComparisonPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $c = $this->route('candidate');

        return $c instanceof SifiIssueCandidate && $this->user()?->can('view', $c) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['candidate_revision' => ['required', 'integer', 'between:1,1000000'], 'input_digest' => ['required', 'regex:/\A[a-f0-9]{64}\z/D'], 'request_nonce' => ['required', 'uuid'], 'cutoff' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'before_or_equal:now'], 'mode' => ['required', Rule::in(['candidate', 'fixture'])], 'fixture_pair' => ['required_if:mode,fixture', 'prohibited_if:mode,candidate', Rule::in(['match', 'conflict', 'scope', 'modality'])]];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v): void {
            if (array_diff(array_keys($this->all()), ['candidate_revision', 'input_digest', 'request_nonce', 'cutoff', 'mode', 'fixture_pair', '_token'])) {
                $v->errors()->add('request', 'Unknown fields');
            }if (! is_int($this->input('candidate_revision'))) {
                $v->errors()->add('candidate_revision', 'Strict integer required');
            }
        });
    }
}
