param(
    [string]$TaskName = 'SMA AFBS Literacy Network Monitor',
    [string]$DesktopFolder = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$hiddenRunner = Join-Path $PSScriptRoot 'monitor-school-app-hidden.vbs'
$controlRunner = Join-Path $PSScriptRoot 'literacy-monitor-control-hidden.vbs'

if ([string]::IsNullOrWhiteSpace($DesktopFolder)) {
    $DesktopFolder = [Environment]::GetFolderPath('Desktop')
}

if (-not (Test-Path -LiteralPath $hiddenRunner) -or -not (Test-Path -LiteralPath $controlRunner)) {
    throw 'File launcher monitor tidak ditemukan. Pastikan repository sudah diperbarui.'
}

$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue

if ($existing) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

$action = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ('//B //Nologo "' + $hiddenRunner + '"')
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 5)
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2)
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType Interactive -RunLevel Limited

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger `
    -Settings $settings -Principal $principal `
    -Description 'Memantau DNS, TCP 443, dan HTTPS aplikasi SMA AFBS setiap 5 menit tanpa membuka jendela PowerShell.' | Out-Null

$shortcutDefinitions = @(
    @{ Name = 'Aktifkan Monitor Jaringan.lnk'; Action = 'enable' },
    @{ Name = 'Nonaktifkan Monitor Jaringan.lnk'; Action = 'disable' },
    @{ Name = 'Cek Status Monitor Jaringan.lnk'; Action = 'status' }
)
$shell = New-Object -ComObject WScript.Shell

foreach ($definition in $shortcutDefinitions) {
    $shortcut = $shell.CreateShortcut((Join-Path $DesktopFolder $definition.Name))
    $shortcut.TargetPath = Join-Path $env:WINDIR 'System32\wscript.exe'
    $shortcut.Arguments = '//B //Nologo "' + $controlRunner + '" ' + $definition.Action
    $shortcut.WorkingDirectory = $projectRoot
    $shortcut.Description = 'Kontrol monitor jaringan aplikasi SMA AFBS'
    $shortcut.Save()
}

Start-ScheduledTask -TaskName $TaskName

Write-Output "Monitor terpasang tanpa jendela konsol. Tiga shortcut dibuat di $DesktopFolder."
