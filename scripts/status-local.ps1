$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Python = Join-Path $Root ".venv\Scripts\python.exe"
if (-not (Test-Path -LiteralPath $Python)) { throw "Jalankan scripts\setup-local.ps1 dahulu." }
$env:PYTHONPATH = Join-Path $Root "src"
& $Python -m sifi.local_cli status --root $Root
if ($LASTEXITCODE -ne 0) { throw "Pemeriksaan status SIFI gagal." }
