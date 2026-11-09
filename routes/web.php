<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Sifi\IntelligenceDashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';

// SIFI product roadmap: authenticated, read-only projection of docs/canonical.
Route::middleware(['auth', 'verified'])->get('/sifi/product-roadmap',
    [\App\Http\Controllers\Sifi\ProductRoadmapController::class, 'index'])
    ->name('sifi.product-roadmap.index');

/* SIFI_AQ01_SIGNAL_INBOX_ROUTE_BEGIN — isolated offline demonstration only */
\Illuminate\Support\Facades\Route::middleware(['auth', 'verified'])
    ->get('/sifi/signals', [\App\Http\Controllers\Sifi\SignalInboxController::class, 'index'])
    ->name('sifi.signals');
/* SIFI_AQ01_SIGNAL_INBOX_ROUTE_END */

/* SIFI_AQ01_INVESTIGATION_WORKSPACE_BEGIN — Laravel-owned local drafts only */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/investigations', [\App\Http\Controllers\Sifi\InvestigationController::class, 'index'])->name('sifi.investigations.index');
    Route::post('/investigations', [\App\Http\Controllers\Sifi\InvestigationController::class, 'store'])->middleware('throttle:30,1')->name('sifi.investigations.store');
    Route::get('/investigations/{investigation}', [\App\Http\Controllers\Sifi\InvestigationController::class, 'show'])->whereUuid('investigation')->name('sifi.investigations.show');
    Route::patch('/investigations/{investigation}', [\App\Http\Controllers\Sifi\InvestigationController::class, 'update'])->whereUuid('investigation')->middleware('throttle:30,1')->name('sifi.investigations.update');
    Route::post('/investigations/{investigation}/signals', [\App\Http\Controllers\Sifi\InvestigationController::class, 'attachSignal'])->whereUuid('investigation')->middleware('throttle:30,1')->name('sifi.investigations.attach');
});
/* SIFI_AQ01_INVESTIGATION_WORKSPACE_END */

/* SIFI_AQ01_ISSUE_CANDIDATES_V1 — Laravel-owned drafts, never a Python Issue Object */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates', [\App\Http\Controllers\Sifi\IssueCandidateController::class, 'index'])->name('sifi.issue-candidates.index');
    Route::post('/issue-candidates', [\App\Http\Controllers\Sifi\IssueCandidateController::class, 'store'])->middleware('throttle:30,1')->name('sifi.issue-candidates.store');
    Route::get('/issue-candidates/{candidate}', [\App\Http\Controllers\Sifi\IssueCandidateController::class, 'show'])->name('sifi.issue-candidates.show');
    Route::patch('/issue-candidates/{candidate}', [\App\Http\Controllers\Sifi\IssueCandidateController::class, 'update'])->middleware('throttle:30,1')->name('sifi.issue-candidates.update');
});

/* SIFI_AQ01_INCREMENT7_ADVISORY - Laravel-owned analyst request; Python read-derived only */
Route::middleware(['auth', 'verified'])->post('/sifi/issue-candidates/{candidate}/intelligence',
    [\App\Http\Controllers\Sifi\IntelligenceAssessmentController::class, 'store'])
    ->whereUuid('candidate')->middleware('throttle:10,1')
    ->name('sifi.issue-candidates.intelligence.store');

/* SIFI_AQ01_INCREMENT8: proposal-only Source & Coverage drafts, no collector authority */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function () {
    Route::get('/source-catalog',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'index'])->name('sifi.source-catalog.index');
    Route::post('/source-catalog/connector-preview',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'previewConnector'])->middleware('throttle:10,1')->name('sifi.source-catalog.connector-preview');
    Route::post('/source-catalog/acquisition-preview', [\App\Http\Controllers\Sifi\SourceCatalogController::class,'acquireWorldBank'])->middleware('throttle:5,1')->name('sifi.source-catalog.acquisition-preview');
    Route::post('/source-catalog/pipeline-draft',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'createPipelineDraft'])->middleware('throttle:10,1')->name('sifi.source-catalog.pipeline-draft');
    Route::post('/source-catalog/sources',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'storeSource'])->middleware('throttle:30,1')->name('sifi.source-catalog.sources.store');
    Route::patch('/source-catalog/sources/{source}',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'updateSource'])->whereUuid('source')->middleware('throttle:30,1')->name('sifi.source-catalog.sources.update');
    Route::post('/source-catalog/profiles',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'storeProfile'])->middleware('throttle:30,1')->name('sifi.source-catalog.profiles.store');
    Route::patch('/source-catalog/profiles/{profile}',[\App\Http\Controllers\Sifi\SourceCatalogController::class,'updateProfile'])->whereUuid('profile')->middleware('throttle:30,1')->name('sifi.source-catalog.profiles.update');
});


/* SIFI_AQ01_INCREMENT9: local evidence TRIAGE, independent reviewer can only record gaps. */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/evidence', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'index'])->whereUuid('candidate')->name('sifi.evidence.index');
    Route::post('/issue-candidates/{candidate}/evidence', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'store'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.evidence.store');
    Route::patch('/evidence/{evidence}', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'update'])->whereUuid('evidence')->middleware('throttle:30,1')->name('sifi.evidence.update');
    Route::post('/evidence/{evidence}/request-review', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'assign'])->whereUuid('evidence')->middleware('throttle:15,1')->name('sifi.evidence.assign');
    Route::get('/evidence-reviews', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'reviewQueue'])->name('sifi.evidence.review-queue');
    Route::post('/evidence/{evidence}/review', [\App\Http\Controllers\Sifi\EvidenceIntakeController::class,'review'])->whereUuid('evidence')->middleware('throttle:15,1')->name('sifi.evidence.review');
});

/* SIFI_AQ01_INCREMENT10: read-derived offline evidence readiness diagnostics; NO admission */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/evidence-diagnostics', [\App\Http\Controllers\Sifi\EvidenceDiagnosticsController::class,'show'])->whereUuid('candidate')->name('sifi.evidence-diagnostics.show');
    Route::post('/issue-candidates/{candidate}/evidence-diagnostics', [\App\Http\Controllers\Sifi\EvidenceDiagnosticsController::class,'store'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.evidence-diagnostics.store');
});

/* SIFI_AQ01_INCREMENT11: human-authored claim/event hypotheses, no factual assertion. */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function() {
    Route::get('/issue-candidates/{candidate}/claim-events',[\App\Http\Controllers\Sifi\ClaimEventController::class,'show'])->whereUuid('candidate')->name('sifi.claim-events.show');
    Route::post('/issue-candidates/{candidate}/claim-events/claims',[\App\Http\Controllers\Sifi\ClaimEventController::class,'storeClaim'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.claim.store');
    Route::post('/issue-candidates/{candidate}/claim-events/events',[\App\Http\Controllers\Sifi\ClaimEventController::class,'storeEvent'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.event.store');
    Route::post('/issue-candidates/{candidate}/claim-events/links',[\App\Http\Controllers\Sifi\ClaimEventController::class,'storeLink'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.link.store');
    Route::post('/issue-candidates/{candidate}/claim-events/assess',[\App\Http\Controllers\Sifi\ClaimEventController::class,'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.claim-events.assess.store');
});

/* SIFI_AQ01_INCREMENT12: Laravel-local Issue Case draft and strictly advisory scope-label check. */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function() {
 Route::get('/issue-candidates/{candidate}/issue-case',[\App\Http\Controllers\Sifi\IssueCaseController::class,'show'])->whereUuid('candidate')->name('sifi.issue-case.show');
 Route::post('/issue-candidates/{candidate}/issue-case',[\App\Http\Controllers\Sifi\IssueCaseController::class,'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.issue-case.save');
 Route::post('/issue-candidates/{candidate}/issue-case/assess',[\App\Http\Controllers\Sifi\IssueCaseController::class,'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.issue-case.assess');
});

/* SIFI_AQ01_INCREMENT13: analyst-only trajectory/risk/opportunity hypotheses; no factual scores. */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function() {
 Route::get('/issue-candidates/{candidate}/trajectory', [\App\Http\Controllers\Sifi\TrajectoryController::class,'show'])->whereUuid('candidate')->name('sifi.trajectory.show');
 Route::post('/issue-candidates/{candidate}/trajectory', [\App\Http\Controllers\Sifi\TrajectoryController::class,'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.trajectory.save');
 Route::post('/issue-candidates/{candidate}/trajectory/assess', [\App\Http\Controllers\Sifi\TrajectoryController::class,'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.trajectory.assess');
});


/* SIFI_AQ01_INCREMENT14: unverified, human-authored foresight scenarios (offline only). */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function() {
 Route::get('/issue-candidates/{candidate}/foresight', [\App\Http\Controllers\Sifi\ForesightController::class,'show'])->whereUuid('candidate')->name('sifi.foresight.show');
 Route::post('/issue-candidates/{candidate}/foresight', [\App\Http\Controllers\Sifi\ForesightController::class,'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.foresight.save');
 Route::post('/issue-candidates/{candidate}/foresight/assess', [\App\Http\Controllers\Sifi\ForesightController::class,'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.foresight.assess');
});

/* SIFI_AQ01_INCREMENT15: local-only executive briefing composer; DRAFT / REVIEW_REQUESTED only. */
Route::middleware(['auth','verified'])->prefix('sifi')->group(function() {
 Route::get('/issue-candidates/{candidate}/executive-brief', [\App\Http\Controllers\Sifi\ExecutiveBriefController::class,'show'])->whereUuid('candidate')->name('sifi.executive-brief.show');
 Route::post('/issue-candidates/{candidate}/executive-brief', [\App\Http\Controllers\Sifi\ExecutiveBriefController::class,'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.executive-brief.save');
 Route::get('/issue-candidates/{candidate}/executive-brief/download', [\App\Http\Controllers\Sifi\ExecutiveBriefController::class,'download'])->whereUuid('candidate')->middleware('throttle:20,1')->name('sifi.executive-brief.download');
});

/* SIFI_AQ01_INCREMENT16: read-only local workflow hub; user-owned candidates only. */
Route::middleware(['auth','verified'])->get('/sifi/workbench',
    [\App\Http\Controllers\Sifi\WorkbenchController::class, 'index'])
    ->name('sifi.workbench');

/* SIFI_R17: read-only, bearer-backed offline signal similarity triage. */
Route::middleware(['auth','verified'])->get('/sifi/signal-relations',
    [\App\Http\Controllers\Sifi\SignalRelationsController::class,'index'])
    ->name('sifi.signal-relations.index');

/* SIFI_CLAIM_COMPARISON_PREVIEW_V1: read-only, no review/admission. */
require __DIR__.'/claim_comparison_preview.php';

// AE12 configuration only; no collection, grant or evidence authority.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\MonitoringObjectController::class,'index'])->name('sifi.monitoring-objects.index');
 Route::post('/',[\App\Http\Controllers\Sifi\MonitoringObjectController::class,'store'])->middleware('throttle:20,1')->name('sifi.monitoring-objects.store');
 Route::patch('/{object}',[\App\Http\Controllers\Sifi\MonitoringObjectController::class,'update'])->whereUuid('object')->middleware('throttle:20,1')->name('sifi.monitoring-objects.update');
});

// AE13: local metadata discovery; never authorizes network, rights or evidence admission.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/discovery')->whereUuid('object')->group(function () {
    Route::get('/', [\App\Http\Controllers\Sifi\SourceDiscoveryController::class,'index'])->middleware('throttle:60,1')->name('sifi.source-discovery.index');
    Route::post('/', [\App\Http\Controllers\Sifi\SourceDiscoveryController::class,'store'])->middleware('throttle:20,1')->name('sifi.source-discovery.store');
    Route::patch('/{selection}', [\App\Http\Controllers\Sifi\SourceDiscoveryController::class,'update'])->whereUuid('selection')->middleware('throttle:20,1')->name('sifi.source-discovery.update');
    Route::post('/{selection}/register', [\App\Http\Controllers\Sifi\SourceDiscoveryController::class,'register'])->whereUuid('selection')->middleware('throttle:20,1')->name('sifi.source-discovery.register');
});

// SIFI_E2E04_REAL_SOURCE_HANDOFF: one locally reviewed snapshot -> real-source signal + triage drafts only.
Route::middleware(['auth','verified'])->post('sifi/monitoring-objects/{object}/e2e01/real-signal-handoff',[\App\Http\Controllers\Sifi\E2e04RealSignalController::class,'create'])->whereUuid('object')->middleware('throttle:3,1')->name('sifi.e2e04.handoff');

// SIFI_E2E03_INTELLIGENCE_V1: human intelligence draft from original-source packet; no evidence admission.
Route::middleware(['auth','verified'])->post('sifi/monitoring-objects/{object}/e2e01/intelligence',[\App\Http\Controllers\Sifi\E2e03IntelligenceController::class,'create'])->whereUuid('object')->middleware('throttle:6,1')->name('sifi.e2e03.intelligence');

// SIFI_E2E02_IMAGE_REVIEW_V1 — one real image + session-bound claim candidate; no evidence admission.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/e2e01')->whereUuid('object')->group(function(){
 Route::post('/image',[\App\Http\Controllers\Sifi\E2e02ReviewController::class,'acquireImage'])->middleware('throttle:1,1')->name('sifi.e2e02.image.acquire');
 Route::get('/image/{sessionId}',[\App\Http\Controllers\Sifi\E2e02ReviewController::class,'image'])->whereUuid('sessionId')->middleware('throttle:30,1')->name('sifi.e2e02.image.show');
 Route::post('/review-packet',[\App\Http\Controllers\Sifi\E2e02ReviewController::class,'packet'])->middleware('throttle:10,1')->name('sifi.e2e02.review.packet');
});

// SIFI_E2E01_PILOT_V1: explicit single-page acquisition; draft-only, no automatic admission.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/e2e01')->whereUuid('object')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\E2e01PilotController::class,'index'])->middleware('throttle:30,1')->name('sifi.e2e01.index');
 Route::post('/acquire',[\App\Http\Controllers\Sifi\E2e01PilotController::class,'acquire'])->middleware('throttle:1,1')->name('sifi.e2e01.acquire');
 Route::post('/brief',[\App\Http\Controllers\Sifi\E2e01PilotController::class,'brief'])->middleware('throttle:5,1')->name('sifi.e2e01.brief');
});

// AE16_F04B_LIVE_GATEWAY_V1: only existing owner-bound grants may dispatch live HTTP.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/universal-search')->whereUuid('object')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\Ae16UniversalSearchController::class,'index'])->middleware('throttle:30,1')->name('sifi.ae16-universal-search.index');
 Route::post('/',[\App\Http\Controllers\Sifi\Ae16UniversalSearchController::class,'execute'])->middleware('throttle:2,1')->name('sifi.ae16-universal-search.execute');
});

// AE14-PLAN-01: deterministic local-catalog planning only; no web request, AI, retrieval or admission.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/research')->whereUuid('object')->group(function () {
 Route::get('/', [\App\Http\Controllers\Sifi\ResearchPlanningController::class,'index'])->middleware('throttle:60,1')->name('sifi.research-planning.index');
 Route::post('/', [\App\Http\Controllers\Sifi\ResearchPlanningController::class,'store'])->middleware('throttle:20,1')->name('sifi.research-planning.store');
});

// AE14-NET-01 fixture retrieval decisions; no live transport.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/research-bridge')->whereUuid('object')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\ResearchBridgeController::class,'index'])->middleware('throttle:60,1')->name('sifi.research-bridge.index');
 Route::post('/',[\App\Http\Controllers\Sifi\ResearchBridgeController::class,'decide'])->middleware('throttle:20,1')->name('sifi.research-bridge.decide');
 Route::post('/{retrieval}/snapshot',[\App\Http\Controllers\Sifi\ResearchBridgeController::class,'snapshot'])->whereUuid('retrieval')->middleware('throttle:20,1')->name('sifi.research-bridge.snapshot');
 Route::post('/{retrieval}/draft',[\App\Http\Controllers\Sifi\ResearchBridgeController::class,'draft'])->whereUuid('retrieval')->middleware('throttle:20,1')->name('sifi.research-bridge.draft');
});
// AE14-NET02 fresh context-bound research acquisition.
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/research-worldbank')->whereUuid('object')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\WorldBankResearchController::class,'index'])->middleware('throttle:60,1')->name('sifi.worldbank-research.index');
 Route::post('/',[\App\Http\Controllers\Sifi\WorldBankResearchController::class,'decide'])->middleware('throttle:20,1')->name('sifi.worldbank-research.decide');
 foreach(['acquire','recover','draft'] as $action)Route::post('/{retrieval}/'.$action,[\App\Http\Controllers\Sifi\WorldBankResearchController::class,$action])->whereUuid('retrieval')->middleware('throttle:5,1')->name('sifi.worldbank-research.'.$action);
});

// AE14 multi-source fixture research, using existing owned research sessions.
Route::middleware(['auth','verified'])->get('/sifi/research',[\App\Http\Controllers\Sifi\MultiSourceResearchController::class,'choose'])->name('sifi.multi-research.choose');
Route::middleware(['auth','verified'])->prefix('sifi/monitoring-objects/{object}/research-multi')->whereUuid('object')->group(function(){
 Route::get('/',[\App\Http\Controllers\Sifi\MultiSourceResearchController::class,'index'])->name('sifi.multi-research.index');
 Route::post('/',[\App\Http\Controllers\Sifi\MultiSourceResearchController::class,'search'])->middleware('throttle:20,1')->name('sifi.multi-research.search');
 Route::post('/{session}/feed-draft',[\App\Http\Controllers\Sifi\MultiSourceResearchController::class,'feedDraft'])->whereUuid('session')->middleware('throttle:5,1,sifi-feed-draft')->name('sifi.multi-research.feed-draft');
 Route::post('/{session}/save',[\App\Http\Controllers\Sifi\MultiSourceResearchController::class,'save'])->whereUuid('session')->middleware('throttle:20,1')->name('sifi.multi-research.save');
});

Route::middleware(['auth','verified','throttle:30,1'])->get('/sifi/monitoring-objects/{object}/research-multi/{session}/references',[\App\Http\Controllers\Sifi\ResearchReferencesController::class,'download'])->whereUuid('object')->whereUuid('session')->name('sifi.multi-research.references');
/* SIFI_IH03H_LOCAL_SHADOW_DASHBOARD */
require __DIR__.'/sifi_ih03h.php';

/* SIFI_IH03J_OWNER_LOCAL_REAL_SOURCE */
require __DIR__.'/sifi_ih03j.php';
