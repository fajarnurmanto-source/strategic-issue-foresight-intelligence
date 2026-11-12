<?php

use App\Http\Controllers\Sifi\Ae16UniversalSearchController;
use App\Http\Controllers\Sifi\ClaimEventController;
use App\Http\Controllers\Sifi\E2e01PilotController;
use App\Http\Controllers\Sifi\E2e02ReviewController;
use App\Http\Controllers\Sifi\E2e03IntelligenceController;
use App\Http\Controllers\Sifi\E2e04RealSignalController;
use App\Http\Controllers\Sifi\E4PeriodDiscoveryController;
use App\Http\Controllers\Sifi\EvidenceDiagnosticsController;
use App\Http\Controllers\Sifi\EvidenceIntakeController;
use App\Http\Controllers\Sifi\ExecutiveBriefController;
use App\Http\Controllers\Sifi\ForesightController;
use App\Http\Controllers\Sifi\IntelligenceAssessmentController;
use App\Http\Controllers\Sifi\IntelligenceDashboardController;
use App\Http\Controllers\Sifi\InvestigationController;
use App\Http\Controllers\Sifi\IssueCandidateController;
use App\Http\Controllers\Sifi\IssueCaseController;
use App\Http\Controllers\Sifi\MonitoringObjectController;
use App\Http\Controllers\Sifi\MultiSourceResearchController;
use App\Http\Controllers\Sifi\ProductRoadmapController;
use App\Http\Controllers\Sifi\ResearchBridgeController;
use App\Http\Controllers\Sifi\ResearchPlanningController;
use App\Http\Controllers\Sifi\ResearchReferencesController;
use App\Http\Controllers\Sifi\SignalInboxController;
use App\Http\Controllers\Sifi\SignalRelationsController;
use App\Http\Controllers\Sifi\SourceCatalogController;
use App\Http\Controllers\Sifi\SourceDiscoveryController;
use App\Http\Controllers\Sifi\TrajectoryController;
use App\Http\Controllers\Sifi\WorkbenchController;
use App\Http\Controllers\Sifi\WorldBankResearchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [IntelligenceDashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';

// SIFI product roadmap: authenticated, read-only projection of docs/canonical.
Route::middleware(['auth', 'verified'])->get('/sifi/product-roadmap',
    [ProductRoadmapController::class, 'index'])
    ->name('sifi.product-roadmap.index');

// SIFI E4: register keywords first, then choose a date-bound, owned, permission-aware discovery.
Route::middleware(['auth', 'verified'])->get('/sifi/discovery-period',
    [E4PeriodDiscoveryController::class, 'index'])
    ->name('sifi.e4.period-discovery.index');
Route::middleware(['auth', 'verified', 'throttle:2,1'])->post('/sifi/discovery-period/collect',
    [E4PeriodDiscoveryController::class, 'collect'])
    ->name('sifi.e4.period-discovery.collect');

/* SIFI_AQ01_SIGNAL_INBOX_ROUTE_BEGIN — isolated offline demonstration only */
Route::middleware(['auth', 'verified'])
    ->get('/sifi/signals', [SignalInboxController::class, 'index'])
    ->name('sifi.signals');
/* SIFI_AQ01_SIGNAL_INBOX_ROUTE_END */

/* SIFI_AQ01_INVESTIGATION_WORKSPACE_BEGIN — Laravel-owned local drafts only */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/investigations', [InvestigationController::class, 'index'])->name('sifi.investigations.index');
    Route::post('/investigations', [InvestigationController::class, 'store'])->middleware('throttle:30,1')->name('sifi.investigations.store');
    Route::get('/investigations/{investigation}', [InvestigationController::class, 'show'])->whereUuid('investigation')->name('sifi.investigations.show');
    Route::patch('/investigations/{investigation}', [InvestigationController::class, 'update'])->whereUuid('investigation')->middleware('throttle:30,1')->name('sifi.investigations.update');
    Route::post('/investigations/{investigation}/signals', [InvestigationController::class, 'attachSignal'])->whereUuid('investigation')->middleware('throttle:30,1')->name('sifi.investigations.attach');
});
/* SIFI_AQ01_INVESTIGATION_WORKSPACE_END */

/* SIFI_AQ01_ISSUE_CANDIDATES_V1 — Laravel-owned drafts, never a Python Issue Object */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates', [IssueCandidateController::class, 'index'])->name('sifi.issue-candidates.index');
    Route::post('/issue-candidates', [IssueCandidateController::class, 'store'])->middleware('throttle:30,1')->name('sifi.issue-candidates.store');
    Route::get('/issue-candidates/{candidate}', [IssueCandidateController::class, 'show'])->name('sifi.issue-candidates.show');
    Route::patch('/issue-candidates/{candidate}', [IssueCandidateController::class, 'update'])->middleware('throttle:30,1')->name('sifi.issue-candidates.update');
});

/* SIFI_AQ01_INCREMENT7_ADVISORY - Laravel-owned analyst request; Python read-derived only */
Route::middleware(['auth', 'verified'])->post('/sifi/issue-candidates/{candidate}/intelligence',
    [IntelligenceAssessmentController::class, 'store'])
    ->whereUuid('candidate')->middleware('throttle:10,1')
    ->name('sifi.issue-candidates.intelligence.store');

/* SIFI_AQ01_INCREMENT8: proposal-only Source & Coverage drafts, no collector authority */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/source-catalog', [SourceCatalogController::class, 'index'])->name('sifi.source-catalog.index');
    Route::post('/source-catalog/connector-preview', [SourceCatalogController::class, 'previewConnector'])->middleware('throttle:10,1')->name('sifi.source-catalog.connector-preview');
    Route::post('/source-catalog/acquisition-preview', [SourceCatalogController::class, 'acquireWorldBank'])->middleware('throttle:5,1')->name('sifi.source-catalog.acquisition-preview');
    Route::post('/source-catalog/pipeline-draft', [SourceCatalogController::class, 'createPipelineDraft'])->middleware('throttle:10,1')->name('sifi.source-catalog.pipeline-draft');
    Route::post('/source-catalog/sources', [SourceCatalogController::class, 'storeSource'])->middleware('throttle:30,1')->name('sifi.source-catalog.sources.store');
    Route::patch('/source-catalog/sources/{source}', [SourceCatalogController::class, 'updateSource'])->whereUuid('source')->middleware('throttle:30,1')->name('sifi.source-catalog.sources.update');
    Route::post('/source-catalog/profiles', [SourceCatalogController::class, 'storeProfile'])->middleware('throttle:30,1')->name('sifi.source-catalog.profiles.store');
    Route::patch('/source-catalog/profiles/{profile}', [SourceCatalogController::class, 'updateProfile'])->whereUuid('profile')->middleware('throttle:30,1')->name('sifi.source-catalog.profiles.update');
});

/* SIFI_AQ01_INCREMENT9: local evidence TRIAGE, independent reviewer can only record gaps. */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/evidence', [EvidenceIntakeController::class, 'index'])->whereUuid('candidate')->name('sifi.evidence.index');
    Route::post('/issue-candidates/{candidate}/evidence', [EvidenceIntakeController::class, 'store'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.evidence.store');
    Route::patch('/evidence/{evidence}', [EvidenceIntakeController::class, 'update'])->whereUuid('evidence')->middleware('throttle:30,1')->name('sifi.evidence.update');
    Route::post('/evidence/{evidence}/request-review', [EvidenceIntakeController::class, 'assign'])->whereUuid('evidence')->middleware('throttle:15,1')->name('sifi.evidence.assign');
    Route::get('/evidence-reviews', [EvidenceIntakeController::class, 'reviewQueue'])->name('sifi.evidence.review-queue');
    Route::post('/evidence/{evidence}/review', [EvidenceIntakeController::class, 'review'])->whereUuid('evidence')->middleware('throttle:15,1')->name('sifi.evidence.review');
});

/* SIFI_AQ01_INCREMENT10: read-derived offline evidence readiness diagnostics; NO admission */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/evidence-diagnostics', [EvidenceDiagnosticsController::class, 'show'])->whereUuid('candidate')->name('sifi.evidence-diagnostics.show');
    Route::post('/issue-candidates/{candidate}/evidence-diagnostics', [EvidenceDiagnosticsController::class, 'store'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.evidence-diagnostics.store');
});

/* SIFI_AQ01_INCREMENT11: human-authored claim/event hypotheses, no factual assertion. */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/claim-events', [ClaimEventController::class, 'show'])->whereUuid('candidate')->name('sifi.claim-events.show');
    Route::post('/issue-candidates/{candidate}/claim-events/claims', [ClaimEventController::class, 'storeClaim'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.claim.store');
    Route::post('/issue-candidates/{candidate}/claim-events/events', [ClaimEventController::class, 'storeEvent'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.event.store');
    Route::post('/issue-candidates/{candidate}/claim-events/links', [ClaimEventController::class, 'storeLink'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.claim-events.link.store');
    Route::post('/issue-candidates/{candidate}/claim-events/assess', [ClaimEventController::class, 'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.claim-events.assess.store');
});

/* SIFI_AQ01_INCREMENT12: Laravel-local Issue Case draft and strictly advisory scope-label check. */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/issue-case', [IssueCaseController::class, 'show'])->whereUuid('candidate')->name('sifi.issue-case.show');
    Route::post('/issue-candidates/{candidate}/issue-case', [IssueCaseController::class, 'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.issue-case.save');
    Route::post('/issue-candidates/{candidate}/issue-case/assess', [IssueCaseController::class, 'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.issue-case.assess');
});

/* SIFI_AQ01_INCREMENT13: analyst-only trajectory/risk/opportunity hypotheses; no factual scores. */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/trajectory', [TrajectoryController::class, 'show'])->whereUuid('candidate')->name('sifi.trajectory.show');
    Route::post('/issue-candidates/{candidate}/trajectory', [TrajectoryController::class, 'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.trajectory.save');
    Route::post('/issue-candidates/{candidate}/trajectory/assess', [TrajectoryController::class, 'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.trajectory.assess');
});

/* SIFI_AQ01_INCREMENT14: unverified, human-authored foresight scenarios (offline only). */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/foresight', [ForesightController::class, 'show'])->whereUuid('candidate')->name('sifi.foresight.show');
    Route::post('/issue-candidates/{candidate}/foresight', [ForesightController::class, 'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.foresight.save');
    Route::post('/issue-candidates/{candidate}/foresight/assess', [ForesightController::class, 'assess'])->whereUuid('candidate')->middleware('throttle:10,1')->name('sifi.foresight.assess');
});

/* SIFI_AQ01_INCREMENT15: local-only executive briefing composer; DRAFT / REVIEW_REQUESTED only. */
Route::middleware(['auth', 'verified'])->prefix('sifi')->group(function () {
    Route::get('/issue-candidates/{candidate}/executive-brief', [ExecutiveBriefController::class, 'show'])->whereUuid('candidate')->name('sifi.executive-brief.show');
    Route::post('/issue-candidates/{candidate}/executive-brief', [ExecutiveBriefController::class, 'save'])->whereUuid('candidate')->middleware('throttle:30,1')->name('sifi.executive-brief.save');
    Route::get('/issue-candidates/{candidate}/executive-brief/download', [ExecutiveBriefController::class, 'download'])->whereUuid('candidate')->middleware('throttle:20,1')->name('sifi.executive-brief.download');
});

/* SIFI_AQ01_INCREMENT16: read-only local workflow hub; user-owned candidates only. */
Route::middleware(['auth', 'verified'])->get('/sifi/workbench',
    [WorkbenchController::class, 'index'])
    ->name('sifi.workbench');

/* SIFI_R17: read-only, bearer-backed offline signal similarity triage. */
Route::middleware(['auth', 'verified'])->get('/sifi/signal-relations',
    [SignalRelationsController::class, 'index'])
    ->name('sifi.signal-relations.index');

/* SIFI_CLAIM_COMPARISON_PREVIEW_V1: read-only, no review/admission. */
require __DIR__.'/claim_comparison_preview.php';

// AE12 configuration only; no collection, grant or evidence authority.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects')->group(function () {
    Route::get('/', [MonitoringObjectController::class, 'index'])->name('sifi.monitoring-objects.index');
    Route::post('/', [MonitoringObjectController::class, 'store'])->middleware('throttle:20,1')->name('sifi.monitoring-objects.store');
    Route::patch('/{object}', [MonitoringObjectController::class, 'update'])->whereUuid('object')->middleware('throttle:20,1')->name('sifi.monitoring-objects.update');
});

// AE13: local metadata discovery; never authorizes network, rights or evidence admission.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/discovery')->whereUuid('object')->group(function () {
    Route::get('/', [SourceDiscoveryController::class, 'index'])->middleware('throttle:60,1')->name('sifi.source-discovery.index');
    Route::post('/', [SourceDiscoveryController::class, 'store'])->middleware('throttle:20,1')->name('sifi.source-discovery.store');
    Route::patch('/{selection}', [SourceDiscoveryController::class, 'update'])->whereUuid('selection')->middleware('throttle:20,1')->name('sifi.source-discovery.update');
    Route::post('/{selection}/register', [SourceDiscoveryController::class, 'register'])->whereUuid('selection')->middleware('throttle:20,1')->name('sifi.source-discovery.register');
});

// SIFI_E2E04_REAL_SOURCE_HANDOFF: one locally reviewed snapshot -> real-source signal + triage drafts only.
Route::middleware(['auth', 'verified'])->post('sifi/monitoring-objects/{object}/e2e01/real-signal-handoff', [E2e04RealSignalController::class, 'create'])->whereUuid('object')->middleware('throttle:3,1')->name('sifi.e2e04.handoff');

// SIFI_E2E03_INTELLIGENCE_V1: human intelligence draft from original-source packet; no evidence admission.
Route::middleware(['auth', 'verified'])->post('sifi/monitoring-objects/{object}/e2e01/intelligence', [E2e03IntelligenceController::class, 'create'])->whereUuid('object')->middleware('throttle:6,1')->name('sifi.e2e03.intelligence');

// SIFI_E2E02_IMAGE_REVIEW_V1 — one real image + session-bound claim candidate; no evidence admission.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/e2e01')->whereUuid('object')->group(function () {
    Route::post('/image', [E2e02ReviewController::class, 'acquireImage'])->middleware('throttle:1,1')->name('sifi.e2e02.image.acquire');
    Route::get('/image/{sessionId}', [E2e02ReviewController::class, 'image'])->whereUuid('sessionId')->middleware('throttle:30,1')->name('sifi.e2e02.image.show');
    Route::post('/review-packet', [E2e02ReviewController::class, 'packet'])->middleware('throttle:10,1')->name('sifi.e2e02.review.packet');
});

// SIFI_E2E01_PILOT_V1: explicit single-page acquisition; draft-only, no automatic admission.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/e2e01')->whereUuid('object')->group(function () {
    Route::get('/', [E2e01PilotController::class, 'index'])->middleware('throttle:30,1')->name('sifi.e2e01.index');
    Route::post('/acquire', [E2e01PilotController::class, 'acquire'])->middleware('throttle:1,1')->name('sifi.e2e01.acquire');
    Route::post('/brief', [E2e01PilotController::class, 'brief'])->middleware('throttle:5,1')->name('sifi.e2e01.brief');
});

// AE16_F04B_LIVE_GATEWAY_V1: only existing owner-bound grants may dispatch live HTTP.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/universal-search')->whereUuid('object')->group(function () {
    Route::get('/', [Ae16UniversalSearchController::class, 'index'])->middleware('throttle:30,1')->name('sifi.ae16-universal-search.index');
    Route::post('/', [Ae16UniversalSearchController::class, 'execute'])->middleware('throttle:2,1')->name('sifi.ae16-universal-search.execute');
});

// AE14-PLAN-01: deterministic local-catalog planning only; no web request, AI, retrieval or admission.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/research')->whereUuid('object')->group(function () {
    Route::get('/', [ResearchPlanningController::class, 'index'])->middleware('throttle:60,1')->name('sifi.research-planning.index');
    Route::post('/', [ResearchPlanningController::class, 'store'])->middleware('throttle:20,1')->name('sifi.research-planning.store');
});

// AE14-NET-01 fixture retrieval decisions; no live transport.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/research-bridge')->whereUuid('object')->group(function () {
    Route::get('/', [ResearchBridgeController::class, 'index'])->middleware('throttle:60,1')->name('sifi.research-bridge.index');
    Route::post('/', [ResearchBridgeController::class, 'decide'])->middleware('throttle:20,1')->name('sifi.research-bridge.decide');
    Route::post('/{retrieval}/snapshot', [ResearchBridgeController::class, 'snapshot'])->whereUuid('retrieval')->middleware('throttle:20,1')->name('sifi.research-bridge.snapshot');
    Route::post('/{retrieval}/draft', [ResearchBridgeController::class, 'draft'])->whereUuid('retrieval')->middleware('throttle:20,1')->name('sifi.research-bridge.draft');
});
// AE14-NET02 fresh context-bound research acquisition.
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/research-worldbank')->whereUuid('object')->group(function () {
    Route::get('/', [WorldBankResearchController::class, 'index'])->middleware('throttle:60,1')->name('sifi.worldbank-research.index');
    Route::post('/', [WorldBankResearchController::class, 'decide'])->middleware('throttle:20,1')->name('sifi.worldbank-research.decide');
    foreach (['acquire', 'recover', 'draft'] as $action) {
        Route::post('/{retrieval}/'.$action, [WorldBankResearchController::class, $action])->whereUuid('retrieval')->middleware('throttle:5,1')->name('sifi.worldbank-research.'.$action);
    }
});

// AE14 multi-source fixture research, using existing owned research sessions.
Route::middleware(['auth', 'verified'])->get('/sifi/research', [MultiSourceResearchController::class, 'choose'])->name('sifi.multi-research.choose');
Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/research-multi')->whereUuid('object')->group(function () {
    Route::get('/', [MultiSourceResearchController::class, 'index'])->name('sifi.multi-research.index');
    Route::post('/', [MultiSourceResearchController::class, 'search'])->middleware('throttle:20,1')->name('sifi.multi-research.search');
    Route::post('/{session}/feed-draft', [MultiSourceResearchController::class, 'feedDraft'])->whereUuid('session')->middleware('throttle:5,1,sifi-feed-draft')->name('sifi.multi-research.feed-draft');
    Route::post('/{session}/save', [MultiSourceResearchController::class, 'save'])->whereUuid('session')->middleware('throttle:20,1')->name('sifi.multi-research.save');
});

Route::middleware(['auth', 'verified', 'throttle:30,1'])->get('/sifi/monitoring-objects/{object}/research-multi/{session}/references', [ResearchReferencesController::class, 'download'])->whereUuid('object')->whereUuid('session')->name('sifi.multi-research.references');
/* SIFI_IH03H_LOCAL_SHADOW_DASHBOARD */
require __DIR__.'/sifi_ih03h.php';

/* SIFI_IH03J_OWNER_LOCAL_REAL_SOURCE */
require __DIR__.'/sifi_ih03j.php';
