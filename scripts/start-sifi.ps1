param(
    [switch]$NoDemo,
    [switch]$NoBrowser,
    [int]$Port = 8765
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$python = Join-Path $root ".venv\Scripts\python.exe"
$runtimeConfig = Join-Path $root "runtime\config"
$runtimeLogs = Join-Path $root "runtime\logs"
$statePath = Join-Path $runtimeConfig "sifi-server.json"
$stdoutPath = Join-Path $runtimeLogs "sifi-server.log"
$stderrPath = Join-Path $runtimeLogs "sifi-server-error.log"

if ($Port -lt 1024 -or $Port -gt 65535) {
    throw "Port harus berada pada rentang 1024-65535."
}
if (-not (Test-Path $python)) {
    & (Join-Path $PSScriptRoot "setup-local.ps1")
}
if (-not $NoDemo) {
    & (Join-Path $PSScriptRoot "demo-local.ps1")
}
New-Item -ItemType Directory -Force -Path $runtimeConfig, $runtimeLogs | Out-Null

if (Test-Path $statePath) {
    $existingState = Get-Content -Raw $statePath | ConvertFrom-Json
    $existingProcess = Get-Process -Id $existingState.pid -ErrorAction SilentlyContinue
    if ($existingProcess) {
        throw "SIFI sudah berjalan dengan PID $($existingState.pid)."
    }
    Remove-Item -LiteralPath $statePath
}

$env:PYTHONPATH = Join-Path $root "src"
$arguments = @("-m", "sifi.web.cli", "--root", $root, "--port", "$Port")
$process = Start-Process -FilePath $python -ArgumentList $arguments -PassThru `
    -WindowStyle Hidden -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath

$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    Start-Sleep -Milliseconds 250
    if ($process.HasExited) { break }
    try {
        $health = Invoke-RestMethod "http://127.0.0.1:$Port/health" -TimeoutSec 1
        if ($health.local_only -eq $true -and $health.status -eq "OK") {
            $ready = $true
            break
        }
    }
    catch { }
}

if (-not $ready) {
    if (-not $process.HasExited) { Stop-Process -Id $process.Id }
    throw "SIFI gagal mencapai status siap. Periksa $stderrPath."
}

$state = [ordered]@{
    pid = $process.Id
    port = $Port
    root = $root
    python = (Resolve-Path $python).Path
    started_at = (Get-Date).ToUniversalTime().ToString("o")
    local_url = "http://127.0.0.1:$Port"
}
$state | ConvertTo-Json | Set-Content -Encoding UTF8 $statePath

if (-not $NoBrowser) {
    Start-Process "http://127.0.0.1:$Port"
}

$state | ConvertTo-Json
