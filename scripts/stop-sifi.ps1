$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$statePath = Join-Path $root "runtime\config\sifi-server.json"

if (-not (Test-Path $statePath)) {
    Write-Output '{"status":"NOT_RUNNING"}'
    exit 0
}

$state = Get-Content -Raw $statePath | ConvertFrom-Json
$serverPid = [int]$state.pid
$process = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
if (-not $process) {
    Remove-Item -LiteralPath $statePath
    Write-Output '{"status":"STALE_STATE_REMOVED"}'
    exit 0
}

$details = Get-CimInstance Win32_Process -Filter "ProcessId = $serverPid"
$expectedPython = (Resolve-Path $state.python).Path
$actualPython = $process.Path
$commandLine = [string]$details.CommandLine
if (
    $actualPython -ne $expectedPython -or
    -not $commandLine.Contains("sifi.web.cli") -or
    -not $commandLine.Contains($root)
) {
    throw "PID state tidak cocok dengan proses server SIFI; penghentian ditolak."
}

Stop-Process -Id $serverPid
$process.WaitForExit(5000) | Out-Null
Remove-Item -LiteralPath $statePath
[ordered]@{status="STOPPED"; pid=$serverPid; local_only=$true} | ConvertTo-Json
