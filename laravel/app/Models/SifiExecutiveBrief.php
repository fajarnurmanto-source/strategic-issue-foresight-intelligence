<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/**
 * Local human-written executive draft, never a final intelligence publication.
 *
 * These attributes are non-nullable text fields in the executive brief migration.
 *
 * @property string $executive_summary
 * @property string $decision_questions
 * @property string $verification_priorities
 * @property string $limitations
 */
final class SifiExecutiveBrief extends Model {
 protected $table='sifi_executive_briefs';
 protected $keyType='string';public $incrementing=false;
 protected $fillable=['id','candidate_id','owner_user_id','title','audience','executive_summary',
  'decision_questions','verification_priorities','limitations','review_state','input_digest',
  'input_snapshot_json','revision'];
 protected function casts():array{return ['revision'=>'integer'];}
}
