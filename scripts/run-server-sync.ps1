$ErrorActionPreference = 'Stop'

$projectPath = 'E:\xampp\htdocs\akses2-laravel'
$requestDirectory = Join-Path $projectPath 'storage\app\server-sync\requests'

Set-Location $projectPath

if (-not (Test-Path -LiteralPath $requestDirectory)) {
    exit 2
}

$emptyChecks = 0

while ($emptyChecks -lt 2) {
    $requestFile = Get-ChildItem -LiteralPath $requestDirectory -Filter '*.json' -File |
        Sort-Object LastWriteTime |
        Select-Object -First 1

    if ($null -eq $requestFile) {
        $emptyChecks++
        Start-Sleep -Milliseconds 750
        continue
    }

    $emptyChecks = 0
    $processingPath = $requestFile.FullName + '.processing'
    Move-Item -LiteralPath $requestFile.FullName -Destination $processingPath

    try {
        $request = Get-Content -LiteralPath $processingPath -Raw | ConvertFrom-Json

        if (-not $request.id -or $request.operation -notin @('test', 'pull')) {
            continue
        }

        & 'E:\xampp\php\php.exe' artisan server-sync:run $request.operation "--request-id=$($request.id)"
    }
    finally {
        Remove-Item -LiteralPath $processingPath -Force -ErrorAction SilentlyContinue
    }
}

exit 0
