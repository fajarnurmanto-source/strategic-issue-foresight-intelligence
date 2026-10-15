param(
    [Parameter(Mandatory=$true)][string]$PrimaryAdjudicatorCode,
    [Parameter(Mandatory=$true)][string]$IndependentCheckerCode,
    [Parameter(Mandatory=$true)][ValidateSet('SATISFIED','NOT_REQUIRED_DOCUMENTED')][string]$ExternalReviewRequirement,
    [Parameter(Mandatory=$true)][switch]$ConflictReviewComplete,
    [string]$Root = (Split-Path -Parent $PSScriptRoot)
)
$ErrorActionPreference = 'Stop'
$python = Join-Path $Root '.venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $python -PathType Leaf)) {
    throw 'SIFI local Python runtime is missing. Run scripts\setup-local.ps1 first.'
}
$env:PYTHONPATH = Join-Path $Root 'src'
& $python -m sifi.validation.gate_cli --root $Root --primary $PrimaryAdjudicatorCode --checker $IndependentCheckerCode --external-review $ExternalReviewRequirement --conflict-review-complete
if ($LASTEXITCODE -ne 0) { throw 'CB22 gate configuration failed.' }
