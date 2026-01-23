# =====================================================
# Database Management Scripts (PowerShell)
# Movie Booking System - Database Per Service
# =====================================================

param(
    [Parameter(Position=0)]
    [string]$Command,
    
    [Parameter(Position=1)]
    [string]$Service,
    
    [Parameter(Position=2)]
    [string]$BackupFile
)

# Database configurations
$databases = @{
    'auth' = @{
        container = 'postgres-auth'
        port = 5433
        user = 'auth_user'
        password = 'auth_password123'
        db = 'auth_db'
    }
    'movie' = @{
        container = 'postgres-movie'
        port = 5434
        user = 'movie_user'
        password = 'movie_password123'
        db = 'movies_db'
    }
    'booking' = @{
        container = 'postgres-booking'
        port = 5435
        user = 'booking_user'
        password = 'booking_password123'
        db = 'bookings_db'
    }
    'payment' = @{
        container = 'postgres-payment'
        port = 5436
        user = 'payment_user'
        password = 'payment_password123'
        db = 'payments_db'
    }
    'notification' = @{
        container = 'postgres-notification'
        port = 5437
        user = 'notification_user'
        password = 'notification_password123'
        db = 'notifications_db'
    }
}

function Write-Header {
    param([string]$Text)
    
    Write-Host ""
    Write-Host "================================" -ForegroundColor Green
    Write-Host $Text -ForegroundColor Green
    Write-Host "================================" -ForegroundColor Green
    Write-Host ""
}

function Check-AllDatabases {
    Write-Header "Checking All Databases"
    
    foreach ($serviceName in $databases.Keys) {
        $config = $databases[$serviceName]
        
        Write-Host "`nChecking $serviceName database..." -ForegroundColor Yellow
        
        $result = docker exec $config.container pg_isready -U $config.user 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ $serviceName database is ready" -ForegroundColor Green
        } else {
            Write-Host "✗ $serviceName database is NOT ready" -ForegroundColor Red
        }
    }
}

function Backup-Database {
    param([string]$ServiceName)
    
    if (-not $ServiceName) {
        Write-Host "Usage: .\db-manager.ps1 backup <service>" -ForegroundColor Red
        Write-Host "Available services: auth, movie, booking, payment, notification"
        return
    }
    
    if (-not $databases.ContainsKey($ServiceName)) {
        Write-Host "Unknown service: $ServiceName" -ForegroundColor Red
        return
    }
    
    $config = $databases[$ServiceName]
    
    Write-Header "Backing up $ServiceName database"
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupFile = "backup_$($config.db)_$timestamp.sql"
    
    Write-Host "Creating backup: $backupFile"
    
    docker exec $config.container pg_dump -U $config.user $config.db | Out-File -FilePath $backupFile -Encoding utf8
    
    Write-Host "✓ Backup completed: $backupFile" -ForegroundColor Green
}

function Backup-AllDatabases {
    Write-Header "Backing up All Databases"
    
    foreach ($serviceName in $databases.Keys) {
        Backup-Database -ServiceName $serviceName
    }
}

function Restore-Database {
    param(
        [string]$ServiceName,
        [string]$BackupFilePath
    )
    
    if (-not $ServiceName -or -not $BackupFilePath) {
        Write-Host "Usage: .\db-manager.ps1 restore <service> <backup_file>" -ForegroundColor Red
        return
    }
    
    if (-not $databases.ContainsKey($ServiceName)) {
        Write-Host "Unknown service: $ServiceName" -ForegroundColor Red
        return
    }
    
    if (-not (Test-Path $BackupFilePath)) {
        Write-Host "Backup file not found: $BackupFilePath" -ForegroundColor Red
        return
    }
    
    $config = $databases[$ServiceName]
    
    Write-Header "Restoring $ServiceName database from $BackupFilePath"
    
    Write-Host "WARNING: This will overwrite the existing database!" -ForegroundColor Yellow
    $confirmation = Read-Host "Are you sure? (yes/no)"
    
    if ($confirmation -eq 'yes') {
        Get-Content $BackupFilePath | docker exec -i $config.container psql -U $config.user $config.db
        Write-Host "✓ Restore completed" -ForegroundColor Green
    } else {
        Write-Host "Restore cancelled"
    }
}

function Connect-ToDatabase {
    param([string]$ServiceName)
    
    if (-not $ServiceName) {
        Write-Host "Usage: .\db-manager.ps1 connect <service>" -ForegroundColor Red
        Write-Host "Available services: auth, movie, booking, payment, notification"
        return
    }
    
    if (-not $databases.ContainsKey($ServiceName)) {
        Write-Host "Unknown service: $ServiceName" -ForegroundColor Red
        return
    }
    
    $config = $databases[$ServiceName]
    
    Write-Header "Connecting to $ServiceName database"
    
    docker exec -it $config.container psql -U $config.user $config.db
}

function Show-DatabaseStats {
    Write-Header "Database Statistics"
    
    foreach ($serviceName in $databases.Keys) {
        $config = $databases[$serviceName]
        
        Write-Host "`n=== $serviceName Database ===" -ForegroundColor Yellow
        
        # Get database size
        $size = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT pg_size_pretty(pg_database_size('$($config.db)'));" 2>$null
        if ($size) {
            Write-Host "Size: $($size.Trim())"
        }
        
        # Get table count
        $tables = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>$null
        if ($tables) {
            Write-Host "Tables: $($tables.Trim())"
        }
        
        # Get connection count
        $connections = docker exec $config.container psql -U $config.user $config.db -t -c "SELECT count(*) FROM pg_stat_activity WHERE datname='$($config.db)';" 2>$null
        if ($connections) {
            Write-Host "Active connections: $($connections.Trim())"
        }
    }
}

function Reset-Database {
    param([string]$ServiceName)
    
    if (-not $ServiceName) {
        Write-Host "Usage: .\db-manager.ps1 reset <service>" -ForegroundColor Red
        Write-Host "Available services: auth, movie, booking, payment, notification"
        return
    }
    
    if (-not $databases.ContainsKey($ServiceName)) {
        Write-Host "Unknown service: $ServiceName" -ForegroundColor Red
        return
    }
    
    $config = $databases[$ServiceName]
    
    Write-Header "Resetting $ServiceName database"
    
    Write-Host "WARNING: This will DELETE ALL DATA in $ServiceName database!" -ForegroundColor Red
    $confirmation = Read-Host "Are you sure? Type 'DELETE' to confirm"
    
    if ($confirmation -eq 'DELETE') {
        docker exec $config.container psql -U $config.user -c "DROP DATABASE IF EXISTS $($config.db);"
        docker exec $config.container psql -U $config.user -c "CREATE DATABASE $($config.db);"
        Write-Host "✓ Database reset completed" -ForegroundColor Green
    } else {
        Write-Host "Reset cancelled"
    }
}

function Show-Help {
    @"
Database Management Tool for Movie Booking System

Usage: .\db-manager.ps1 <command> [options]

Commands:
  check                     - Check status of all databases
  stats                     - Show database statistics
  backup <service>          - Backup a specific database
  backup-all                - Backup all databases
  restore <service> <file>  - Restore a database from backup
  connect <service>         - Connect to a database via psql
  reset <service>           - Reset a database (DELETE ALL DATA)
  help                      - Show this help message

Services:
  auth, movie, booking, payment, notification

Examples:
  .\db-manager.ps1 check
  .\db-manager.ps1 backup auth
  .\db-manager.ps1 backup-all
  .\db-manager.ps1 restore auth backup_auth_db_20260123_120000.sql
  .\db-manager.ps1 connect booking
  .\db-manager.ps1 stats
  .\db-manager.ps1 reset payment

"@
}

# Main script logic
switch ($Command) {
    'check' {
        Check-AllDatabases
    }
    'stats' {
        Show-DatabaseStats
    }
    'backup' {
        Backup-Database -ServiceName $Service
    }
    'backup-all' {
        Backup-AllDatabases
    }
    'restore' {
        Restore-Database -ServiceName $Service -BackupFilePath $BackupFile
    }
    'connect' {
        Connect-ToDatabase -ServiceName $Service
    }
    'reset' {
        Reset-Database -ServiceName $Service
    }
    default {
        Show-Help
    }
}
