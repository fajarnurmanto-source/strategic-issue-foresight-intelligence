<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiEvidenceDraft;
use Illuminate\Foundation\Http\FormRequest;

final class AssignEvidenceReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evidence = $this->route('evidence');

        return $evidence instanceof SifiEvidenceDraft
            && $this->user()?->can('assign', $evidence) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'reviewer_email' => ['required', 'email', 'max:255'],
            'workflow_state' => ['prohibited'], 'evidence_admitted' => ['prohibited'],
            'qualification_state' => ['prohibited'],
        ];
    }
}
