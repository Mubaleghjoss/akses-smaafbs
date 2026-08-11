param(
    [string]$BaseUrl = 'https://app.smaafbs.sch.id',
    [string]$Source = 'school-main',
    [string]$TokenFile = '',
    [string]$LogFile = '',
    [string]$StateFile = '',
    [bool]$MonitorEnabled = $true,
    [string]$EventType = 'heartbeat'
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($TokenFile)) {
    $TokenFile = Join-Path $ProjectRoot 'storage\app\private\literacy-school-monitor-token.txt'
}

if ([string]::IsNullOrWhiteSpace($LogFile)) {
    $LogFile = Join-Path $ProjectRoot 'storage\logs\literacy-school-monitor-v2.csv'
}

if ([string]::IsNullOrWhiteSpace($StateFile)) {
    $StateFile = Join-Path $ProjectRoot 'storage\app\private\literacy-school-monitor-state.json'
}

$logDirectory = Split-Path -Parent $LogFile
$stateDirectory = Split-Path -Parent $StateFile
New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null
New-Item -ItemType Directory -Force -Path $stateDirectory | Out-Null

$uri = [Uri]$BaseUrl
$checkedAt = [DateTimeOffset]::Now
$gatewayOk = $false
$internetOk = $false
$dnsOk = $false
$tcpOk = $false
$httpStatus = 0
$durationMs = 0
$gatewayDurationMs = 0
$internetDurationMs = 0
$dnsDurationMs = 0
$tcpDurationMs = 0
$httpsDurationMs = 0
$errorCode = ''

function Test-TcpEndpoint {
    param(
        [string]$HostName,
        [int]$Port = 443,
        [int]$TimeoutMs = 5000
    )

    $client = [Net.Sockets.TcpClient]::new()

    try {
        $task = $client.ConnectAsync($HostName, $Port)

        return $task.Wait($TimeoutMs) -and $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

$gatewayAddress = ''

try {
    $routeOutput = & route.exe print -4 2>$null
    $routeMatch = $routeOutput | Select-String -Pattern '^\s*0\.0\.0\.0\s+0\.0\.0\.0\s+(\S+)' | Select-Object -First 1

    if ($routeMatch -and $routeMatch.Matches.Count -gt 0) {
        $gatewayAddress = [string]$routeMatch.Matches[0].Groups[1].Value
    }
} catch {
    $gatewayAddress = ''
}

if (-not [string]::IsNullOrWhiteSpace($gatewayAddress)) {
    $stopwatch = [Diagnostics.Stopwatch]::StartNew()
    try {
        $gatewayOk = Test-Connection -ComputerName $gatewayAddress -Count 1 -Quiet -ErrorAction SilentlyContinue

        if (-not $gatewayOk) {
            $arpOutput = & arp.exe -a $gatewayAddress 2>$null
            $gatewayOk = ($arpOutput -join "`n") -match [regex]::Escape($gatewayAddress)
        }
    } finally {
        $stopwatch.Stop()
        $gatewayDurationMs = [int]$stopwatch.ElapsedMilliseconds
    }
}

$stopwatch = [Diagnostics.Stopwatch]::StartNew()
try {
    $internetOk = Test-TcpEndpoint -HostName '1.1.1.1' -Port 443 -TimeoutMs 5000
} finally {
    $stopwatch.Stop()
    $internetDurationMs = [int]$stopwatch.ElapsedMilliseconds
}

$stopwatch = [Diagnostics.Stopwatch]::StartNew()
try {
    Resolve-DnsName -Name $uri.Host -Type A -ErrorAction Stop | Out-Null
    $dnsOk = $true
} catch {
    $dnsOk = $false
} finally {
    $stopwatch.Stop()
    $dnsDurationMs = [int]$stopwatch.ElapsedMilliseconds
}

if ($dnsOk) {
    $stopwatch = [Diagnostics.Stopwatch]::StartNew()
    try {
        $tcpOk = Test-TcpEndpoint -HostName $uri.Host -Port 443 -TimeoutMs 5000
    } catch {
        $tcpOk = $false
    } finally {
        $stopwatch.Stop()
        $tcpDurationMs = [int]$stopwatch.ElapsedMilliseconds
    }
}

if ($tcpOk) {
    $nodeProbe = Join-Path $PSScriptRoot 'monitor-school-http-probe.cjs'

    if ((Get-Command node -ErrorAction SilentlyContinue) -and (Test-Path -LiteralPath $nodeProbe)) {
        $stopwatch = [Diagnostics.Stopwatch]::StartNew()
        try {
            $probeResult = (& node $nodeProbe probe ($BaseUrl.TrimEnd('/') + '/up') 2>$null) | ConvertFrom-Json
            $httpStatus = [int]$probeResult.status
        } catch {
            $httpStatus = 0
        } finally {
            $stopwatch.Stop()
            $httpsDurationMs = [int]$stopwatch.ElapsedMilliseconds
        }
    } else {
        $stopwatch = [Diagnostics.Stopwatch]::StartNew()
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            $response = Invoke-WebRequest -Uri ($BaseUrl.TrimEnd('/') + '/up') -Method Get -TimeoutSec 20 -UseBasicParsing
            $httpStatus = [int]$response.StatusCode
        } catch {
            $httpStatus = 0
        } finally {
            $stopwatch.Stop()
            $httpsDurationMs = [int]$stopwatch.ElapsedMilliseconds
        }
    }
}

$durationMs = $gatewayDurationMs + $internetDurationMs + $dnsDurationMs + $tcpDurationMs + $httpsDurationMs
$isOk = $dnsOk -and $tcpOk -and $httpStatus -ge 200 -and $httpStatus -lt 400

if (-not $isOk) {
    $errorCode = if (-not $gatewayOk -and -not $internetOk) {
        'GATEWAY_OR_LAN_FAILED'
    } elseif (-not $internetOk) {
        'INTERNET_OR_ONT_FAILED'
    } elseif (-not $dnsOk) {
        'DNS_FAILED'
    } elseif (-not $tcpOk) {
        'TCP_443_FAILED'
    } elseif ($httpStatus -gt 0) {
        'HTTP_' + $httpStatus
    } else {
        'HTTPS_RESET_OR_TIMEOUT'
    }
}

$previousFailures = 0
$previousErrorCode = ''

if (Test-Path -LiteralPath $StateFile) {
    try {
        $previousState = Get-Content -LiteralPath $StateFile -Raw | ConvertFrom-Json
        $previousFailures = [int]$previousState.consecutive_failures
        $previousErrorCode = [string]$previousState.last_error_code
    } catch {
        $previousFailures = 0
        $previousErrorCode = ''
    }
}

$consecutiveFailures = if ($isOk) { 0 } else { $previousFailures + 1 }
$status = if ($isOk -and $previousFailures -gt 0) { 'recovered' } elseif ($isOk) { 'ok' } else { 'failed' }
$state = [ordered]@{
    checked_at = $checkedAt.ToString('o')
    status = $status
    monitor_enabled = $MonitorEnabled
    consecutive_failures = $consecutiveFailures
    last_error_code = $errorCode
    gateway_ok = $gatewayOk
    internet_ok = $internetOk
    dns_ok = $dnsOk
    tcp_ok = $tcpOk
    http_status = $httpStatus
    duration_ms = $durationMs
}
$state | ConvertTo-Json | Set-Content -LiteralPath $StateFile -Encoding UTF8

$logRow = [pscustomobject]@{
    checked_at = $checkedAt.ToString('o')
    source = $Source
    status = $status
    gateway_ok = $gatewayOk
    internet_ok = $internetOk
    dns_ok = $dnsOk
    tcp_ok = $tcpOk
    http_status = $httpStatus
    duration_ms = $durationMs
    gateway_duration_ms = $gatewayDurationMs
    internet_duration_ms = $internetDurationMs
    dns_duration_ms = $dnsDurationMs
    tcp_duration_ms = $tcpDurationMs
    https_duration_ms = $httpsDurationMs
    consecutive_failures = $consecutiveFailures
    error_code = $errorCode
}

if (Test-Path -LiteralPath $LogFile) {
    $logRow | Export-Csv -LiteralPath $LogFile -Append -NoTypeInformation -Encoding UTF8
} else {
    $logRow | Export-Csv -LiteralPath $LogFile -NoTypeInformation -Encoding UTF8
}

if ($isOk -and (Test-Path -LiteralPath $TokenFile)) {
    $token = (Get-Content -LiteralPath $TokenFile -Raw).Trim()

    if (-not [string]::IsNullOrWhiteSpace($token)) {
        $body = @{
            source = $Source
            status = $status
            dns_ok = $dnsOk
            tcp_ok = $tcpOk
            http_status = $httpStatus
            duration_ms = $durationMs
            consecutive_failures = $consecutiveFailures
            error_code = if ($status -eq 'recovered') { 'RECOVERED_AFTER_FAILURES' } else { $null }
            checked_at = $checkedAt.ToString('o')
            context = @{
                client_version = '2.0'
                monitor_enabled = $MonitorEnabled
                event_type = $EventType
                gateway_ok = $gatewayOk
                internet_ok = $internetOk
                gateway_duration_ms = $gatewayDurationMs
                internet_duration_ms = $internetDurationMs
                dns_duration_ms = $dnsDurationMs
                tcp_duration_ms = $tcpDurationMs
                https_duration_ms = $httpsDurationMs
                previous_error_code = if ($status -eq 'recovered') { $previousErrorCode } else { $null }
            }
        } | ConvertTo-Json -Depth 3

        $nodeProbe = Join-Path $PSScriptRoot 'monitor-school-http-probe.cjs'
        $payloadFile = Join-Path $stateDirectory 'literacy-school-monitor-payload.json'

        try {
            [IO.File]::WriteAllText($payloadFile, $body, [Text.UTF8Encoding]::new($false))
            if ((Get-Command node -ErrorAction SilentlyContinue) -and (Test-Path -LiteralPath $nodeProbe)) {
                & node $nodeProbe post ($BaseUrl.TrimEnd('/') + '/api/v1/monitoring/school-network') $TokenFile $payloadFile | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    throw 'Endpoint monitor menolak data.'
                }
            } else {
                Invoke-RestMethod `
                    -Uri ($BaseUrl.TrimEnd('/') + '/api/v1/monitoring/school-network') `
                    -Method Post `
                    -Headers @{ Authorization = 'Bearer ' + $token; Accept = 'application/json' } `
                    -ContentType 'application/json' `
                    -Body $body `
                    -TimeoutSec 20 | Out-Null
            }
        } catch {
            Write-Warning ('Hasil lokal tersimpan, tetapi belum dapat dikirim ke server: ' + $_.Exception.Message)
        } finally {
            if (Test-Path -LiteralPath $payloadFile) {
                Remove-Item -LiteralPath $payloadFile -Force
            }
        }
    }
}

if ((Test-Path -LiteralPath $LogFile) -and (Get-Item -LiteralPath $LogFile).Length -gt 5MB) {
    $archivePath = Join-Path $logDirectory ('literacy-school-monitor-v2-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.csv')
    Move-Item -LiteralPath $LogFile -Destination $archivePath
}

Get-ChildItem -LiteralPath $logDirectory -Filter 'literacy-school-monitor-v2-*.csv' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } |
    Remove-Item -Force -ErrorAction SilentlyContinue
