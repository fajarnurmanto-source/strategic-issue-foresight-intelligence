# SIFI Source Center: fail-closed, source-only Windows PowerShell installer.
# No API credentials, automatic provider spend, background collector, or production promotions.
# Usage: powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\sifi-source-center\install-source-center.ps1 -TargetLaravel "D:\Research\SIFI\<your-laravel-folder>" [-Apply] [-ProbeGoogle]
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$TargetLaravel,
    [string]$SourceRepo = '',
    [switch]$Apply,
    [switch]$ProbeGoogle
)

$ErrorActionPreference = 'Stop'
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($SourceRepo)) {
    $SourceRepo = (Resolve-Path -LiteralPath (Join-Path $scriptDir '..\..')).ProviderPath
}
$sourceRoot = (Resolve-Path -LiteralPath $SourceRepo).ProviderPath.TrimEnd('\', '/')
$targetRoot = (Resolve-Path -LiteralPath $TargetLaravel).ProviderPath.TrimEnd('\', '/')
$manifestPath = Join-Path $scriptDir 'install-manifest.json'
$manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
if ($manifest.schema -ne 'sifi-source-center-install.v1' -or $manifest.files.Count -lt 19) {
    throw 'SIFI_INSTALL_MANIFEST_INVALID'
}
if ($sourceRoot -eq $targetRoot -or $targetRoot.StartsWith($sourceRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'SIFI_INSTALL_REFUSES_SOURCE_TARGET_OVERLAP'
}
foreach ($required in @('artisan', 'composer.json', 'package.json', 'routes\web.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $targetRoot $required) -PathType Leaf)) {
        throw ('SIFI_INSTALL_TARGET_NOT_LARAVEL: ' + $required)
    }
}
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'SIFI_INSTALL_GIT_REQUIRED'
}
$branchName = (& git -C $sourceRoot branch --show-current 2>$null | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $branchName -ne $manifest.branch) {
    throw ('SIFI_INSTALL_CHECKOUT_REQUIRED: ' + $manifest.branch)
}

function Get-GitBlobSha([string]$file) {
    # All 19 source-center manifest entries are UTF-8 text. Normalize Windows CRLF
    # only; never read or transmit .env or another secret file.
    $value = [IO.File]::ReadAllText($file, [Text.Encoding]::UTF8)
    $raw = [Text.Encoding]::UTF8.GetBytes($value.Replace("`r`n", "`n"))
    $prefix = [Text.Encoding]::ASCII.GetBytes(('blob ' + $raw.Length + [char]0))
    $joined = New-Object byte[] ($prefix.Length + $raw.Length)
    [Array]::Copy($prefix, 0, $joined, 0, $prefix.Length)
    [Array]::Copy($raw, 0, $joined, $prefix.Length, $raw.Length)
    $sha = [Security.Cryptography.SHA1]::Create()
    try {
        return ([BitConverter]::ToString($sha.ComputeHash($joined))).Replace('-', '').ToLowerInvariant()
    } finally { $sha.Dispose() }
}
function Invoke-Checked([string]$name, [string]$exe, [string[]]$arguments) {
    Write-Output ('SIFI_STEP=' + $name)
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) {
        throw ('SIFI_STEP_FAILED: ' + $name + ', exit=' + $LASTEXITCODE)
    }
}

$plan = @()
foreach ($row in $manifest.files) {
    $relative = [string]$row.path
    if (-not ($relative -match '^(app/|database/migrations/|docs/|resources/js/pages/sifi/|routes/web\.php$|tests/Feature/Sifi/)') -or
        $relative.Contains('..') -or $relative.StartsWith('/')) {
        throw ('SIFI_INSTALL_UNEXPECTED_PATH: ' + $relative)
    }
    $native = $relative.Replace('/', [IO.Path]::DirectorySeparatorChar)
    $src = Join-Path $sourceRoot $native
    $dst = Join-Path $targetRoot $native
    if (-not (Test-Path -LiteralPath $src -PathType Leaf)) {
        throw ('SIFI_INSTALL_SOURCE_MISSING: ' + $relative)
    }
    $sourceHash = Get-GitBlobSha $src
    if ($sourceHash -ne [string]$row.candidate_blob) {
        throw ('SIFI_INSTALL_SOURCE_CHANGED: ' + $relative)
    }
    $exists = Test-Path -LiteralPath $dst -PathType Leaf
    $targetHash = if ($exists) { Get-GitBlobSha $dst } else { '' }
    $base = [string]$row.baseline_blob
    if ($exists -and $targetHash -ne $sourceHash -and ($base -eq '' -or $targetHash -ne $base)) {
        throw ('SIFI_INSTALL_TARGET_DRIFT_BLOCKED: ' + $relative + ' (will not overwrite user changes)')
    }
    if (-not $exists -and $base -ne '') {
        throw ('SIFI_INSTALL_BASE_FILE_MISSING: ' + $relative)
    }
    $plan += [pscustomobject]@{
        relative = $relative; source = $src; destination = $dst
        needsCopy = ($targetHash -ne $sourceHash); wasPresent = $exists
    }
}
$changes = @($plan | Where-Object { $_.needsCopy })
Write-Output ('SIFI_SOURCE_PREFLIGHT=PASS; FILES=' + $plan.Count + '; TO_COPY=' + $changes.Count)
if (-not $Apply) {
    Write-Output 'SIFI_INSTALL=DRY_RUN; run again with -Apply to install, migrate and test.'
    return
}

foreach ($required in @('php', 'npm')) {
    if (-not (Get-Command $required -ErrorAction SilentlyContinue)) {
        throw ('SIFI_INSTALL_DEPENDENCY_MISSING: ' + $required)
    }
}
if (-not (Test-Path -LiteralPath (Join-Path $targetRoot 'vendor\autoload.php') -PathType Leaf)) {
    throw 'SIFI_INSTALL_COMPOSER_VENDOR_MISSING'
}
if (-not (Test-Path -LiteralPath (Join-Path $targetRoot 'node_modules\typescript\bin\tsc') -PathType Leaf)) {
    throw 'SIFI_INSTALL_NODE_DEPENDENCIES_MISSING'
}
# This source-center package is not a production release.
$environmentText = (& php (Join-Path $targetRoot 'artisan') env 2>&1 | Out-String)
if ($LASTEXITCODE -ne 0 -or $environmentText -match 'production') {
    throw 'SIFI_INSTALL_REQUIRES_NONPRODUCTION_LARAVEL'
}

$stamp = [DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss_fff')
$parent = Split-Path -Parent $targetRoot
$backupRoot = Join-Path $parent ('SIFI_SOURCE_CENTER_BACKUPS\' + $stamp)
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
$assetDir = Join-Path $targetRoot 'public\build'
$assetBackup = Join-Path $backupRoot 'assets-before'
$assetExisted = Test-Path -LiteralPath $assetDir -PathType Container
if ($assetExisted) {
    Copy-Item -LiteralPath $assetDir -Destination $assetBackup -Recurse -Force
}
$hasAttemptedMigration = $false
$originalDir = (Get-Location).Path
try {
    foreach ($item in $changes) {
        $backup = Join-Path $backupRoot ($item.relative.Replace('/', [IO.Path]::DirectorySeparatorChar))
        if ($item.wasPresent) {
            New-Item -ItemType Directory -Force -Path (Split-Path -Parent $backup) | Out-Null
            [IO.File]::Copy($item.destination, $backup, $true)
        }
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $item.destination) | Out-Null
        [IO.File]::Copy($item.source, $item.destination, $true)
    }
    Set-Location -LiteralPath $targetRoot
    foreach ($item in $changes) {
        if ($item.relative.EndsWith('.php')) {
            Invoke-Checked ('PHP_LINT_' + $item.relative) 'php' @('-l', $item.destination)
        }
    }
    Invoke-Checked 'FOCUSED_LARAVEL_TESTS' 'php' @(
        'artisan', 'test',
        '--filter=GoogleNewsRssPreviewTest|GoogleTrendsRssPreviewTest|SourceCenterDoctorCommandTest|SourceModuleCatalogTest|E4PeriodDiscoveryTest|SifiSourceCatalogWorkflowTest'
    )
    Invoke-Checked 'TYPESCRIPT_TYPES' 'npm' @('run', 'types:check')
    Invoke-Checked 'FRONTEND_BUILD' 'npm' @('run', 'build')

    # This is the only database change: the new source-selection + audit tables.
    # Laravel's configured connection applies; no production database switch is made.
    $hasAttemptedMigration = $true
    Invoke-Checked 'SOURCE_SELECTION_MIGRATION' 'php' @(
        'artisan', 'migrate',
        '--path=database/migrations/2026_11_09_000021_create_sifi_user_source_selections.php', '--force'
    )
    Invoke-Checked 'SIFI_SOURCE_DOCTOR' 'php' @('artisan', 'sifi:sources:doctor')
    if ($ProbeGoogle) {
        Invoke-Checked 'SIFI_GOOGLE_PUBLIC_RSS_LIVE' 'php' @('artisan', 'sifi:sources:doctor', '--live')
    }
    $receipt = [ordered]@{
        schema = 'sifi-source-center-install-receipt.v1'
        status = 'PASS'
        applied_utc = [DateTime]::UtcNow.ToString('o')
        source_branch = $manifest.branch
        files_copied = $changes.Count
        backup_directory = $backupRoot
        rss_live_probe_requested = [bool]$ProbeGoogle
        production_collector_changed = $false
    }
    $receipt | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $backupRoot 'receipt.json') -Encoding UTF8
    Write-Output ('SIFI_SOURCE_INSTALL=PASS; BACKUP=' + $backupRoot)
} catch {
    $reason = $_.Exception.Message
    Write-Output ('SIFI_SOURCE_INSTALL=FAIL; REASON=' + $reason)
    if (-not $hasAttemptedMigration) {
        # Restore only these explicit 19 source paths, never the root or .env.
        foreach ($item in $changes) {
            if ($item.wasPresent) {
                $backup = Join-Path $backupRoot ($item.relative.Replace('/', [IO.Path]::DirectorySeparatorChar))
                if (Test-Path -LiteralPath $backup -PathType Leaf) {
                    [IO.File]::Copy($backup, $item.destination, $true)
                }
            } elseif (Test-Path -LiteralPath $item.destination -PathType Leaf) {
                [IO.File]::Delete($item.destination)
            }
        }
        # Frontend build may have touched this specific directory. Backup is kept.
        if (Test-Path -LiteralPath $assetDir -PathType Container) {
            Remove-Item -LiteralPath $assetDir -Recurse -Force
        }
        if ($assetExisted -and (Test-Path -LiteralPath $assetBackup -PathType Container)) {
            Copy-Item -LiteralPath $assetBackup -Destination $assetDir -Recurse -Force
        }
        Write-Output 'SIFI_SOURCE_ROLLBACK=SOURCE_AND_FRONTEND_RESTORED'
    } else {
        Write-Output ('SIFI_SOURCE_ROLLBACK=MANUAL_REVIEW_REQUIRED; MIGRATION_ATTEMPTED; BACKUP=' + $backupRoot)
    }
    throw
} finally {
    Set-Location -LiteralPath $originalDir
}
