# Read-only SIFI canonical Laravel topology and route smoke check.
# Intended for Windows PowerShell 5.1 on the owner's Windows/WSL workstation.
# Source of truth: D:\Research\SIFI\laravel (reviewed GitHub integration branch).
# Runtime: /home/fajar/projects/sifi-laravel-vnext (Ubuntu-24.04).
[CmdletBinding()]
param(
    [string]$WorkspaceRoot = 'D:\Research\SIFI',
    [string]$Distro = 'Ubuntu-24.04',
    [string]$RuntimeRoot = '/home/fajar/projects/sifi-laravel-vnext',
    [int]$Port = 8821
)

$ErrorActionPreference = 'Stop'
$failed = New-Object System.Collections.Generic.List[string]
$warnings = New-Object System.Collections.Generic.List[string]

function Check([string]$label, [bool]$passed) {
    Write-Output ('{0}={1}' -f $label, $(if ($passed) { 'PASS' } else { 'FAIL' }))
    if (-not $passed) { $script:failed.Add($label) }
}

function WslCheck([string[]]$arguments) {
    $null = & wsl.exe -d $Distro -e @arguments 2>$null
    return ($LASTEXITCODE -eq 0)
}

$workspaceItem = Get-Item -LiteralPath $WorkspaceRoot -ErrorAction SilentlyContinue
Check 'WINDOWS_WORKSPACE_EXISTS' ($null -ne $workspaceItem -and $workspaceItem.PSIsContainer)
if (-not $workspaceItem) { Write-Output 'SIFI_CANONICAL=BLOCKED'; exit 1 }

$laravel = Join-Path $workspaceItem.FullName 'laravel'
$mustExist = @(
    'artisan',
    'composer.json',
    'bootstrap\app.php',
    'routes\web.php',
    'packages\decision-intelligence\composer.json',
    'app\Http\Controllers\Sifi\MonitoringObjectController.php',
    'app\Http\Controllers\Sifi\IssueCaseController.php',
    'app\Http\Controllers\Sifi\EvidenceIntakeController.php',
    'app\Http\Controllers\Sifi\ExecutiveBriefController.php',
    'app\Http\Controllers\Sifi\SourceCatalogController.php'
)
Check 'CANONICAL_LARAVEL_DIRECTORY' (Test-Path -LiteralPath $laravel -PathType Container)
foreach ($relative in $mustExist) {
    $label = 'SOURCE_' + (($relative -replace '[^A-Za-z0-9]', '_').ToUpperInvariant())
    Check $label (Test-Path -LiteralPath (Join-Path $laravel $relative) -PathType Leaf)
}

$gitRoot = @(& git.exe -C $workspaceItem.FullName rev-parse --show-toplevel 2>$null)
if ($LASTEXITCODE -ne 0) { $gitRoot = @() }
Check 'WORKSPACE_GIT_REPOSITORY' ($gitRoot.Count -eq 1)
if ($gitRoot.Count -eq 1) {
    Write-Output ('WORKSPACE_GIT_ROOT={0}' -f $gitRoot[0])
    Write-Output ('WORKSPACE_BRANCH={0}' -f ((& git.exe -C $workspaceItem.FullName branch --show-current) -join ''))
    Write-Output ('WORKSPACE_COMMIT={0}' -f ((& git.exe -C $workspaceItem.FullName rev-parse HEAD) -join ''))
}

Check 'WSL_RUNTIME_ARTISAN' (WslCheck @('test', '-f', "$RuntimeRoot/artisan"))
Check 'WSL_RUNTIME_COMPOSER' (WslCheck @('test', '-f', "$RuntimeRoot/composer.json"))
Check 'WSL_RUNTIME_VENDOR' (WslCheck @('test', '-f', "$RuntimeRoot/vendor/autoload.php"))

$sockets = @(& wsl.exe -d $Distro -e ss -ltnp 2>$null)
$listen = @($sockets | Where-Object { $_ -match ":$Port\s" })
Check 'WSL_LISTENER' ($listen.Count -gt 0)
$listen | ForEach-Object { Write-Output ('LISTENER=' + $_) }

if ($listen.Count -gt 0) {
    $match = [regex]::Match(($listen -join ' '), 'pid=(\d+)')
    if ($match.Success) {
        $runtimePid = $match.Groups[1].Value
        $cwd = @(& wsl.exe -d $Distro -e readlink -f "/proc/$runtimePid/cwd" 2>$null)
        Write-Output ('WSL_LISTENER_CWD={0}' -f ($cwd -join ''))
        Check 'LISTENER_BOUND_TO_EXPECTED_LARAVEL' (($cwd -join '').TrimEnd('/') -eq "$RuntimeRoot/public")
    } else {
        $warnings.Add('LISTENER_PID_UNAVAILABLE')
    }
}

$hasPhp = WslCheck @('test', '-x', '/usr/bin/php8.3')
Check 'WSL_PHP_8_3' $hasPhp
if ($hasPhp -and (WslCheck @('test', '-f', "$RuntimeRoot/artisan"))) {
    # Read-only route resolution; never migrate or load live providers.
    $routeJson = @(& wsl.exe -d $Distro -e php8.3 "$RuntimeRoot/artisan" route:list --json 2>$null)
    if ($LASTEXITCODE -ne 0) {
        Check 'LARAVEL_ROUTE_BOOT' $false
    } else {
        try {
            $allRoutes = @(($routeJson -join "`n" | ConvertFrom-Json))
            Check 'LARAVEL_ROUTE_BOOT' $true
            $routeNames = @($allRoutes | ForEach-Object { $_.name })
            $essential = @(
                'sifi.monitoring-objects.index',
                'sifi.source-catalog.index',
                'sifi.investigations.index',
                'sifi.issue-candidates.index',
                'sifi.issue-case.show',
                'sifi.evidence.index',
                'sifi.executive-brief.show'
            )
            foreach ($name in $essential) {
                Check ('ROUTE_' + ($name -replace '[^A-Za-z0-9]', '_').ToUpperInvariant()) ($routeNames -contains $name)
            }
            Write-Output ('TOTAL_SIFI_ROUTES={0}' -f @($routeNames | Where-Object { $_ -like 'sifi.*' }).Count)
        } catch {
            Check 'LARAVEL_ROUTE_JSON_VALID' $false
        }
    }
}

# This checks topology and route registration, not identical code hashes, database
# migrations, user data, integration journeys, UI or commercial readiness.
Write-Output 'SOURCE_CENTER=DEFERRED'
Write-Output 'PRODUCTION_ADMISSION=DENIED'
Write-Output 'SOURCE_RUNTIME_BYTE_IDENTITY=NOT_CHECKED'
Write-Output ('WARNINGS={0}' -f ($warnings -join ','))
if ($failed.Count) {
    Write-Output ('SIFI_CANONICAL_PREFLIGHT=BLOCKED; FAILED={0}' -f ($failed -join ','))
    exit 1
}
Write-Output 'SIFI_CANONICAL_PREFLIGHT=PASS_TOPOLOGY_ONLY'
