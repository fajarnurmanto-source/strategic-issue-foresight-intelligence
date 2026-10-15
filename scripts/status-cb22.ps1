param([string]$Root = (Split-Path -Parent $PSScriptRoot))
$ErrorActionPreference = 'Stop'
$python = Join-Path $Root '.venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $python -PathType Leaf)) {
    throw 'SIFI local Python runtime is missing. Run scripts\setup-local.ps1 first.'
}
$env:PYTHONPATH = Join-Path $Root 'src'
& $python -m sifi.validation.cli status --root $Root
if ($LASTEXITCODE -ne 0) { throw 'CB22 status check failed.' }
