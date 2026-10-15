$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$python = Join-Path $root ".venv\Scripts\python.exe"

if (-not (Test-Path $python)) {
    & (Join-Path $PSScriptRoot "setup-local.ps1")
}

$env:PYTHONPATH = Join-Path $root "src"
& $python -m sifi.local_cli demo --root $root
