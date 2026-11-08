<?php
namespace App\Http\Requests\Sifi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
final class MonitoringObjectRequest extends FormRequest {
 public function authorize():bool{return $this->user()?->hasVerifiedEmail()===true;}
 public function rules():array {
  $r=['name'=>['required','string','min:3','max:180'],'description'=>['required','string','min:10','max:2000'],
   'category'=>['required','string','min:2','max:100'],'geography'=>['nullable','string','min:2','max:180'],
   'objective'=>['required','string','min:10','max:2000'],'horizon'=>['required','string','min:3','max:160'],
   'state'=>['required',Rule::in(['CONFIGURED','PAUSED','ARCHIVED'])],
   'revision'=>[$this->isMethod('patch')?'required':'prohibited','integer','min:1'],
   'source_ids'=>['present','array','max:25'],'source_ids.*'=>['required','uuid','distinct'],
   'ae16_filter'=>['sometimes','required','array'],
  'ae16_filter.query'=>['required_with:ae16_filter','string','min:2','max:600'],
  'ae16_filter.date_window'=>['required_with:ae16_filter','array'],
  'ae16_filter.date_window.start'=>['required_with:ae16_filter','date_format:Y-m-d'],
  'ae16_filter.date_window.end_exclusive'=>['required_with:ae16_filter','date_format:Y-m-d','after:ae16_filter.date_window.start'],
  'ae16_filter.date_window.time_basis'=>['required_with:ae16_filter',Rule::in(['publication'])],
  'ae16_filter.date_window.timezone'=>['required_with:ae16_filter',Rule::in(['Asia/Jakarta'])],
  'ae16_filter.geography'=>['required_with:ae16_filter',Rule::in(['KOTA_YOGYAKARTA'])],
  'ae16_filter.media_types'=>['required_with:ae16_filter','array','min:1','max:5'],
  'ae16_filter.media_types.*'=>['required','string','distinct',Rule::in(['news','video','audio','image','document'])],
  'ae16_filter.sentiment'=>['required_with:ae16_filter',Rule::in(['positive','negative','neutral','all'])],
  'ae16_filter.verification'=>['sometimes',Rule::in(['ALL'])],
  'user_id'=>['prohibited'],'collector_state'=>['prohibited'],'verified'=>['prohibited'],'grant'=>['prohibited'],'schedule'=>['prohibited']];
  foreach(['aliases'=>20,'keywords'=>30,'exclusions'=>30] as $f=>$max){$r[$f]=['present','array','max:'.$max];$r[$f.'.*']=['required','string','max:100','distinct:ignore_case'];}
  return $r;
 }
}
