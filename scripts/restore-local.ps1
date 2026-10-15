param([Parameter(Mandatory=$true)][string]$BackupPath)
$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Python = Join-Path $Root ".venv\Scripts\python.exe"
if (-not (Test-Path -LiteralPath $Python)) { throw "Jalankan scripts\setup-local.ps1 dahulu." }
$ResolvedBackup = (Resolve-Path -LiteralPath $BackupPath).Path
$env:PYTHONPATH = Join-Path $Root "src"
& $Python -m sifi.local_cli restore --root $Root --backup-path $ResolvedBackup
if ($LASTEXITCODE -ne 0) { throw "Restore SIFI gagal." }
