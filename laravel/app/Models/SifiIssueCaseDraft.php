<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Human-authored issue organization draft; not a SIFI Python Issue Object. */
final class SifiIssueCaseDraft extends Model {
 protected $table='sifi_issue_case_drafts';
 protected $keyType='string';public $incrementing=false;
 protected $fillable=['id','candidate_id','owner_user_id','coverage_profile_id','title','issue_domain','geography_label','scope_note','lifecycle_state','revision'];
 protected function casts():array {return ['revision'=>'integer'];}
}
