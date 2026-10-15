$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$python = Join-Path $root ".venv\Scripts\python.exe"

if (-not (Test-Path $python)) {
    & (Join-Path $PSScriptRoot "setup-local.ps1")
}

$env:PYTHONPATH = Join-Path $root "src"
Push-Location $root
try {
    & $python -m unittest discover -s tests
    if ($LASTEXITCODE -ne 0) { throw "Pengujian SIFI gagal." }
    & $python -m sifi.release_check --root $root
    if ($LASTEXITCODE -ne 0) { throw "Pemeriksaan rilis lokal SIFI gagal." }
}
finally {
    Pop-Location
}
