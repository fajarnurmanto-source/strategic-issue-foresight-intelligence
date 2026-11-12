<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;

/** Policy runs before validation to protect cross-user source detail. */
final class SaveExecutiveBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        $c = $this->route('candidate');

        return $c instanceof SifiIssueCandidate && $this->user()?->can('update', $c) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'candidate_revision' => ['required', 'integer', 'between:1,1000000'],
            'case_revision' => ['required', 'integer', 'between:1,1000000'],
            'trajectory_revision' => ['required', 'integer', 'between:1,1000000'],
            'foresight_revision' => ['required', 'integer', 'between:1,1000000'],
            'brief_revision' => ['nullable', 'integer', 'between:1,1000000'],
            'input_digest' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'audience' => ['required', 'string', 'min:3', 'max:120'],
            'executive_summary' => ['required', 'string', 'min:15', 'max:4000'],
            'decision_questions' => ['required', 'string', 'min:10', 'max:3500'],
            'verification_priorities' => ['required', 'string', 'min:10', 'max:3500'],
            'limitations' => ['required', 'string', 'min:10', 'max:3500'],
            'review_state' => ['required', 'in:DRAFT,REVIEW_REQUESTED'],
            'verified' => ['prohibited'], 'published' => ['prohibited'], 'evidence_admitted' => ['prohibited'],
            'issue_object_id' => ['prohibited'], 'decision_approved' => ['prohibited'],
            'forecast_probability' => ['prohibited'], 'risk_score' => ['prohibited'],
        ];
    }
}
