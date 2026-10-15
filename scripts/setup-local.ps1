$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$VenvPython = Join-Path $Root ".venv\Scripts\python.exe"

if (-not (Test-Path -LiteralPath $VenvPython)) {
    $Python = Get-Command py -ErrorAction SilentlyContinue
    if ($null -ne $Python) {
        & py -3 -m venv (Join-Path $Root ".venv")
    }
    else {
        $Python = Get-Command python -ErrorAction SilentlyContinue
        if ($null -eq $Python) { throw "Python 3 tidak ditemukan." }
        & python -m venv (Join-Path $Root ".venv")
    }
}

$env:PYTHONPATH = Join-Path $Root "src"
& $VenvPython -m sifi.local_cli init --root $Root
if ($LASTEXITCODE -ne 0) { throw "Inisialisasi lokal SIFI gagal." }
Write-Host "SIFI_LOCAL_SETUP=PASS"
Write-Host "ROOT=$Root"
