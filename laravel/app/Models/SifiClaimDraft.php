<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Analyst-authored statement; never a verified claim. */
final class SifiClaimDraft extends Model {
    protected $table='sifi_claim_drafts'; protected $keyType='string'; public $incrementing=false;
    protected $fillable=['id','candidate_id','evidence_id','owner_user_id','statement','attribution','modality','revision'];
    protected function casts():array{return ['revision'=>'integer'];}
}
