# Nightly MySQL backup: runs "php artisan db:backup" and logs to storage\logs\backup.log.
# Exit code is non-zero if the dump or the second-location copy failed.
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) { $php = 'C:\xampp\php\php.exe' }
if (-not (Test-Path $php)) { throw "php.exe not found. Add PHP to PATH or edit this script." }

$log = Join-Path $project 'storage\logs\backup.log'
$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
Add-Content -Path $log -Value "[$stamp] backup started"

# cmd merges stderr itself, so PHP warnings do not become PowerShell error records.
$out = cmd /c "`"$php`" artisan db:backup 2>&1" | Out-String
$code = $LASTEXITCODE
Add-Content -Path $log -Value $out.TrimEnd()
Add-Content -Path $log -Value "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] backup finished, exit code $code"
exit $code
