<# Employee-side E2E smoke: starts a server and runs the PHP integration script. #>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$server = Start-Process php -ArgumentList @('-S', '127.0.0.1:8010', '-t', 'public') -WorkingDirectory $root -PassThru -WindowStyle Hidden
try {
    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        try { Invoke-WebRequest 'http://127.0.0.1:8010/login' -UseBasicParsing -TimeoutSec 2 | Out-Null; $ready = $true; break } catch { Start-Sleep -Milliseconds 500 }
    }
    if (-not $ready) { throw 'Server did not start' }

    $env:E2E_BASE_URL = 'http://127.0.0.1:8010'
    & php "$root\scripts\employee_e2e.php"
    if ($LASTEXITCODE -ne 0) { throw "E2E exited with code $LASTEXITCODE" }
}
finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force }
}