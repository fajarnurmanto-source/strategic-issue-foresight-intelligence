<?php
namespace App\Http\Requests\Sifi;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\SifiEvidenceDraft;
use App\Models\SifiIssueCandidate;
use Illuminate\Validation\Rule;
final class StoreEvidenceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');
        return $candidate instanceof SifiIssueCandidate
            && $this->user()?->can('create', [SifiEvidenceDraft::class, $candidate]) === true;
    }
    public function rules(): array { return [
        'signal_id'=>['required','regex:/\A[a-fA-F0-9]{64}\z/'],
        'claim_text'=>['required','string','min:5','max:3000'],
        'rationale'=>['nullable','string','max:4000'],
        'known_gaps'=>['nullable','string','max:4000'],
        'relationship'=>['required',Rule::in(['UNKNOWN','SUPPORTS','CONTRADICTS','CONTEXT'])],
        'workflow_state'=>['prohibited'],'reviewer_user_id'=>['prohibited'],
        'evidence_admitted'=>['prohibited'],'rights_state'=>['prohibited'],
        'qualification_state'=>['prohibited'],'python_issue_id'=>['prohibited'],
    ]; }
}
