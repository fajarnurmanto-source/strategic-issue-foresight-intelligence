<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Analyst hypothesis container, never verified SIFI trajectory/risk/impact. */
final class SifiTrajectoryDraft extends Model {
 protected $table='sifi_trajectory_drafts';
 protected $keyType='string';public $incrementing=false;
 protected $fillable=['id','case_id','owner_user_id','horizon','trajectory_hypothesis','risk_hypothesis','opportunity_hypothesis','watch_condition','uncertainty_note','revision'];
 protected function casts():array{return ['revision'=>'integer'];}
}
