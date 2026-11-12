<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Native Policy authorization occurs before field validation. */
final class StoreClaimEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof SifiIssueCandidate && $this->user()?->can('update', $candidate) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $common = ['candidate_revision' => ['required', 'integer', 'between:1,1000000'],
            'verified' => ['prohibited'], 'evidence_admission' => ['prohibited'],
            'issue_id' => ['prohibited'], 'event_verified' => ['prohibited']];
        $route = $this->route()?->getName();
        if ($route === 'sifi.claim-events.claim.store') {
            return $common + [
                'evidence_id' => ['required', 'uuid'], 'statement' => ['required', 'string', 'min:3', 'max:2000'],
                'attribution' => ['nullable', 'string', 'max:255'],
                'modality' => ['required', Rule::in(['UNDETERMINED', 'REPORTED', 'ALLEGED'])]];
        }
        if ($route === 'sifi.claim-events.event.store') {
            return $common + [
                'summary' => ['required', 'string', 'min:3', 'max:1000'],
                'time_note' => ['nullable', 'string', 'max:255'],
                'place_note' => ['nullable', 'string', 'max:255']];
        }
        if ($route === 'sifi.claim-events.link.store') {
            return $common + [
                'claim_id' => ['required', 'uuid'], 'event_id' => ['required', 'uuid'],
                'relation' => ['required', Rule::in(['UNKNOWN', 'POSSIBLE_ASSOCIATION'])]];
        }
        if ($route === 'sifi.claim-events.assess.store') {
            return $common;
        }

        return $common;
    }
}
