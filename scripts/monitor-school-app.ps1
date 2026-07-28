param(
    [string]$BaseUrl = 'https://app.smaafbs.sch.id',
    [string]$Source = 'school-main',
    [string]$TokenFile = '',
    [string]$LogFile = '',
    [string]$StateFile = ''
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($TokenFile)) {
    $TokenFile = Join-Path $ProjectRoot 'storage\app\private\literacy-school-monitor-token.txt'
}

if ([string]::IsNullOrWhiteSpace($LogFile)) {
    $LogFile = Join-Path $ProjectRoot 'storage\logs\literacy-school-monitor.csv'
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
$dnsOk = $false
$tcpOk = $false
$httpStatus = 0
$durationMs = 0
$errorCode = ''

try {
    Resolve-DnsName -Name $uri.Host -Type A -ErrorAction Stop | Out-Null
    $dnsOk = $true
} catch {
    $errorCode = 'DNS_FAILED'
}

if ($dnsOk) {
    try {
        $tcpOk = Test-NetConnection -ComputerName $uri.Host -Port 443 -InformationLevel Quiet
        if (-not $tcpOk) {
            $errorCode = 'TCP_443_FAILED'
        }
    } catch {
        $errorCode = 'TCP_443_FAILED'
    }
}

if ($tcpOk) {
    $nodeProbe = Join-Path $PSScriptRoot 'monitor-school-http-probe.cjs'

    if ((Get-Command node -ErrorAction SilentlyContinue) -and (Test-Path -LiteralPath $nodeProbe)) {
        try {
            $probeResult = (& node $nodeProbe probe ($BaseUrl.TrimEnd('/') + '/up') 2>$null) | ConvertFrom-Json
            $httpStatus = [int]$probeResult.status
            $durationMs = [int]$probeResult.duration_ms
            if ($LASTEXITCODE -ne 0 -or $httpStatus -lt 200 -or $httpStatus -ge 400) {
                $errorCode = if ($httpStatus -gt 0) { 'HTTP_' + $httpStatus } else { 'CONNECTION_RESET_OR_TIMEOUT' }
            }
        } catch {
            $errorCode = 'NODE_HTTPS_PROBE_FAILED'
        }
    } else {
        $stopwatch = [Diagnostics.Stopwatch]::StartNew()
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            $response = Invoke-WebRequest -Uri ($BaseUrl.TrimEnd('/') + '/up') -Method Get -TimeoutSec 20 -UseBasicParsing
            $httpStatus = [int]$response.StatusCode
            if ($httpStatus -lt 200 -or $httpStatus -ge 400) {
                $errorCode = 'HTTP_' + $httpStatus
            }
        } catch {
            $errorCode = 'CONNECTION_RESET_OR_TIMEOUT'
        } finally {
            $stopwatch.Stop()
            $durationMs = [int]$stopwatch.ElapsedMilliseconds
        }
    }
}

$isOk = $dnsOk -and $tcpOk -and $httpStatus -ge 200 -and $httpStatus -lt 400
$previousFailures = 0

if (Test-Path -LiteralPath $StateFile) {
    try {
        $previousState = Get-Content -LiteralPath $StateFile -Raw | ConvertFrom-Json
        $previousFailures = [int]$previousState.consecutive_failures
    } catch {
        $previousFailures = 0
    }
}

$consecutiveFailures = if ($isOk) { 0 } else { $previousFailures + 1 }
$status = if ($isOk -and $previousFailures -gt 0) { 'recovered' } elseif ($isOk) { 'ok' } else { 'failed' }
$state = [ordered]@{
    checked_at = $checkedAt.ToString('o')
    status = $status
    consecutive_failures = $consecutiveFailures
    last_error_code = $errorCode
}
$state | ConvertTo-Json | Set-Content -LiteralPath $StateFile -Encoding UTF8

$logRow = [pscustomobject]@{
    checked_at = $checkedAt.ToString('o')
    source = $Source
    status = $status
    dns_ok = $dnsOk
    tcp_ok = $tcpOk
    http_status = $httpStatus
    duration_ms = $durationMs
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
            consecutive_failures = $previousFailures
            error_code = if ($status -eq 'recovered') { 'RECOVERED_AFTER_FAILURES' } else { $null }
            checked_at = $checkedAt.ToString('o')
            context = @{ client_version = '1.0' }
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

$logRow
