<?php
namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;

final class RunEvidenceDiagnosticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');
        return $candidate instanceof SifiIssueCandidate && $this->user()?->can('view', $candidate) === true;
    }
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'between:1,1000000'],
            'evidence_admitted' => ['prohibited'], 'qualification_state' => ['prohibited'],
            'issue_object_id' => ['prohibited'], 'source_url' => ['prohibited'],
        ];
    }
}
