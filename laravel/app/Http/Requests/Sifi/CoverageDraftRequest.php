<?php

namespace App\Http\Requests\Sifi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CoverageDraftRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name'=>['required','string','min:3','max:180'],
            'level'=>['required',Rule::in(['CITY','PROVINCE','NATIONAL'])],
            'region_name'=>['required','string','min:2','max:180'],
            'domains'=>['required','array','min:1','max:12'],
            'domains.*'=>['required','string','distinct','max:90'],
            'source_ids'=>['sometimes','array','max:25'],
            'source_ids.*'=>['required','uuid','distinct'],
            'revision'=>[$this->isMethod('patch')?'required':'prohibited','integer','min:1'],
            'profile_state'=>['prohibited'], 'collector_state'=>['prohibited'],
        ];
    }
}
