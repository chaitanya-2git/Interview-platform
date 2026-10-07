$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8765/interviews'
$script:checks = 0

function Check([bool]$condition, [string]$message) {
    if (-not $condition) { throw "HTTP acceptance failed: $message" }
    $script:checks++
}

function Token([string]$html) {
    $match = [regex]::Match($html, 'name="_token" value="([^"]+)"')
    if (-not $match.Success) { throw 'CSRF token was not rendered.' }
    return $match.Groups[1].Value
}

function Status([scriptblock]$request) {
    try { & $request | Out-Null; return 200 }
    catch { return [int]$_.Exception.Response.StatusCode }
}

$guest = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$login = Invoke-WebRequest "$base/login" -WebSession $guest -UseBasicParsing
Check ($login.StatusCode -eq 200 -and $login.Content.Contains('/interviews/assets/app.css')) 'base-path login assets'
$guard = Invoke-WebRequest "$base/admin/reports" -WebSession $guest -UseBasicParsing
Check ($guard.Content.Contains('Welcome back')) 'guest admin redirect'

$adminLogin = Invoke-WebRequest "$base/login" -WebSession $guest -Method Post -Body @{ _token = Token $login.Content; identity = 'phase.four.admin'; password = 'AcceptancePass12' } -UseBasicParsing
Check ($adminLogin.Content.Contains('Operations dashboard')) 'admin login'
$report = Invoke-WebRequest "$base/admin/reports?preset=custom&start=2032-05-01&end=2032-05-31" -WebSession $guest -UseBasicParsing
Check ($report.Content.Contains('Resource utilization') -and $report.Content.Contains('Support workload')) 'report render'
Check ($report.Headers['Content-Security-Policy'] -and $report.Headers['X-Frame-Options'] -eq 'DENY') 'security headers'
$search = Invoke-WebRequest "$base/admin/search?q=BluJay" -Headers @{ Accept = 'application/json' } -WebSession $guest -UseBasicParsing
Check ($search.Content.Contains('BluJay Search Operations')) 'global search result'
$short = Invoke-WebRequest "$base/admin/search?q=x" -Headers @{ Accept = 'application/json' } -WebSession $guest -UseBasicParsing
Check ($short.StatusCode -eq 200 -and $short.Content.Contains('"minimum":2') -and $short.Content.Contains('"candidates":[]')) 'short search handling'
$audit = Invoke-WebRequest "$base/admin/audit" -WebSession $guest -UseBasicParsing
Check ($audit.Content.Contains('Resource updated')) 'audit listing'
$detail = Invoke-WebRequest "$base/admin/audit/detail?id=2" -WebSession $guest -UseBasicParsing
Check ($detail.Content.Contains('[redacted]') -and -not $detail.Content.Contains('never-show')) 'audit secret redaction'
$csv = Invoke-WebRequest "$base/admin/reports/export.csv?preset=custom&start=2032-05-01&end=2032-05-31" -WebSession $guest -UseBasicParsing
Check ($csv.Content.Contains("'=1+1")) 'CSV formula neutralization'

$candidate = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$candidateLoginForm = Invoke-WebRequest "$base/login" -WebSession $candidate -UseBasicParsing
$candidateLogin = Invoke-WebRequest "$base/login" -WebSession $candidate -Method Post -Body @{ _token = Token $candidateLoginForm.Content; identity = '+919300000001'; password = 'AcceptancePass12' } -UseBasicParsing
Check ($candidateLogin.Content.Contains('Your interview workspace')) 'candidate login'
$mine = Invoke-WebRequest "$base/candidate/interviews" -WebSession $candidate -UseBasicParsing
Check ($mine.Content.Contains('BluJay Search Operations')) 'candidate interview ownership view'
$adminDenied = Status { Invoke-WebRequest "$base/admin/reports" -WebSession $candidate -UseBasicParsing }
Check ($adminDenied -eq 403) 'candidate admin denial'
$idor = Status { Invoke-WebRequest "$base/candidate/interview?id=5" -WebSession $candidate -UseBasicParsing }
Check ($idor -eq 404) 'candidate IDOR denial'
$logoutToken = Token $mine.Content
$logout = Invoke-WebRequest "$base/logout" -WebSession $candidate -Method Post -Body @{ _token = $logoutToken } -UseBasicParsing
Check ($logout.Content.Contains('Welcome back')) 'logout'

Write-Output "$checks HTTP acceptance checks passed."
