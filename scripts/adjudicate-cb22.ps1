param(
    [Parameter(Mandatory=$true)][string]$Bundle,
    [Parameter(Mandatory=$true)][ValidateSet('PRIMARY_ADJUDICATOR','INDEPENDENT_CHECKER')][string]$Role,
    [Parameter(Mandatory=$true)][string]$StaffCode,
    [Parameter(Mandatory=$true)][ValidateSet('PASS','FAIL','INCONCLUSIVE')][string]$Decision,
    [Parameter(Mandatory=$true)][string]$ReviewedAt,
    [Parameter(Mandatory=$true)][switch]$ConflictFree,
    [string]$Root = (Split-Path -Parent $PSScriptRoot)
)
$ErrorActionPreference = 'Stop'
$python = Join-Path $Root '.venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $python -PathType Leaf)) {
    throw 'SIFI local Python runtime is missing. Run scripts\setup-local.ps1 first.'
}
$env:PYTHONPATH = Join-Path $Root 'src'
& $python -m sifi.validation.decision_cli --root $Root --bundle $Bundle --role $Role --staff-code $StaffCode --decision $Decision --reviewed-at $ReviewedAt --conflict-free
if ($LASTEXITCODE -ne 0) { throw 'CB22 adjudication vote failed.' }
