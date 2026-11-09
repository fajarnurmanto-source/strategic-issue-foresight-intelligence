<#
SIFI reversible cleanup, Windows PowerShell 5.1 compatible.
Default: Plan only. Apply requires the exact reviewed plan path; Restore is supported.
Only old, regenerable QA cache folders beneath two validation subtrees qualify.
No source, database, WSL runtime, evidence, backup, or provider credential deletion.
#>
[CmdletBinding()]
param(
    [ValidateSet('Plan', 'Apply', 'Restore')]
    [string]$Mode = 'Plan',
    [string]$Root = 'D:\Research\SIFI',
    [int]$MinimumAgeDays = 7,
    [string]$PlanFile,
    [string]$ReceiptFile
)

$ErrorActionPreference = 'Stop'
$Root = [IO.Path]::GetFullPath($Root).TrimEnd('\')
$PlanFolder = Join-Path $Root '00_CONTROL\CLEANUP_CANDIDATES'
$QuarantineFolder = Join-Path $Root '99_QUARANTINE'
$CandidateNames = @('node_modules', '.venv', '__pycache__', '.pytest_cache', 'playwright-report', 'test-results')
$AllowedSubtrees = @('05_VALIDATION\PROGRESS', '05_VALIDATION\STAGING')
$Schema = 'sifi-reversible-qa-cache-cleanup.v1'

function Stop-Cleanup([string]$Code) {
    throw "SIFI_CLEANUP=HELD:$Code"
}

function Ensure-Under([string]$ItemPath, [string]$ParentPath) {
    $full = [IO.Path]::GetFullPath($ItemPath).TrimEnd('\')
    $parent = [IO.Path]::GetFullPath($ParentPath).TrimEnd('\')
    if (-not $full.StartsWith($parent + '\', [StringComparison]::OrdinalIgnoreCase)) {
        Stop-Cleanup 'PATH_OUTSIDE_APPROVED_SUBTREE'
    }
}

function Has-ReparsePoint([string]$ItemPath) {
    $current = Get-Item -LiteralPath $ItemPath -Force -ErrorAction Stop
    while ($null -ne $current) {
        if (($current.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { return $true }
        if ($current.FullName.TrimEnd('\') -ieq $Root) { break }
        $current = $current.Parent
    }
    return $false
}

function Get-Stats([string]$Directory) {
    $bytes = [long]0
    $files = [long]0
    Get-ChildItem -LiteralPath $Directory -Recurse -Force -ErrorAction Stop | ForEach-Object {
        $item = $_
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            Stop-Cleanup 'SYMLINK_OR_JUNCTION_IN_CACHE'
        }
        if (-not $item.PSIsContainer) {
            $bytes += [long]$item.Length
            $files += 1
        }
    }
    return [PSCustomObject]@{ bytes = $bytes; files = $files }
}

function Assert-Target([string]$Absolute, [string]$Relative) {
    if ($Relative -match '(^|/)(\.\.|\.git|runtime|storage|database|vendor|\.env)(/|$)') {
        Stop-Cleanup 'PROTECTED_RELATIVE_PATH'
    }
    if ($Relative.Contains('\') -or $Relative.StartsWith('/')) { Stop-Cleanup 'BAD_RELATIVE_PATH' }
    if ($CandidateNames -notcontains ([IO.Path]::GetFileName($Absolute))) {
        Stop-Cleanup 'NOT_REGENERABLE_CACHE_NAME'
    }
    $isAllowed = $false
    foreach ($sub in $AllowedSubtrees) {
        $allowedRoot = Join-Path $Root $sub
        if ([IO.Directory]::Exists($allowedRoot)) {
            $absoluteFull = [IO.Path]::GetFullPath($Absolute).TrimEnd('\')
            if ($absoluteFull.StartsWith([IO.Path]::GetFullPath($allowedRoot).TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) {
                $isAllowed = $true
            }
        }
    }
    if (-not $isAllowed) { Stop-Cleanup 'UNAPPROVED_QA_SCOPE' }
    if (-not (Test-Path -LiteralPath $Absolute -PathType Container)) { Stop-Cleanup 'CACHE_MISSING' }
    if (Has-ReparsePoint $Absolute) { Stop-Cleanup 'REPARSE_PATH' }

    # Git status is not a deletion list; tracked files in this subtree are protected.
    $tracked = @(& git.exe -C $Root ls-files -- $Relative 2>$null)
    if ($LASTEXITCODE -ne 0) { Stop-Cleanup 'GIT_QUERY_FAILED' }
    if ($tracked.Count -gt 0) { Stop-Cleanup 'CACHE_CONTAINS_TRACKED_SOURCE' }
}

function Assert-RestoreOrigin([string]$Absolute, [string]$Relative) {
    if ($Relative -match '(^|/)(\.\.|\.git|runtime|storage|database|vendor|\.env)(/|$)' -or
        $Relative.Contains('\') -or $Relative.StartsWith('/')) {
        Stop-Cleanup 'INVALID_RESTORE_RELATIVE'
    }
    $calculated = [IO.Path]::GetFullPath((Join-Path $Root $Relative)).TrimEnd('\')
    if ($calculated -ine [IO.Path]::GetFullPath($Absolute).TrimEnd('\')) {
        Stop-Cleanup 'RESTORE_PATH_TAMPERED'
    }
    if ($CandidateNames -notcontains ([IO.Path]::GetFileName($calculated))) {
        Stop-Cleanup 'RESTORE_UNAPPROVED_CACHE_NAME'
    }
    $allowed = $false
    foreach ($sub in $AllowedSubtrees) {
        $parent = Join-Path $Root $sub
        if ($calculated.StartsWith([IO.Path]::GetFullPath($parent).TrimEnd('\') + '\',
            [StringComparison]::OrdinalIgnoreCase)) { $allowed = $true }
    }
    if (-not $allowed) { Stop-Cleanup 'RESTORE_OUTSIDE_VALIDATION_SCOPE' }
}

if (-not (Test-Path -LiteralPath (Join-Path $Root '.git'))) { Stop-Cleanup 'ROOT_NOT_GIT_PROJECT' }
if (-not (Test-Path -LiteralPath (Join-Path $Root '00_CONTROL'))) { Stop-Cleanup 'GOVERNANCE_MISSING' }
if ($MinimumAgeDays -lt 1) { Stop-Cleanup 'MINIMUM_AGE_MUST_BE_POSITIVE' }
if ((Test-Path -LiteralPath $PlanFolder) -and (Has-ReparsePoint $PlanFolder)) {
    Stop-Cleanup 'PLAN_DIRECTORY_REPARSE_POINT'
}
if ((Test-Path -LiteralPath $QuarantineFolder) -and (Has-ReparsePoint $QuarantineFolder)) {
    Stop-Cleanup 'QUARANTINE_DIRECTORY_REPARSE_POINT'
}

if ($Mode -eq 'Plan') {
    $cutoff = (Get-Date).ToUniversalTime().AddDays(-$MinimumAgeDays)
    $raw = @()
    foreach ($sub in $AllowedSubtrees) {
        $scope = Join-Path $Root $sub
        if (Test-Path -LiteralPath $scope -PathType Container) {
            $raw += @(Get-ChildItem -LiteralPath $scope -Directory -Recurse -Force -ErrorAction Stop |
                Where-Object { ($CandidateNames -contains $_.Name) -and ($_.LastWriteTimeUtc -lt $cutoff) })
        }
    }

    $selected = New-Object System.Collections.ArrayList
    foreach ($candidate in ($raw | Sort-Object { $_.FullName.Length })) {
        $nested = $false
        foreach ($parent in $selected) {
            if ($candidate.FullName.StartsWith($parent.FullName + '\', [StringComparison]::OrdinalIgnoreCase)) {
                $nested = $true
                break
            }
        }
        if (-not $nested) { [void]$selected.Add($candidate) }
    }

    $entries = @()
    foreach ($candidate in $selected) {
        $relative = $candidate.FullName.Substring($Root.Length).TrimStart('\').Replace('\', '/')
        Assert-Target $candidate.FullName $relative
        $stats = Get-Stats $candidate.FullName
        $entries += [ordered]@{
            path = $candidate.FullName
            relative = $relative
            last_write_utc = $candidate.LastWriteTimeUtc.ToString('o')
            bytes = $stats.bytes
            files = $stats.files
            reason = 'aged_regenerable_validation_cache'
        }
    }

    New-Item -ItemType Directory -Force -Path $PlanFolder | Out-Null
    $stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd_HHmmss')
    $out = Join-Path $PlanFolder ("SIFI_CLEANUP_PLAN_$stamp.json")
    $manifest = [ordered]@{
        schema = $Schema
        project_root = $Root
        created_utc = (Get-Date).ToUniversalTime().ToString('o')
        minimum_age_days = $MinimumAgeDays
        entries = @($entries)
    }
    $manifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $out -Encoding UTF8
    Write-Output "SIFI_CLEANUP_PLAN=$out"
    Write-Output "CANDIDATE_DIRECTORIES=$($entries.Count)"
    Write-Output "CANDIDATE_BYTES=$(($entries | Measure-Object -Property bytes -Sum).Sum)"
    $entries | Select-Object relative, bytes, files, last_write_utc | Format-Table -AutoSize
    Write-Output 'SIFI_CLEANUP=PLAN_ONLY; CHANGES=0'
    exit 0
}

if ($Mode -eq 'Apply') {
    if (-not $PlanFile) { Stop-Cleanup 'PLAN_FILE_REQUIRED' }
    Ensure-Under $PlanFile $PlanFolder
    $plan = Get-Content -LiteralPath $PlanFile -Raw | ConvertFrom-Json
    if ($plan.schema -ne $Schema -or $plan.project_root -ine $Root) { Stop-Cleanup 'PLAN_SCHEMA_OR_ROOT_MISMATCH' }
    $batch = [IO.Path]::GetFileNameWithoutExtension($PlanFile)
    $destinationRoot = Join-Path $QuarantineFolder $batch
    if (Test-Path -LiteralPath $destinationRoot) { Stop-Cleanup 'QUARANTINE_BATCH_ALREADY_EXISTS' }

    foreach ($entry in @($plan.entries)) {
        $calculated = [IO.Path]::GetFullPath((Join-Path $Root $entry.relative)).TrimEnd('\')
        if ($calculated -ine [IO.Path]::GetFullPath($entry.path).TrimEnd('\')) { Stop-Cleanup 'PLAN_ENTRY_TAMPERED' }
        Assert-Target $calculated $entry.relative
        $item = Get-Item -LiteralPath $calculated -Force
        $stats = Get-Stats $calculated
        if ($item.LastWriteTimeUtc.ToString('o') -ne $entry.last_write_utc -or
            $stats.bytes -ne [long]$entry.bytes -or $stats.files -ne [long]$entry.files) {
            Stop-Cleanup 'CACHE_CHANGED_SINCE_PLAN'
        }
    }

    $receipt = Join-Path $PlanFolder ("SIFI_CLEANUP_RECEIPT_$batch.json")
    $pendingReceipt = "$receipt.pending"
    if (Test-Path -LiteralPath $receipt) { Stop-Cleanup 'QUARANTINE_RECEIPT_ALREADY_EXISTS' }
    if (Test-Path -LiteralPath $pendingReceipt) { Stop-Cleanup 'QUARANTINE_PENDING_RECEIPT_ALREADY_EXISTS' }
    $moved = New-Object System.Collections.ArrayList
    try {
        foreach ($entry in @($plan.entries)) {
            $from = $entry.path
            $to = Join-Path $destinationRoot ($entry.relative.Replace('/', '\'))
            New-Item -ItemType Directory -Force -Path (Split-Path -Parent $to) | Out-Null
            if (Test-Path -LiteralPath $to) { Stop-Cleanup 'QUARANTINE_DESTINATION_EXISTS' }
            Move-Item -LiteralPath $from -Destination $to -ErrorAction Stop
            [void]$moved.Add([PSCustomObject]@{ from = $from; to = $to; relative = $entry.relative })
        }
        [ordered]@{
            schema = $Schema
            source_plan = $PlanFile
            applied_utc = (Get-Date).ToUniversalTime().ToString('o')
            destination_root = $destinationRoot
            moved = @($moved)
            status = 'QUARANTINED_NOT_DELETED'
        } | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $pendingReceipt -Encoding UTF8
        Move-Item -LiteralPath $pendingReceipt -Destination $receipt -ErrorAction Stop
    } catch {
        $originalError = $_
        foreach ($entry in (@($moved) | Sort-Object -Property { $_.from.Length } -Descending)) {
            Move-Item -LiteralPath $entry.to -Destination $entry.from -ErrorAction Stop
        }
        throw $originalError
    }

    Write-Output "SIFI_CLEANUP=QUARANTINED; FOLDERS=$($moved.Count); DELETED=0"
    Write-Output "RESTORE_RECEIPT=$receipt"
    exit 0
}

if ($Mode -eq 'Restore') {
    if (-not $ReceiptFile) { Stop-Cleanup 'RECEIPT_FILE_REQUIRED' }
    Ensure-Under $ReceiptFile $PlanFolder
    $receipt = Get-Content -LiteralPath $ReceiptFile -Raw | ConvertFrom-Json
    if ($receipt.schema -ne $Schema -or $receipt.status -ne 'QUARANTINED_NOT_DELETED') {
        Stop-Cleanup 'INVALID_QUARANTINE_RECEIPT'
    }
    Ensure-Under $receipt.destination_root $QuarantineFolder
    foreach ($entry in @($receipt.moved)) {
        Ensure-Under $entry.to $receipt.destination_root
        Assert-RestoreOrigin $entry.from $entry.relative
        $expectedDestination = [IO.Path]::GetFullPath((Join-Path $receipt.destination_root ($entry.relative.Replace('/', '\')))).TrimEnd('\')
        if ($expectedDestination -ine [IO.Path]::GetFullPath($entry.to).TrimEnd('\')) {
            Stop-Cleanup 'RESTORE_DESTINATION_TAMPERED'
        }
        if (Test-Path -LiteralPath $entry.from) { Stop-Cleanup 'ORIGINAL_PATH_OCCUPIED' }
        if (-not (Test-Path -LiteralPath $entry.to -PathType Container)) { Stop-Cleanup 'QUARANTINED_CACHE_MISSING' }
        if (Has-ReparsePoint $entry.to) { Stop-Cleanup 'QUARANTINED_CACHE_REPARSE_POINT' }
    }
    $restored = New-Object System.Collections.ArrayList
    try {
        foreach ($entry in @($receipt.moved)) {
            New-Item -ItemType Directory -Force -Path (Split-Path -Parent $entry.from) | Out-Null
            Move-Item -LiteralPath $entry.to -Destination $entry.from -ErrorAction Stop
            [void]$restored.Add($entry)
        }
    } catch {
        $originalError = $_
        foreach ($entry in (@($restored) | Sort-Object -Property { $_.from.Length } -Descending)) {
            Move-Item -LiteralPath $entry.from -Destination $entry.to -ErrorAction Stop
        }
        throw $originalError
    }
    Write-Output "SIFI_CLEANUP=RESTORED; FOLDERS=$(@($receipt.moved).Count)"
    exit 0
}
