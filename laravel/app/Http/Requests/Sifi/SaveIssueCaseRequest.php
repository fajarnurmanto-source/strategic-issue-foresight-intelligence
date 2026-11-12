<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Laravel policy check BEFORE validating input. */
final class SaveIssueCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $c = $this->route('candidate');

        return $c instanceof SifiIssueCandidate && $this->user()?->can('update', $c) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $common = ['candidate_revision' => ['required', 'integer', 'between:1,1000000'],
            'case_revision' => ['nullable', 'integer', 'between:1,1000000'],
            'verified' => ['prohibited'], 'evidence_admission' => ['prohibited'],
            'python_issue_id' => ['prohibited'], 'risk_score' => ['prohibited']];
        if ($this->route()?->getName() === 'sifi.issue-case.save') {
            return $common + [
                'title' => ['required', 'string', 'min:3', 'max:180'],
                'issue_domain' => ['required', 'string', 'min:2', 'max:120'],
                'geography_label' => ['required', 'string', 'min:2', 'max:180'],
                'scope_note' => ['required', 'string', 'min:3', 'max:4000'],
                'coverage_profile_id' => ['nullable', 'uuid'],
                'lifecycle_state' => ['required', Rule::in(['DRAFT', 'WATCHLIST', 'ON_HOLD', 'CLOSED_DRAFT'])],
            ];
        }

        return $common;
    }
}
