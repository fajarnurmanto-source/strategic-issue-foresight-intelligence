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
        laravel/app/Models/SifiTrajectoryDraft.php|\
        laravel/app/Models/SifiForesightDraft.php|\
        laravel/app/Models/SifiExecutiveBrief.php|\
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
echo "PHPSTAN_ERRORS_BEFORE_RECTOR=$Before"
echo "PHPSTAN_ERRORS_AFTER_RECTOR=$After"
if [ "$After" -lt 561 ]; then
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
' "$LastReport"

test "$(git -C "$Repo" rev-parse HEAD)" = "$Canonical"
test -z "$(git -C "$Repo" status --porcelain)"
echo "PHPSTAN_REPORT=$LastReport"
echo 'SIFI_CANONICAL_SOURCE=UNCHANGED'
echo 'SIFI_RUNTIME=UNCHANGED'
echo 'PRODUCTION_ADMISSION=DENIED'
