<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Human-authored scenario hypotheses. No forecast, evidence admission or Issue Object. */
final class SifiForesightDraft extends Model {
 protected $table='sifi_foresight_drafts';
 protected $keyType='string';public $incrementing=false;
 protected $fillable=['id','trajectory_id','owner_user_id','baseline_scenario','alternative_scenario','stress_scenario',
   'critical_drivers','falsification_checks','decision_options','uncertainty_note','revision'];
 protected function casts():array{return ['revision'=>'integer'];}
}
