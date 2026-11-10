<?php
namespace App\Http\Requests\Sifi;
use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;
/** Authorize via existing candidate policy BEFORE validation or binding details. */
final class SaveForesightRequest extends FormRequest {
 public function authorize():bool {
  $candidate=$this->route('candidate');
  return $candidate instanceof SifiIssueCandidate && $this->user()?->can('update',$candidate)===true;
 }
 public function rules():array {
  $base=['candidate_revision'=>['required','integer','between:1,1000000'],
   'case_revision'=>['required','integer','between:1,1000000'],
   'trajectory_revision'=>['required','integer','between:1,1000000'],
   'draft_revision'=>['nullable','integer','between:1,1000000'],
   'forecast_probability'=>['prohibited'],'risk_score'=>['prohibited'],
   'verified'=>['prohibited'],'issue_object_id'=>['prohibited'],
   'evidence_admission'=>['prohibited'],'decision_approved'=>['prohibited']];
  if($this->route()?->getName()!=='sifi.foresight.save')return $base;
  $fields=['baseline_scenario','alternative_scenario','stress_scenario','critical_drivers',
     'falsification_checks','decision_options','uncertainty_note'];
  foreach($fields as $name)$base[$name]=['required','string','min:3','max:2500'];
  return $base;
 }
}
