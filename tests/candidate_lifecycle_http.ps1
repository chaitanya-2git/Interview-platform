$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8766/interviews'
$origin = 'http://127.0.0.1:8766'
$script:checks = 0

function Check([bool]$condition, [string]$message) {
    if (-not $condition) { throw "Candidate lifecycle HTTP failed: $message" }
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
function Login([string]$identity, [string]$password) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $form = Invoke-WebRequest "$base/login" -WebSession $session -UseBasicParsing
    $page = Invoke-WebRequest "$base/login" -WebSession $session -Method Post -Body @{ _token = Token $form.Content; identity = $identity; password = $password } -UseBasicParsing
    return @{ Session = $session; Page = $page }
}

# Exercise the real multipart registration and photo pipeline.
$temp = Join-Path $env:TEMP ('blujay-photo-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp | Out-Null
$png = Join-Path $temp 'avatar.png'
[IO.File]::WriteAllBytes($png, [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='))
$jar = Join-Path $temp 'cookies.txt'
$registerForm = Join-Path $temp 'register.html'
$registerResult = Join-Path $temp 'registered.html'
& curl.exe -sS -c $jar "$base/register" -o $registerForm
$registerHtml = Get-Content $registerForm -Raw
$registerToken = Token $registerHtml
$technologyMatch = [regex]::Match($registerHtml, 'name="technology_ids\[\]" value="(\d+)"')
Check $technologyMatch.Success 'registration exposes an active technology'
$technologyId = $technologyMatch.Groups[1].Value
& curl.exe -sS -L -b $jar -c $jar -F "_token=$registerToken" -F 'full_name=Photo Upload Candidate' -F 'phone=+919711111111' -F 'password=PhotoUpload12!' -F "technology_ids[]=$technologyId" -F 'classification=Genuine' -F "photo=@$png;type=image/png" "$base/register" -o $registerResult
$registeredHtml = Get-Content $registerResult -Raw
Check ($registeredHtml.Contains('Your interview workspace')) 'candidate uploads a profile photo during registration'
$ownPhotoMatch = [regex]::Match($registeredHtml, 'src="([^\"]*profile/photo\?[^\"]+)"')
Check $ownPhotoMatch.Success 'candidate sees own photo URL'
$ownPhotoUrl = [Net.WebUtility]::HtmlDecode($ownPhotoMatch.Groups[1].Value)
$ownHeaders = Join-Path $temp 'own-headers.txt'
$ownCode = & curl.exe -sS -D $ownHeaders -o (Join-Path $temp 'own.jpg') -w '%{http_code}' -b $jar "$origin$ownPhotoUrl"
Check ($ownCode -eq '200' -and (Get-Content $ownHeaders -Raw).Contains('image/jpeg')) 'candidate can fetch own re-encoded private photo'

$adminLogin = Login 'lifecycle.admin' 'LifecycleAdmin12'
$admin = $adminLogin.Session
Check ($adminLogin.Page.Content.Contains('Operations dashboard')) 'admin login'
$directory = Invoke-WebRequest "$base/admin/candidates?search=Photo%20Upload%20Candidate" -WebSession $admin -UseBasicParsing
$candidateMatch = [regex]::Match($directory.Content, 'admin/candidate\?id=(\d+)')
Check ($candidateMatch.Success -and $directory.Content.Contains('profile/photo?id=')) 'admin candidate directory renders private photo'
$photoCandidateId = $candidateMatch.Groups[1].Value
$photoDetail = Invoke-WebRequest "$base/admin/candidate?id=$photoCandidateId" -WebSession $admin -UseBasicParsing
Check ($photoDetail.Content.Contains("profile/photo?id=$photoCandidateId") -and $photoDetail.Content.Contains('profile-avatar')) 'admin candidate detail renders larger private photo'
$adminPhotoMatch = [regex]::Match($photoDetail.Content, 'src="([^\"]*profile/photo\?[^\"]+)"')
$adminPhotoUrl = [Net.WebUtility]::HtmlDecode($adminPhotoMatch.Groups[1].Value)
$adminPhoto = Invoke-WebRequest "$origin$adminPhotoUrl" -WebSession $admin -UseBasicParsing
Check ($adminPhoto.StatusCode -eq 200 -and $adminPhoto.Headers['Content-Type'].Contains('image/jpeg') -and $adminPhoto.Headers['Cache-Control'].Contains('private')) 'admin is authorized and photo response uses private caching'
Check ($adminPhotoUrl.Contains('v=') -and -not $adminPhotoUrl.Contains('.jpg')) 'photo URL uses safe cache version and hides storage filename'

$candidateBLogin = Login '+919700000008' 'LifecyclePass12'
$candidateB = $candidateBLogin.Session
$photoIdor = Status { Invoke-WebRequest "$base/profile/photo?id=$photoCandidateId" -WebSession $candidateB -UseBasicParsing }
Check ($photoIdor -eq 404) 'candidate cannot access another candidate photo'
$guestPhotoCode = & curl.exe -sS -o NUL -w '%{http_code}' "$origin$adminPhotoUrl"
Check ($guestPhotoCode -eq '303') 'unauthenticated private photo request is redirected to login'
$fallback = Invoke-WebRequest "$base/admin/candidate?id=8" -WebSession $admin -UseBasicParsing
Check ($fallback.Content.Contains('>CC</span>') -and -not $fallback.Content.Contains('profile/photo?id=8')) 'missing photo uses initials fallback'

$candidateDenied = Status { Invoke-WebRequest "$base/admin/candidate/delete" -WebSession $candidateB -Method Post -Body @{ _token = Token $candidateBLogin.Page.Content; candidate_id = '6'; confirmation_name = 'DELETE' } -UseBasicParsing }
Check ($candidateDenied -eq 403) 'non-admin denied permanent-delete route'
$getDelete = Status { Invoke-WebRequest "$base/admin/candidate/delete" -WebSession $admin -UseBasicParsing }
Check ($getDelete -eq 405) 'GET permanent deletion rejected'
$csrfDelete = Status { Invoke-WebRequest "$base/admin/candidate/delete" -WebSession $admin -Method Post -Body @{ candidate_id = '6'; confirmation_name = 'DELETE' } -UseBasicParsing }
Check ($csrfDelete -eq 419) 'missing CSRF blocks deletion'

$dayCandidate = Login '+919700000009' 'LifecyclePass12'
$daySession = $dayCandidate.Session
$detail = Invoke-WebRequest "$base/admin/candidate?id=9" -WebSession $admin -UseBasicParsing
Check ($detail.Content.Contains('upcoming interview') -and $detail.Content.Contains('Deactivation leaves these bookings')) 'upcoming-interview warning still allows deactivation'
$historyLink = [regex]::Match($detail.Content, 'admin/interview\?id=(\d+)')
Check $historyLink.Success 'candidate history link renders before deletion'
$deactivated = Invoke-WebRequest "$base/admin/candidate/deactivate" -WebSession $admin -Method Post -Body @{ _token = Token $detail.Content; candidate_id = '9' } -UseBasicParsing
Check ($deactivated.Content.Contains('Candidate deactivated') -and $deactivated.Content.Contains('Reactivate candidate')) 'admin deactivates candidate with interview history'
$revoked = Invoke-WebRequest "$base/candidate" -WebSession $daySession -UseBasicParsing
Check $revoked.Content.Contains('Welcome back') 'existing inactive-candidate session loses portal access'
$reactivated = Invoke-WebRequest "$base/admin/candidate/reactivate" -WebSession $admin -Method Post -Body @{ _token = Token $deactivated.Content; candidate_id = '9' } -UseBasicParsing
Check ($reactivated.Content.Contains('Candidate reactivated') -and $reactivated.Content.Contains('Deactivate candidate')) 'admin reactivates candidate without duplication'

$deleteDetail = Invoke-WebRequest "$base/admin/candidate?id=9" -WebSession $admin -UseBasicParsing
Check ($deleteDetail.Content.Contains('Type <strong>DELETE</strong>') -and $deleteDetail.Content.Contains('operational history will be preserved')) 'strong DELETE confirmation dialog renders for candidate with history'
$mismatch = Invoke-WebRequest "$base/admin/candidate/delete" -WebSession $admin -Method Post -Body @{ _token = Token $deleteDetail.Content; candidate_id = '9'; confirmation_name = 'wrong' } -UseBasicParsing
Check ($mismatch.Content.Contains('Type DELETE exactly')) 'backend rejects confirmation mismatch'
$deleted = Invoke-WebRequest "$base/admin/candidate/delete" -WebSession $admin -Method Post -Body @{ _token = Token $mismatch.Content; candidate_id = '9'; confirmation_name = 'DELETE' } -UseBasicParsing
Check $deleted.Content.Contains('Candidate permanently deleted') 'candidate with interview history deleted through POST route'
$missing = Status { Invoke-WebRequest "$base/admin/candidate?id=9" -WebSession $admin -UseBasicParsing }
Check ($missing -eq 404) 'deleted candidate account detail is unavailable'
$historical = Invoke-WebRequest "$base/admin/interview?id=$($historyLink.Groups[1].Value)" -WebSession $admin -UseBasicParsing
Check ($historical.Content.Contains('Today Candidate') -and $historical.Content.Contains('Deleted candidate')) 'deleted candidate interview detail remains readable'
$interviews = Invoke-WebRequest "$base/admin/interviews?search=Today%20Candidate" -WebSession $admin -UseBasicParsing
Check ($interviews.Content.Contains('Today Candidate') -and $interviews.Content.Contains('Deleted candidate')) 'deleted candidate remains in interview directory'
$calendar = Invoke-WebRequest "$base/admin/calendar?date=2038-01-05&view=day" -WebSession $admin -UseBasicParsing
Check ($calendar.Content.Contains('Today Candidate') -and $calendar.Content.Contains('Deleted candidate')) 'deleted candidate remains on admin calendar'

Remove-Item -LiteralPath $temp -Recurse -Force
Write-Output "$checks Candidate lifecycle HTTP checks passed."
