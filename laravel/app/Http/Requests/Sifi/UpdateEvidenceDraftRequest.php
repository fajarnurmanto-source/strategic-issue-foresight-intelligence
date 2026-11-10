<?php
namespace App\Http\Requests\Sifi;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\SifiEvidenceDraft;
use Illuminate\Validation\Rule;
final class UpdateEvidenceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evidence = $this->route('evidence');
        return $evidence instanceof SifiEvidenceDraft
            && $this->user()?->can('update', $evidence) === true;
    }
    public function rules(): array { return [
        'revision'=>['required','integer','min:1'],
        'claim_text'=>['required','string','min:5','max:3000'],
        'rationale'=>['nullable','string','max:4000'],
        'known_gaps'=>['nullable','string','max:4000'],
        'relationship'=>['required',Rule::in(['UNKNOWN','SUPPORTS','CONTRADICTS','CONTEXT'])],
        'signal_id'=>['prohibited'],'workflow_state'=>['prohibited'],
        'reviewer_user_id'=>['prohibited'],'evidence_admitted'=>['prohibited'],
        'rights_state'=>['prohibited'],'qualification_state'=>['prohibited'],
        'python_issue_id'=>['prohibited'],
    ]; }
}
