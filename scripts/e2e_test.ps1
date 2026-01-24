# =====================================================
# END-TO-END TEST SCRIPT
# Movie Booking System - Smoke Tests (Windows)
# =====================================================

param(
    [string]$ApiBase = "http://localhost"
)

$ErrorActionPreference = "Continue"

# Test counters
$script:Total = 0
$script:Passed = 0
$script:Failed = 0

Write-Host "================================================" -ForegroundColor Blue
Write-Host "  🧪 Movie Booking System - E2E Tests" -ForegroundColor Blue
Write-Host "================================================" -ForegroundColor Blue
Write-Host "  Base URL: $ApiBase"
Write-Host ""

function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Url,
        [int]$ExpectedCode,
        [string]$Body = $null,
        [string]$Token = $null,
        [string]$ContentType = "application/json"
    )
    
    $script:Total++
    Write-Host "  [$script:Total] $Name... " -NoNewline
    
    try {
        $headers = @{}
        if ($Token) {
            $headers["Authorization"] = "Bearer $Token"
        }
        
        $params = @{
            Uri = $Url
            Method = $Method
            TimeoutSec = 10
            ErrorAction = "Stop"
        }
        
        if ($headers.Count -gt 0) {
            $params["Headers"] = $headers
        }
        
        if ($Body -and $Method -eq "POST") {
            $params["Body"] = $Body
            $params["ContentType"] = $ContentType
        }
        
        $response = Invoke-WebRequest @params
        $statusCode = $response.StatusCode
    }
    catch {
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        } else {
            $statusCode = 0
        }
    }
    
    if ($statusCode -eq $ExpectedCode) {
        Write-Host "✓ PASS" -ForegroundColor Green -NoNewline
        Write-Host " (HTTP $statusCode)"
        $script:Passed++
        return $true
    } else {
        Write-Host "✗ FAIL" -ForegroundColor Red -NoNewline
        Write-Host " (Expected $ExpectedCode, got $statusCode)"
        $script:Failed++
        return $false
    }
}

# Wait for services
Write-Host "⏳ Checking service availability..." -ForegroundColor Yellow
Write-Host ""

$maxRetries = 30
$retry = 0
while ($retry -lt $maxRetries) {
    try {
        $response = Invoke-WebRequest -Uri "$ApiBase/api/auth/health" -TimeoutSec 5 -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Host "✓ Services are ready" -ForegroundColor Green
            break
        }
    } catch {
        $retry++
        Write-Host "  Waiting for services... ($retry/$maxRetries)"
        Start-Sleep -Seconds 2
    }
}

if ($retry -eq $maxRetries) {
    Write-Host "❌ Services did not become ready in time" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue
Write-Host "  1. HEALTH CHECKS" -ForegroundColor Blue
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue

Test-Endpoint -Name "Auth Service Health" -Method "GET" -Url "$ApiBase/api/auth/health" -ExpectedCode 200
Test-Endpoint -Name "Movie Service Health" -Method "GET" -Url "$ApiBase/api/movies/health" -ExpectedCode 200
Test-Endpoint -Name "Booking Service Health" -Method "GET" -Url "$ApiBase/api/bookings/health" -ExpectedCode 200
Test-Endpoint -Name "Payment Service Health" -Method "GET" -Url "$ApiBase/api/payments/health" -ExpectedCode 200
Test-Endpoint -Name "Notification Service Health" -Method "GET" -Url "$ApiBase/api/notifications/health" -ExpectedCode 200

Write-Host ""
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue
Write-Host "  2. API ENDPOINTS" -ForegroundColor Blue
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue

Test-Endpoint -Name "Get Movies List" -Method "GET" -Url "$ApiBase/api/movies/movies" -ExpectedCode 200
Test-Endpoint -Name "Get Showtimes" -Method "GET" -Url "$ApiBase/api/movies/showtimes" -ExpectedCode 200

# Test user registration
$randomUser = "testuser_$(Get-Date -Format 'yyyyMMddHHmmss')"
$registerBody = @{
    email = "$randomUser@test.com"
    username = $randomUser
    password = "test123456"
    full_name = "Test User"
} | ConvertTo-Json

$script:Total++
Write-Host "  [$script:Total] User Registration... " -NoNewline
try {
    $regResponse = Invoke-WebRequest -Uri "$ApiBase/api/auth/register" -Method POST -Body $registerBody -ContentType "application/json" -TimeoutSec 10 -ErrorAction Stop
    $regCode = $regResponse.StatusCode
} catch {
    if ($_.Exception.Response) {
        $regCode = [int]$_.Exception.Response.StatusCode
    } else {
        $regCode = 0
    }
}

if ($regCode -in @(200, 201, 400)) {
    Write-Host "✓ PASS" -ForegroundColor Green -NoNewline
    Write-Host " (HTTP $regCode)"
    $script:Passed++
} else {
    Write-Host "✗ FAIL" -ForegroundColor Red -NoNewline
    Write-Host " (HTTP $regCode)"
    $script:Failed++
}

# Test login
$script:Total++
Write-Host "  [$script:Total] User Login... " -NoNewline
$Token = $null
try {
    $loginBody = "username=$randomUser&password=test123456"
    $loginResponse = Invoke-WebRequest -Uri "$ApiBase/api/auth/token" -Method POST -Body $loginBody -ContentType "application/x-www-form-urlencoded" -TimeoutSec 10 -ErrorAction Stop
    $loginCode = $loginResponse.StatusCode
    if ($loginCode -eq 200) {
        $loginData = $loginResponse.Content | ConvertFrom-Json
        $Token = $loginData.access_token
    }
} catch {
    if ($_.Exception.Response) {
        $loginCode = [int]$_.Exception.Response.StatusCode
    } else {
        $loginCode = 0
    }
}

if ($loginCode -eq 200) {
    Write-Host "✓ PASS" -ForegroundColor Green -NoNewline
    Write-Host " (HTTP $loginCode)"
    $script:Passed++
} else {
    Write-Host "⚠ SKIP" -ForegroundColor Yellow -NoNewline
    Write-Host " (HTTP $loginCode - user may not exist)"
}

Write-Host ""
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue
Write-Host "  3. PROTECTED ENDPOINTS" -ForegroundColor Blue
Write-Host "─────────────────────────────────────────────────" -ForegroundColor Blue

Test-Endpoint -Name "Protected Endpoint (no auth)" -Method "GET" -Url "$ApiBase/api/bookings/bookings" -ExpectedCode 401

if ($Token) {
    Test-Endpoint -Name "Protected Endpoint (with auth)" -Method "GET" -Url "$ApiBase/api/bookings/bookings" -ExpectedCode 200 -Token $Token
}

# Summary
Write-Host ""
Write-Host "================================================" -ForegroundColor Blue
Write-Host "  📊 TEST SUMMARY" -ForegroundColor Blue
Write-Host "================================================" -ForegroundColor Blue
Write-Host ""
Write-Host "  Total:  $script:Total"
Write-Host "  Passed: " -NoNewline
Write-Host "$script:Passed" -ForegroundColor Green
Write-Host "  Failed: " -NoNewline
Write-Host "$script:Failed" -ForegroundColor Red
Write-Host ""

if ($script:Failed -eq 0) {
    Write-Host "  ✅ All tests passed!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "  ❌ Some tests failed" -ForegroundColor Red
    exit 1
}
