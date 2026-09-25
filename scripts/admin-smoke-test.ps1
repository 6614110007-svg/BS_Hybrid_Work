# Admin smoke test (dev tool) - requires `php artisan serve --port=8010` running.
# Usage:  powershell -NoProfile -ExecutionPolicy Bypass -File scripts\admin-smoke-test.ps1

$ErrorActionPreference = 'Stop'
$Base = 'http://127.0.0.1:8010'
$tmp = Join-Path $env:TEMP 'bshybrid-smoke'
New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$jar = Join-Path $tmp 'cookies.txt'
$bodyFile = Join-Path $tmp 'body.html'
Remove-Item $jar, $bodyFile -ErrorAction SilentlyContinue

function Invoke-Curl {
    param([string]$Method, [string]$Path, [hashtable]$Data)
    $curlArgs = @('-sS', '-b', $jar, '-c', $jar, '-o', $bodyFile, '-w', '%{http_code}', '-X', $Method, "$Base$Path")
    if ($Data) {
        foreach ($k in $Data.Keys) { $curlArgs += @('--data-urlencode', "$k=$($Data[$k])") }
    }
    $code = & curl.exe @curlArgs
    return [PSCustomObject]@{ Body = (Get-Content -LiteralPath $bodyFile -Raw -Encoding UTF8); Code = $code }
}

$tokenFrom = { param($html) ([regex]::Match($html, 'name="_token" value="([^"]+)"').Groups[1].Value) }

$login = Invoke-Curl -Method GET -Path '/login'
$token = & $tokenFrom $login.Body
if (-not $token) { throw 'no CSRF token on login page' }

$r = Invoke-Curl -Method POST -Path '/login' -Data @{ email = 'admin@deskbooking.local'; password = 'Password123!'; _token = $token }
"login -> $($r.Code) (expect 302)"

foreach ($p in @('/admin/dashboard', '/admin/departments', '/admin/zones', '/admin/desks', '/admin/employees')) {
    $r = Invoke-Curl -Method GET -Path $p
    "$p -> $($r.Code)"
}

$create = Invoke-Curl -Method GET -Path '/admin/departments/create'
$t2 = & $tokenFrom $create.Body
$r = Invoke-Curl -Method POST -Path '/admin/departments' -Data @{ code = 'ITSYS'; name = 'แผนกระบบสารสนเทศ'; is_active = '1'; _token = $t2 }
$page = Invoke-Curl -Method GET -Path '/admin/departments'
"create department -> $($r.Code) / listed: $($page.Body -match 'แผนกระบบสารสนเทศ')"

$empForm = Invoke-Curl -Method GET -Path '/admin/employees/create'
$t3 = & $tokenFrom $empForm.Body
$r = Invoke-Curl -Method POST -Path '/admin/employees' -Data @{
    name = 'ทดสอบ บุคคล'; email = 'smoketest@deskbooking.local'
    employee_code = 'EMP-T01'; role = 'employee'; _token = $t3
}
$empList = Invoke-Curl -Method GET -Path '/admin/employees'
$temp = [regex]::Match($empList.Body, 'font-mono font-bold">(.*?)</span>').Groups[1].Value
"create employee -> $($r.Code) / temp password captured: $([bool]$temp)"

$uid = [regex]::Match($empList.Body, 'employees/(\d+)/toggle-status').Groups[1].Value
$t4 = & $tokenFrom $empList.Body
$r = Invoke-Curl -Method PATCH -Path "/admin/employees/$uid/toggle-status" -Data @{ _token = $t4 }
$page = Invoke-Curl -Method GET -Path '/admin/employees'
"toggle status -> $($r.Code) / badge 'ถูกระงับ' shown: $($page.Body -match 'ถูกระงับ')"

# Use a FRESH cookie jar so we test the inactive login as an anonymous guest
$guestJar = Join-Path $tmp 'guest-cookies.txt'
Remove-Item $guestJar -ErrorAction SilentlyContinue
$jar = $guestJar
$g = Invoke-Curl -Method GET -Path '/login'
$t5 = & $tokenFrom $g.Body
$r = Invoke-Curl -Method POST -Path '/login' -Data @{ email = 'smoketest@deskbooking.local'; password = $temp; _token = $t5 }
"inactive login -> $($r.Code) (expect 302 back to login)"
$final = Invoke-Curl -Method GET -Path '/login'
"inactive error message shown: $($final.Body -match 'ถูกระงับการใช้งาน')"

Write-Host "`nSmoke test completed."