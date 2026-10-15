param(
    [switch]$NoDemo,
    [switch]$NoBrowser,
    [int]$Port = 8765
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$python = Join-Path $root ".venv\Scripts\python.exe"

if (-not (Test-Path $python)) {
    & (Join-Path $PSScriptRoot "setup-local.ps1")
}

if (-not $NoDemo) {
    & (Join-Path $PSScriptRoot "demo-local.ps1")
}

$env:PYTHONPATH = Join-Path $root "src"
$serverArgs = @("-m", "sifi.web.cli", "--root", $root, "--port", $Port)
if (-not $NoBrowser) {
    $serverArgs += "--open-browser"
}
& $python @serverArgs
