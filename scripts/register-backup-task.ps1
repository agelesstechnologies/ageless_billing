# Registers (or removes) the nightly 2 AM backup in Windows Task Scheduler.
#   .\scripts\register-backup-task.ps1                  # runs as you, only while you are logged on
#   .\scripts\register-backup-task.ps1 -Credential (Get-Credential)   # runs whether or not you are logged on
#   .\scripts\register-backup-task.ps1 -Remove
# Run from an elevated PowerShell if registration is refused.
param(
  [string]$Time = '02:00',
  [System.Management.Automation.PSCredential]$Credential,
  [switch]$Remove
)
$ErrorActionPreference = 'Stop'
$name = 'Ageless Park - Nightly DB Backup'

if ($Remove) {
  Unregister-ScheduledTask -TaskName $name -Confirm:$false
  Write-Host "Removed task '$name'."
  return
}

$script = Join-Path $PSScriptRoot 'backup-nightly.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
  -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$script`"" `
  -WorkingDirectory (Split-Path -Parent $PSScriptRoot)
$trigger = New-ScheduledTaskTrigger -Daily -At $Time
# StartWhenAvailable: if the PC was off at 2 AM, run as soon as it is back on.
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries `
  -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 2) -MultipleInstances IgnoreNew

if ($Credential) {
  Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings `
    -User $Credential.UserName -Password $Credential.GetNetworkCredential().Password -RunLevel Limited -Force | Out-Null
} else {
  $principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
  Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
}
Write-Host "Registered '$name' to run daily at $Time. Test it now with:  Start-ScheduledTask -TaskName '$name'"
