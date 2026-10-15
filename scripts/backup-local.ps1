param([string]$Label = "")
$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Python = Join-Path $Root ".venv\Scripts\python.exe"
if (-not (Test-Path -LiteralPath $Python)) { throw "Jalankan scripts\setup-local.ps1 dahulu." }
$env:PYTHONPATH = Join-Path $Root "src"
$Args = @("-m", "sifi.local_cli", "backup", "--root", $Root)
if ($Label) { $Args += @("--label", $Label) }
& $Python @Args
if ($LASTEXITCODE -ne 0) { throw "Backup SIFI gagal." }
