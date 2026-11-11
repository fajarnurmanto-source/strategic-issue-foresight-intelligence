#!/usr/bin/env bash
set -euo pipefail

# Run only against a pinned GitHub commit, inside a disposable WSL staging tree.
# The checked-out canonical tree and the long-running Laravel runtime are read-only.

Repo=/mnt/d/Research/SIFI/repository
Runtime=/home/fajar/projects/sifi-laravel-vnext
Canonical=02d95191f108d1d67737a85470732284ebe26938
Fix=${SIFI_EXPECTED_FIX:-}

if [[ ! "$Fix" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'SIFI_FIX_SHA=INVALID'
    exit 1
fi

echo '=== 1. PINNED SOURCE PREFLIGHT ==='
test "$(git -C "$Repo" rev-parse HEAD)" = "$Canonical"
test -z "$(git -C "$Repo" status --porcelain)"
test "$(git -C "$Repo" rev-parse "$Fix^{commit}")" = "$Fix"
test -x "$Runtime/vendor/bin/pint"
test -x "$Runtime/vendor/bin/phpstan"
test -f "$Runtime/vendor/autoload.php"

while IFS= read -r changed; do
    case "$changed" in
        laravel/app/Sifi/CoreV2/LegacySessionReadGateway.php|\
        laravel/app/Sifi/CoreV2/PipelineReadModel.php|\
        laravel/app/Sifi/CoreV2/ShadowPipeline.php|\
        laravel/app/SifiStructured/Context.php|\
        laravel/app/Services/Sifi/ResearchRetrievalWorkspace.php|\
        laravel/app/Services/Sifi/ResearchSessionWorkspace.php|\
        laravel/app/Http/Controllers/Sifi/InvestigationController.php|\
        laravel/app/Http/Controllers/Sifi/ForesightController.php|\
        laravel/app/Http/Controllers/Sifi/IssueCandidateController.php|\
        laravel/app/Http/Controllers/Sifi/ExecutiveBriefController.php|\
        laravel/app/Services/Sifi/ExecutiveBriefSource.php|\
        laravel/app/Services/Sifi/WorldBankOneShotAcquisition.php|\
        laravel/app/Services/Sifi/DiscoveryCatalog.php|\
        laravel/app/Services/Sifi/BoundedFeedParser.php|\
        laravel/app/Services/Sifi/FeedDiscovery.php|\
        laravel/app/Http/Controllers/Sifi/E2e01PilotController.php|\
        laravel/app/Models/SifiMonitoringObject.php|\
        laravel/app/Services/Sifi/SourceDiscoveryWorkspace.php|\
        laravel/app/Services/Sifi/WorldBankResearchWorkspace.php|\
        laravel/app/Services/Sifi/YogyakartaPadSourceAdapter.php|\
        laravel/app/Http/Controllers/Sifi/E4PeriodDiscoveryController.php|\
        laravel/app/Services/Sifi/WorldBankAcquisitionWorkspace.php|\
        laravel/app/Services/Sifi/WorldBankGdpSourceAdapter.php|\
        laravel/app/Services/Sifi/SourceObservationPipeline.php|\
        laravel/app/Services/Sifi/MultiSourceResearchWorkspace.php|\
        laravel/app/Http/Controllers/Sifi/E2e02ReviewController.php|\
        laravel/app/Services/Sifi/FixtureResearchProvider.php|\
        laravel/app/Models/SifiTrajectoryDraft.php|\
        laravel/app/Models/SifiForesightDraft.php|\
        laravel/app/Models/SifiExecutiveBrief.php|\
        laravel/app/Services/Sifi/MultilingualDiscovery.php|\
        laravel/app/Services/Sifi/OneShotSearchTransport.php|\
        laravel/app/Services/Sifi/Ai/ProviderEndpointPolicy.php|\
        laravel/app/Services/Sifi/DeterministicResearchPlanner.php|\
        laravel/app/Services/Sifi/RealCollectorWorkspace.php|\
        laravel/app/Http/Controllers/Settings/AiModelController.php|\
        laravel/app/Services/Sifi/Ai/ConfiguredHttpAiAdapter.php|\
        laravel/app/Services/Sifi/ClaimComparisonPreview.php|\
        laravel/app/Services/Sifi/CorpusSearchProvider.php|\
        laravel/app/Services/Sifi/FeedResearchBridge.php|\
        laravel/app/Services/Sifi/ResearchWorldBankAcquisition.php|\
        laravel/app/Services/Sifi/TavilySearchProvider.php|\
        laravel/app/Http/Controllers/Sifi/EvidenceIntakeController.php|\
        laravel/app/Http/Controllers/Sifi/TrajectoryController.php|\
        laravel/app/Services/Sifi/MultiSourceFixtureSupport.php|\
        laravel/app/Http/Controllers/Sifi/SourceCatalogController.php|\
        laravel/app/Services/Sifi/Ae16UnifiedFilter.php|\
        laravel/app/Services/Sifi/BoundedSearchTransport.php|\
        laravel/app/Services/Sifi/Integration/BlueskySearchTransport.php|\
        laravel/app/Services/Sifi/Integration/ConnectorProfileDrafts.php|\
        laravel/app/Services/Sifi/Integration/ReviewedCrossrefResearch.php|\
        laravel/app/Services/Sifi/MultilingualQueryPlan.php|\
        laravel/app/Services/Sifi/RealSearchWorkspace.php|\
        laravel/app/Http/Controllers/Sifi/ClaimComparisonPreviewController.php|\
        laravel/app/Http/Controllers/Sifi/IssueCaseController.php|\
        laravel/app/Http/Controllers/Sifi/MonitoringObjectController.php|\
        laravel/app/Services/Sifi/CollectorObservations.php|\
        laravel/app/Services/Sifi/FeedSourcePack.php|\
        laravel/app/Services/Sifi/Integration/AntaraRssMetadataParser.php|\
        laravel/app/Services/Sifi/Integration/AntaraRssTransport.php|\
        laravel/app/Services/Sifi/Integration/GoogleTrendsRssTransport.php|\
        laravel/app/Services/Sifi/Integration/ReviewedGdeltNews.php|\
        laravel/app/Services/Sifi/MonitoringObjectSources.php|\
        laravel/app/Services/Sifi/OneShotSearchAuthority.php|\
        laravel/app/Console/Commands/SifiCoreV2Doctor.php|\
        laravel/app/Console/Commands/SifiIh03bCrossref.php|\
        laravel/app/Http/Controllers/Sifi/AiIssueExplanationController.php|\
        laravel/app/Http/Controllers/Sifi/ClaimEventController.php|\
        laravel/app/Http/Controllers/Sifi/WorkbenchController.php|\
        laravel/app/Services/Sifi/Ai/ProviderHttpTransport.php|\
        laravel/app/Services/Sifi/Ai/UserAiSettings.php|\
        laravel/app/Services/Sifi/ApprovedCorpusSearch.php|\
        laravel/app/Services/Sifi/Integration/CrossSourceReceiptFusion.php|\
        laravel/app/Services/Sifi/Integration/ReviewedAntaraRss.php|\
        laravel/app/Services/Sifi/SearchProviderRegistry.php|\
        laravel/app/Contracts/SifiResearchProvider.php|\
        laravel/app/Http/Controllers/Sifi/EvidenceDiagnosticsController.php|\
        laravel/app/Http/Controllers/Sifi/MultiSourceResearchController.php|\
        laravel/app/Http/Controllers/Sifi/SignalInboxController.php|\
        laravel/app/Http/Controllers/Sifi/SourceDiscoveryController.php|\
        laravel/app/Http/Requests/Sifi/ClaimComparisonPreviewRequest.php|\
        laravel/app/Http/Requests/Sifi/UpdateIssueCandidateRequest.php|\
        laravel/app/Services/Sifi/Ai/FreeAiCatalogue.php|\
        laravel/app/Services/Sifi/Ai/SecureAiRuntime.php|\
        laravel/app/Services/Sifi/Aq01ClaimEventBridge.php|\
        laravel/app/Services/Sifi/Aq01EvidenceDiagnosticsBridge.php|\
        laravel/app/Services/Sifi/Aq01ForesightBridge.php|\
        laravel/app/Services/Sifi/Aq01IntelligenceBridge.php|\
        laravel/app/Services/Sifi/Aq01IssueScopeBridge.php|\
        laravel/app/Services/Sifi/Aq01TrajectoryBridge.php|\
        laravel/app/Services/Sifi/ClaimComparisonInputDiagnostics.php|\
        laravel/app/Services/Sifi/E4PeriodDiscovery.php|\
        laravel/app/Services/Sifi/Integration/ConnectorObservationProjector.php|\
        laravel/app/Services/Sifi/Integration/ReviewedGoogleTrends.php|\
        laravel/app/Services/Sifi/Integration/SocialWatchHistory.php|\
        laravel/app/Services/Sifi/ResearchSignalInsights.php|\
        laravel/app/Console/Commands/SifiIh03dGdelt.php|\
        laravel/app/Console/Commands/SifiIh03eAntara.php|\
        laravel/app/Data/Sifi/AiInferenceRequest.php|\
        laravel/app/Data/Sifi/AiInferenceResult.php|\
        laravel/app/Filament/Superadmin/Pages/ConnectorProfiles.php|\
        laravel/app/Filament/Superadmin/Pages/IntegrationHub.php|\
        laravel/app/Http/Controllers/Sifi/E2e01PilotController.php|\
        laravel/app/Http/Controllers/Sifi/Ih03hSnapshotController.php|\
        laravel/app/Http/Controllers/Sifi/ResearchBridgeController.php|\
        laravel/app/Http/Controllers/Sifi/ResearchPlanningController.php|\
        laravel/app/Http/Controllers/Sifi/WorldBankResearchController.php|\
        laravel/app/Http/Requests/Sifi/AssignEvidenceReviewerRequest.php|\
        laravel/app/Http/Requests/Sifi/CoverageDraftRequest.php|\
        laravel/app/Http/Requests/Sifi/MonitoringObjectRequest.php|\
        laravel/app/Http/Requests/Sifi/RunEvidenceDiagnosticsRequest.php|\
        laravel/app/Http/Requests/Sifi/RunIntelligenceRequest.php|\
        laravel/app/Http/Requests/Sifi/SaveExecutiveBriefRequest.php|\
        laravel/app/Http/Requests/Sifi/SaveForesightRequest.php|\
        laravel/app/Http/Requests/Sifi/SaveIssueCaseRequest.php|\
        laravel/app/Http/Requests/Sifi/SaveTrajectoryRequest.php|\
        laravel/app/Http/Requests/Sifi/SourceDraftRequest.php|\
        laravel/app/Http/Requests/Sifi/StoreClaimEventRequest.php|\
        laravel/app/Http/Requests/Sifi/StoreEvidenceDraftRequest.php|\
        laravel/app/Http/Requests/Sifi/StoreIssueCandidateRequest.php|\
        laravel/app/Http/Requests/Sifi/SubmitEvidenceReviewRequest.php|\
        laravel/app/Http/Requests/Sifi/UpdateEvidenceDraftRequest.php|\
        laravel/app/Models/SifiEvidenceDraft.php|\
        laravel/app/Models/SifiInvestigation.php|\
        laravel/app/Models/SifiIssueCandidate.php|\
        laravel/app/Services/Sifi/Aq01SignalProjection.php|\
        laravel/app/Services/Sifi/ClaimComparisonPreviewInput.php|\
        laravel/app/Services/Sifi/Integration/BlueskyPublicSearchParser.php|\
        laravel/app/Services/Sifi/Integration/ConnectorReadiness.php|\
        laravel/app/Services/Sifi/Integration/GoogleSourceCoverage.php|\
        laravel/app/Services/Sifi/Integration/GoogleTrendsRssParser.php|\
        laravel/app/Services/Sifi/Integration/ProviderInventory.php|\
        laravel/app/Services/Sifi/Integration/ResearchReceiptSignalIntelligence.php|\
        laravel/app/Services/Sifi/Integration/ReviewedBlueskySearch.php|\
        laravel/app/Services/Sifi/Integration/SocialSourceCoverage.php|\
        laravel/app/Services/Sifi/SourceDraftRegistration.php|\
        scripts/rector-sifi-docblock.php|\
        scripts/sifi_phpstan_remediation_stage.sh)
            ;;
        *)
            echo "UNEXPECTED_FIX_PATH=$changed"
            exit 1
            ;;
    esac
done < <(git -C "$Repo" diff --name-only "$Canonical" "$Fix")

echo "SIFI_FIX_SHA=$Fix"
echo 'SIFI_FIX_SCOPE=PASS'

echo '=== 2. FRESH STAGING SNAPSHOT ==='
Stage=$(mktemp -d /tmp/sifi-phpstan-remediation-XXXXXXXX)
git -C "$Repo" archive "$Fix" laravel scripts/rector-sifi-docblock.php |
    tar -xf - -C "$Stage"
Candidate="$Stage/laravel"
test -f "$Candidate/phpstan.neon"
test ! -e "$Candidate/vendor"
ln -s "$Runtime/vendor" "$Candidate/vendor"
mkdir -p \
    "$Candidate/bootstrap/cache" \
    "$Candidate/storage/logs" \
    "$Candidate/storage/framework/cache/data" \
    "$Candidate/storage/framework/sessions" \
    "$Candidate/storage/framework/views"
echo "SIFI_STAGE=$Stage"

echo '=== 3. PINT AND PHPSTAN BEFORE RECTOR ==='
cd "$Candidate"
"$Runtime/vendor/bin/pint" --parallel > "$Stage/pint-before.log"
"$Runtime/vendor/bin/pint" --parallel --test > "$Stage/pint-before-check.log"

export APP_ENV=testing APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE=:memory:
export CACHE_STORE=array QUEUE_CONNECTION=sync

measure_phpstan() {
    local report=$1
    local errlog=$2
    local code
    set +e
    "$Runtime/vendor/bin/phpstan" analyse \
        --no-progress --error-format=json > "$report" 2> "$errlog"
    code=$?
    set -e
    if [ "$code" -gt 1 ]; then
        sed -n '1,60p' "$errlog"
        return 1
    fi
    php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data) || !isset($data["totals"])) exit(9);
        echo (int) ($data["totals"]["file_errors"] ?? 0)
            + (int) ($data["totals"]["errors"] ?? 0);
    ' "$report"
}

Before=$(measure_phpstan "$Stage/phpstan-before.json" "$Stage/phpstan-before.stderr")
echo "PHPSTAN_ERRORS_BEFORE_RECTOR=$Before"

echo '=== 4. ISOLATED RECTOR TYPE-INFERENCE ==='
RectorBin=
if [ "${SIFI_USE_RECTOR:-0}" != '1' ]; then
    echo 'RECTOR_TOOL=SKIPPED_PREVIOUS_ZERO_CHANGES'
elif [ -x "$Runtime/vendor/bin/rector" ]; then
    RectorBin="$Runtime/vendor/bin/rector"
    echo 'RECTOR_TOOL=EXISTING_READ_ONLY'
elif command -v composer >/dev/null 2>&1; then
    ToolDir="$Stage/rector-tools"
    mkdir -p "$ToolDir"
    if timeout 180s composer --working-dir="$ToolDir" require \
        --dev --no-interaction --no-progress --no-scripts --no-plugins \
        'rector/rector:^2.2' > "$Stage/rector-install.log" 2>&1; then
        RectorBin="$ToolDir/vendor/bin/rector"
        echo 'RECTOR_TOOL=ISOLATED_INSTALL'
    else
        echo 'RECTOR_TOOL=INSTALL_UNAVAILABLE'
    fi
else
    echo 'RECTOR_TOOL=COMPOSER_UNAVAILABLE'
fi

After=$Before
LastReport="$Stage/phpstan-before.json"
if [ -n "$RectorBin" ] && [ -x "$RectorBin" ]; then
    cp -a "$Candidate/app" "$Stage/app-before-rector"
    if "$RectorBin" process \
        --config "$Stage/scripts/rector-sifi-docblock.php" \
        > "$Stage/rector-process.log" 2>&1; then
        rsync --recursive --checksum --dry-run \
            --out-format='%n' \
            "$Candidate/app/" "$Stage/app-before-rector/" \
            > "$Stage/rector-changed-files.txt"
        Changed=$(sed '/^$/d' "$Stage/rector-changed-files.txt" | wc -l)
        echo "RECTOR_CHANGED_FILES=$Changed"
        if [ "$Changed" -gt 0 ]; then
            "$Runtime/vendor/bin/pint" --parallel > "$Stage/pint-after.log"
            "$Runtime/vendor/bin/pint" --parallel --test > "$Stage/pint-after-check.log"
            After=$(measure_phpstan "$Stage/phpstan-after.json" "$Stage/phpstan-after.stderr")
            LastReport="$Stage/phpstan-after.json"
        fi
    else
        echo 'RECTOR_EXECUTION=REVIEW_REQUIRED'
        tail -n 20 "$Stage/rector-process.log"
    fi
else
    echo 'RECTOR_EXECUTION=SKIPPED'
fi

echo '=== 5. OUTCOME AND ERROR CLASSES ==='
echo 'PHPSTAN_ORIGINAL_ERRORS=714'
echo 'PHPSTAN_VERIFIED_BATCH1_ERRORS=703'
echo 'PHPSTAN_VERIFIED_BATCH2_ERRORS=632'
echo 'PHPSTAN_VERIFIED_BATCH3_ERRORS=561'
echo 'PHPSTAN_VERIFIED_BATCH4_ERRORS=437'
echo 'PHPSTAN_VERIFIED_BATCH5_ERRORS=325'
echo 'PHPSTAN_VERIFIED_BATCH6_ERRORS=236'
echo 'PHPSTAN_VERIFIED_BATCH7_ERRORS=180'
echo 'PHPSTAN_VERIFIED_BATCH8_ERRORS=137'
echo 'PHPSTAN_VERIFIED_BATCH9_ERRORS=104'
echo 'PHPSTAN_VERIFIED_BATCH10_ERRORS=68'
echo 'PHPSTAN_VERIFIED_BATCH11_ERRORS=50'
echo 'PHPSTAN_VERIFIED_BATCH12_ERRORS=37'
echo "PHPSTAN_ERRORS_BEFORE_RECTOR=$Before"
echo "PHPSTAN_ERRORS_AFTER_RECTOR=$After"
if [ "$After" -lt 37 ]; then
    echo 'SIFI_PHPSTAN_REMEDIATION=IMPROVED'
else
    echo 'SIFI_PHPSTAN_REMEDIATION=REVIEW_REQUIRED'
fi

php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    $count = [];
    $hotspots = [];
    foreach (($data["files"] ?? []) as $path => $file) {
        $hotspots[$path] = count($file["messages"] ?? []);
        foreach (($file["messages"] ?? []) as $item) {
            $id = $item["identifier"] ?? "unknown";
            $count[$id] = ($count[$id] ?? 0) + 1;
        }
    }
    arsort($count);
    foreach (array_slice($count, 0, 12, true) as $id => $n) {
        echo "PHPSTAN_CLASS:$id=$n\n";
    }
    arsort($hotspots);
    foreach (array_slice($hotspots, 0, 15, true) as $file => $n) {
        echo "PHPSTAN_HOTSPOT:$file=$n\n";
    }
    foreach (array_keys($hotspots) as $path) {
        foreach (($data["files"][$path]["messages"] ?? []) as $problem) {
            $msg = preg_replace("/\\s+/", " ", $problem["message"] ?? "");
            $line = (int) ($problem["line"] ?? 0);
            $type = $problem["identifier"] ?? "unknown";
            echo "PHPSTAN_DETAIL:" . basename($path) . ":$line:$type:" . substr($msg, 0, 240) . "\n";
        }
    }
' "$LastReport"

test "$(git -C "$Repo" rev-parse HEAD)" = "$Canonical"
test -z "$(git -C "$Repo" status --porcelain)"
echo "PHPSTAN_REPORT=$LastReport"
echo 'SIFI_CANONICAL_SOURCE=UNCHANGED'
echo 'SIFI_RUNTIME=UNCHANGED'
echo 'PRODUCTION_ADMISSION=DENIED'
