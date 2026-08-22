param(
    [ValidateSet('enable', 'disable', 'status')]
    [string]$Action = 'status',
    [string]$TaskName = 'SMA AFBS Literacy Network Monitor'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$stateFile = Join-Path $projectRoot 'storage\app\private\literacy-school-monitor-state.json'
$logFile = Join-Path $projectRoot 'storage\logs\literacy-school-monitor-v2.csv'

Add-Type -AssemblyName PresentationFramework

function Show-MonitorMessage {
    param(
        [string]$Message,
        [string]$Title = 'Monitor Jaringan SMA AFBS',
        [System.Windows.MessageBoxImage]$Icon = [System.Windows.MessageBoxImage]::Information
    )

    [System.Windows.MessageBox]::Show(
        $Message,
        $Title,
        [System.Windows.MessageBoxButton]::OK,
        $Icon
    ) | Out-Null
}

try {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop

    if ($Action -eq 'enable') {
        Enable-ScheduledTask -TaskName $TaskName | Out-Null
        Start-ScheduledTask -TaskName $TaskName
        Show-MonitorMessage "Monitor jaringan sudah diaktifkan.`nPemeriksaan pertama sedang dijalankan dan berikutnya berjalan setiap 1 menit."
        exit 0
    }

    if ($Action -eq 'disable') {
        $monitorScript = Join-Path $PSScriptRoot 'monitor-school-app.ps1'

        if (Test-Path -LiteralPath $monitorScript) {
            try {
                & $monitorScript -MonitorEnabled $false -EventType 'state_change'
            } catch {
                # The local disabled state remains authoritative when the website is unreachable.
            }
        }

        Disable-ScheduledTask -TaskName $TaskName | Out-Null
        Show-MonitorMessage "Monitor jaringan sudah dinonaktifkan.`nPemeriksaan otomatis setiap 1 menit dihentikan."
        exit 0
    }

    $taskInfo = Get-ScheduledTaskInfo -TaskName $TaskName
    $enabled = $task.State -ne 'Disabled'
    $state = $null

    if (Test-Path -LiteralPath $stateFile) {
        try {
            $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
        } catch {
            $state = $null
        }
    }

    $statusLines = @(
        'Status task: ' + $(if ($enabled) { 'AKTIF' } else { 'NONAKTIF' }),
        'Jadwal berikutnya: ' + $(if ($taskInfo.NextRunTime) { $taskInfo.NextRunTime.ToString('dd/MM/yyyy HH:mm:ss') } else { '-' }),
        'Hasil task terakhir: ' + $taskInfo.LastTaskResult
    )

    if ($state) {
        $statusLines += @(
            'Pemeriksaan terakhir: ' + ([DateTimeOffset]$state.checked_at).ToString('dd/MM/yyyy HH:mm:ss'),
            'Hasil koneksi: ' + [string]$state.status,
            'Kode gangguan: ' + $(if ($state.last_error_code) { [string]$state.last_error_code } else { '-' })
        )
    } else {
        $statusLines += 'Pemeriksaan terakhir: belum ada data'
    }

    $statusLines += 'Log lokal: ' + $logFile
    Show-MonitorMessage ($statusLines -join "`n")
} catch {
    Show-MonitorMessage (
        "Kontrol monitor gagal: $($_.Exception.Message)`nJalankan installer monitor menggunakan akun Windows yang memiliki izin Task Scheduler.",
        'Monitor Jaringan SMA AFBS',
        [System.Windows.MessageBoxImage]::Error
    )
    exit 1
}
