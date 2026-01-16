# PowerShell API Testing Script for Movie Booking System

$API_BASE = "http://localhost"
$AUTH_SERVICE = "$API_BASE/api/auth"
$MOVIE_SERVICE = "$API_BASE/api/movies"
$BOOKING_SERVICE = "$API_BASE/api/bookings"
$PAYMENT_SERVICE = "$API_BASE/api/payments"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "Movie Booking System - API Test Suite" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Register User
Write-Host "1️⃣  Registering new user..." -ForegroundColor Yellow
$registerBody = @{
    email = "testuser@example.com"
    username = "testuser"
    password = "test123456"
    full_name = "Test User"
    phone_number = "0123456789"
} | ConvertTo-Json

$registerResponse = Invoke-RestMethod -Uri "$AUTH_SERVICE/register" -Method Post -Body $registerBody -ContentType "application/json" -ErrorAction SilentlyContinue
Write-Host "Register Response: $($registerResponse | ConvertTo-Json)" -ForegroundColor Green
Write-Host ""

# 2. Login
Write-Host "2️⃣  Logging in..." -ForegroundColor Yellow
$loginBody = @{
    username = "testuser"
    password = "test123456"
}

$loginResponse = Invoke-RestMethod -Uri "$AUTH_SERVICE/token" -Method Post -Body $loginBody -ContentType "application/x-www-form-urlencoded"
$token = $loginResponse.access_token

Write-Host "Login successful!" -ForegroundColor Green
Write-Host "Access Token: $token" -ForegroundColor Gray
Write-Host ""

# 3. Get Movies
Write-Host "3️⃣  Getting movies list..." -ForegroundColor Yellow
$movies = Invoke-RestMethod -Uri "$MOVIE_SERVICE/movies" -Method Get
Write-Host "Found $($movies.Count) movies" -ForegroundColor Green
$movieId = $movies[0].id
Write-Host "Selected Movie: $($movies[0].title) (ID: $movieId)" -ForegroundColor Gray
Write-Host ""

# 4. Get Showtimes
Write-Host "4️⃣  Getting showtimes..." -ForegroundColor Yellow
$showtimes = Invoke-RestMethod -Uri "$MOVIE_SERVICE/showtimes" -Method Get
Write-Host "Found $($showtimes.Count) showtimes" -ForegroundColor Green
if ($showtimes.Count -gt 0) {
    $showtimeId = $showtimes[0].id
    Write-Host "Selected Showtime ID: $showtimeId" -ForegroundColor Gray
    Write-Host "Price: `$$($showtimes[0].price)" -ForegroundColor Gray
}
Write-Host ""

# 5. Create Booking
Write-Host "5️⃣  Creating booking..." -ForegroundColor Yellow
$bookingBody = @{
    showtime_id = $showtimeId
    seat_ids = @(
        "11111111-1111-1111-1111-111111111111",
        "22222222-2222-2222-2222-222222222222"
    )
} | ConvertTo-Json

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

try {
    $booking = Invoke-RestMethod -Uri "$BOOKING_SERVICE/book" -Method Post -Body $bookingBody -Headers $headers
    Write-Host "Booking created successfully!" -ForegroundColor Green
    Write-Host "Booking Code: $($booking.booking_code)" -ForegroundColor Gray
    Write-Host "Total Amount: `$$($booking.total_price)" -ForegroundColor Gray
    $bookingId = $booking.id
    $bookingAmount = $booking.total_price
} catch {
    Write-Host "Booking failed: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# 6. Get User's Bookings
Write-Host "6️⃣  Getting user bookings..." -ForegroundColor Yellow
$userBookings = Invoke-RestMethod -Uri "$BOOKING_SERVICE/bookings" -Method Get -Headers $headers
Write-Host "User has $($userBookings.Count) booking(s)" -ForegroundColor Green
Write-Host ""

# 7. Process Payment
if ($bookingId) {
    Write-Host "7️⃣  Processing payment..." -ForegroundColor Yellow
    $paymentBody = @{
        booking_id = $bookingId
        payment_method = "credit_card"
        amount = $bookingAmount
    } | ConvertTo-Json

    try {
        $payment = Invoke-RestMethod -Uri "$PAYMENT_SERVICE/process" -Method Post -Body $paymentBody -Headers $headers
        Write-Host "Payment processed successfully!" -ForegroundColor Green
        Write-Host "Transaction ID: $($payment.transaction_id)" -ForegroundColor Gray
        Write-Host "Status: $($payment.status)" -ForegroundColor Gray
    } catch {
        Write-Host "Payment failed: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

# 8. Get Payment History
Write-Host "8️⃣  Getting payment history..." -ForegroundColor Yellow
try {
    $payments = Invoke-RestMethod -Uri "$PAYMENT_SERVICE/payments" -Method Get -Headers $headers
    Write-Host "Found $($payments.Count) payment(s)" -ForegroundColor Green
} catch {
    Write-Host "Could not retrieve payments: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# 9. Verify Token
Write-Host "9️⃣  Verifying authentication token..." -ForegroundColor Yellow
$verifyResponse = Invoke-RestMethod -Uri "$AUTH_SERVICE/verify" -Method Get -Headers $headers
Write-Host "Token verified for user: $($verifyResponse.username)" -ForegroundColor Green
Write-Host ""

# 10. Health Checks
Write-Host "🏥 Service Health Checks..." -ForegroundColor Yellow
try {
    $authHealth = Invoke-RestMethod -Uri "$AUTH_SERVICE/health" -Method Get
    Write-Host "✅ Auth Service: $($authHealth.status)" -ForegroundColor Green
} catch { Write-Host "❌ Auth Service: Down" -ForegroundColor Red }

try {
    $movieHealth = Invoke-RestMethod -Uri "$MOVIE_SERVICE/health" -Method Get
    Write-Host "✅ Movie Service: $($movieHealth.status)" -ForegroundColor Green
} catch { Write-Host "❌ Movie Service: Down" -ForegroundColor Red }

try {
    $bookingHealth = Invoke-RestMethod -Uri "$BOOKING_SERVICE/health" -Method Get
    Write-Host "✅ Booking Service: $($bookingHealth.status)" -ForegroundColor Green
} catch { Write-Host "❌ Booking Service: Down" -ForegroundColor Red }

try {
    $paymentHealth = Invoke-RestMethod -Uri "$PAYMENT_SERVICE/health" -Method Get
    Write-Host "✅ Payment Service: $($paymentHealth.status)" -ForegroundColor Green
} catch { Write-Host "❌ Payment Service: Down" -ForegroundColor Red }

Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "✅ API Testing Complete!" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
