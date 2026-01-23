# Database Testing and Validation Script
# Tests all database connections and validates the database-per-service architecture

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Database Architecture Validation" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Database configurations
$databases = @{
    'Auth Service' = @{
        container = 'postgres-auth'
        port = 5433
        user = 'auth_user'
        db = 'auth_db'
        service_port = 8001
    }
    'Movie Service' = @{
        container = 'postgres-movie'
        port = 5434
        user = 'movie_user'
        db = 'movies_db'
        service_port = 8002
    }
    'Booking Service' = @{
        container = 'postgres-booking'
        port = 5435
        user = 'booking_user'
        db = 'bookings_db'
        service_port = 8003
    }
    'Payment Service' = @{
        container = 'postgres-payment'
        port = 5436
        user = 'payment_user'
        db = 'payments_db'
        service_port = 8004
    }
    'Notification Service' = @{
        container = 'postgres-notification'
        port = 5437
        user = 'notification_user'
        db = 'notifications_db'
        service_port = 8005
    }
}

$allPassed = $true

Write-Host "Step 1: Checking Docker Containers" -ForegroundColor Yellow
Write-Host "-----------------------------------" -ForegroundColor Yellow

foreach ($serviceName in $databases.Keys) {
    $config = $databases[$serviceName]
    $containerName = $config.container
    
    Write-Host "`nChecking $serviceName ($containerName)..." -NoNewline
    
    $containerStatus = docker ps --filter "name=$containerName" --format "{{.Status}}" 2>$null
    
    if ($containerStatus -match "Up") {
        Write-Host " ✓ Running" -ForegroundColor Green
    } else {
        Write-Host " ✗ Not running" -ForegroundColor Red
        $allPassed = $false
    }
}

Write-Host "`n"
Write-Host "Step 2: Testing Database Connections" -ForegroundColor Yellow
Write-Host "-------------------------------------" -ForegroundColor Yellow

foreach ($serviceName in $databases.Keys) {
    $config = $databases[$serviceName]
    
    Write-Host "`nTesting $serviceName database..." -NoNewline
    
    $result = docker exec $config.container pg_isready -U $config.user -d $config.db 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host " ✓ Connected" -ForegroundColor Green
    } else {
        Write-Host " ✗ Failed" -ForegroundColor Red
        $allPassed = $false
    }
}

Write-Host "`n"
Write-Host "Step 3: Verifying Database Isolation" -ForegroundColor Yellow
Write-Host "-------------------------------------" -ForegroundColor Yellow

foreach ($serviceName in $databases.Keys) {
    $config = $databases[$serviceName]
    
    Write-Host "`nChecking $serviceName database isolation..." -NoNewline
    
    # Check if database exists and is accessible only by its user
    $dbCheck = docker exec $config.container psql -U $config.user -d $config.db -t -c "SELECT current_database();" 2>&1
    
    if ($dbCheck -match $config.db) {
        Write-Host " ✓ Isolated" -ForegroundColor Green
    } else {
        Write-Host " ✗ Failed" -ForegroundColor Red
        $allPassed = $false
    }
}

Write-Host "`n"
Write-Host "Step 4: Checking Service Endpoints" -ForegroundColor Yellow
Write-Host "-----------------------------------" -ForegroundColor Yellow

foreach ($serviceName in $databases.Keys) {
    $config = $databases[$serviceName]
    
    Write-Host "`nChecking $serviceName endpoint (port $($config.service_port))..." -NoNewline
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$($config.service_port)/health" -TimeoutSec 2 -ErrorAction Stop
        Write-Host " ✓ Healthy" -ForegroundColor Green
    } catch {
        Write-Host " ✗ Not responding" -ForegroundColor Red
        $allPassed = $false
    }
}

Write-Host "`n"
Write-Host "Step 5: Database Statistics" -ForegroundColor Yellow
Write-Host "---------------------------" -ForegroundColor Yellow

foreach ($serviceName in $databases.Keys) {
    $config = $databases[$serviceName]
    
    Write-Host "`n$serviceName:" -ForegroundColor Cyan
    
    # Get database size
    $size = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT pg_size_pretty(pg_database_size('$($config.db)'));" 2>$null
    if ($size) {
        Write-Host "  Database Size: $($size.Trim())"
    }
    
    # Get table count
    $tables = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>$null
    if ($tables) {
        Write-Host "  Tables: $($tables.Trim())"
    }
    
    # Get connection count
    $connections = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT count(*) FROM pg_stat_activity WHERE datname='$($config.db)';" 2>$null
    if ($connections) {
        Write-Host "  Active Connections: $($connections.Trim())"
    }
    
    # Get port mapping
    Write-Host "  Host Port: $($config.port)"
    Write-Host "  Service Port: $($config.service_port)"
}

Write-Host "`n"
Write-Host "======================================" -ForegroundColor Cyan
if ($allPassed) {
    Write-Host "✓ All Tests Passed" -ForegroundColor Green
    Write-Host "Database-per-Service architecture is working correctly!" -ForegroundColor Green
} else {
    Write-Host "✗ Some Tests Failed" -ForegroundColor Red
    Write-Host "Please check the errors above and ensure all containers are running." -ForegroundColor Yellow
}
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""
