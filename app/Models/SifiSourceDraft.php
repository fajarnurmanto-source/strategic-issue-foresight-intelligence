<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Candidate-only catalog, not the Python qualified Source Registry. */
final class SifiSourceDraft extends Model
{
    protected $table = 'sifi_source_drafts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id','user_id','name','source_type','homepage_url','source_geography','notes','revision'];
    protected function casts(): array { return ['revision'=>'integer']; }
}
