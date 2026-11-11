<?php
namespace App\Http\Requests\Sifi;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\SifiEvidenceDraft;
use Illuminate\Validation\Rule;
final class SubmitEvidenceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evidence = $this->route('evidence');
        return $evidence instanceof SifiEvidenceDraft
            && $this->user()?->can('review', $evidence) === true;
    }
    /** @return array<string, list<mixed>> */
    public function rules(): array { return [
        'revision'=>['required','integer','min:1'],
        'review_finding'=>['required',Rule::in(['INSUFFICIENT','FOLLOWUP_REQUIRED','INDEPENDENCE_UNRESOLVED'])],
        'review_notes'=>['required','string','min:10','max:5000'],
        'evidence_admitted'=>['prohibited'],'qualification_state'=>['prohibited'],
        'review_state'=>['prohibited'],
    ]; }
}
