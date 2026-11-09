<?php
namespace App\Http\Requests\Sifi;
use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
/** Run candidate policy before any form validation. */
final class SaveTrajectoryRequest extends FormRequest {
 public function authorize():bool {
  $candidate=$this->route('candidate');
  return $candidate instanceof SifiIssueCandidate && $this->user()?->can('update',$candidate)===true;
 }
 public function rules():array {
  $base=['candidate_revision'=>['required','integer','between:1,1000000'],
   'case_revision'=>['required','integer','between:1,1000000'],
   'draft_revision'=>['nullable','integer','between:1,1000000'],
   'verified'=>['prohibited'],'risk_score'=>['prohibited'],'evidence_admission'=>['prohibited'],
   'issue_object_id'=>['prohibited'],'confidence_percent'=>['prohibited']];
  if($this->route()?->getName()!=='sifi.trajectory.save')return $base;
  return $base+['horizon'=>['required',Rule::in(['UNSPECIFIED','SHORT_TERM','MEDIUM_TERM','LONG_TERM'])],
   'trajectory_hypothesis'=>['required','string','min:3','max:3000'],
   'risk_hypothesis'=>['required','string','min:3','max:3000'],
   'opportunity_hypothesis'=>['required','string','min:3','max:3000'],
   'watch_condition'=>['required','string','min:3','max:3000'],
   'uncertainty_note'=>['required','string','min:3','max:3000']];
 }
}
