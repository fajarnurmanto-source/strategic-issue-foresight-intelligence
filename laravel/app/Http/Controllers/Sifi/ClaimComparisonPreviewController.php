<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\ClaimComparisonPreviewRequest;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\{ClaimComparisonPreview,ClaimComparisonPreviewInput,ClaimComparisonInputDiagnostics};
use Illuminate\Support\Facades\{DB,Gate};
final class ClaimComparisonPreviewController extends Controller {
 private function digest(array $input):string {return hash('sha256',json_encode($input,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
 private function input(ClaimComparisonPreviewInput $source,SifiIssueCandidate $candidate):array {try{return $source->read($candidate);}catch(\Illuminate\Database\QueryException){abort(503,'Preview data unavailable.');}}
 public function show(SifiIssueCandidate $candidate,ClaimComparisonPreviewInput $source,ClaimComparisonPreview $preview,ClaimComparisonInputDiagnostics $diagnostics){
  Gate::authorize('view',$candidate);[$candidate,$input]=DB::transaction(function()use($candidate,$source){$fresh=SifiIssueCandidate::query()->findOrFail($candidate->id);Gate::authorize('view',$fresh);return [$fresh,$this->input($source,$fresh)];});
  try{$details=$diagnostics->summarize($input);}catch(\InvalidArgumentException){abort(503,'Preview diagnostics unavailable.');}
  return \Inertia\Inertia::render('sifi/claim-comparison-preview',['candidate'=>['id'=>$candidate->id,'title'=>$candidate->title,'revision'=>(int)$candidate->revision],'inputDigest'=>$this->digest($input),'pairs'=>$preview->pairs(),'initialCutoff'=>now()->utc()->format('Y-m-d\TH:i:s\Z'),'diagnostics'=>$details]);
 }
 public function compare(ClaimComparisonPreviewRequest $request,SifiIssueCandidate $candidate,ClaimComparisonPreviewInput $source,ClaimComparisonPreview $preview){
  $v=$request->validated();
  $input=DB::transaction(function()use($candidate,$source,$v){$fresh=SifiIssueCandidate::query()->findOrFail($candidate->id);Gate::authorize('view',$fresh);abort_unless((int)$fresh->revision===$v['candidate_revision'],409);$input=$this->input($source,$fresh);abort_unless(hash_equals($this->digest($input),$v['input_digest']),409);return $input;});
  $out=$preview->evaluate($input,$v);
  return DB::transaction(function()use($candidate,$source,$input,$out){$fresh=SifiIssueCandidate::query()->findOrFail($candidate->id);Gate::authorize('view',$fresh);abort_unless((int)$fresh->revision===$input['candidate_revision']&&$this->input($source,$fresh)===$input,409);return response()->json($out)->header('Cache-Control','no-store');});
 }
}
