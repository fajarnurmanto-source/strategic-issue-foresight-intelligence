<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateIssueCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');
        return $candidate instanceof SifiIssueCandidate && $this->user()?->can('update', $candidate) === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'issue_hypothesis' => ['nullable', 'string', 'max:4000'],
            'exposure_notes' => ['nullable', 'string', 'max:4000'],
            'evidence_gaps' => ['nullable', 'string', 'max:4000'],
            'next_checks' => ['nullable', 'string', 'max:4000'],
            'review_state' => ['required', Rule::in(['DRAFT','REVIEW_REQUESTED','ON_HOLD'])],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('review_state') !== 'REVIEW_REQUESTED') { return; }
            foreach (['issue_hypothesis', 'evidence_gaps', 'next_checks'] as $field) {
                $value = $this->input($field);
                if (! is_string($value) || trim($value) === '') {
                    $validator->errors()->add($field, 'Wajib diisi sebelum meminta telaah manusia.');
                }
            }
        }];
    }
}
