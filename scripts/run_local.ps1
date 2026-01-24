# =====================================================
# LOCAL DEVELOPMENT RUNNER
# Movie Booking System - Run all services locally (Windows)
# =====================================================

param(
    [Parameter(Position=0)]
    [ValidateSet("up", "start", "down", "stop", "restart", "logs", "status", "clean", "help")]
    [string]$Action = "up",
    
    [Parameter(Position=1)]
    [string]$Service = ""
)

$ErrorActionPreference = "Stop"

Write-Host "================================================" -ForegroundColor Blue
Write-Host "  Movie Booking System - Local Runner" -ForegroundColor Blue
Write-Host "================================================" -ForegroundColor Blue
Write-Host ""

# Change to project root
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent $ScriptDir
Set-Location $ProjectRoot

# Check Docker
try {
    docker info 2>&1 | Out-Null
    Write-Host "OK Docker is running" -ForegroundColor Green
} catch {
    Write-Host "ERROR Docker is not running. Please start Docker Desktop." -ForegroundColor Red
    exit 1
}

# Determine compose command
$ComposeCmd = "docker-compose"
try {
    docker compose version 2>&1 | Out-Null
    $ComposeCmd = "docker compose"
} catch {
    # fallback to docker-compose
}

Write-Host "OK Using: $ComposeCmd" -ForegroundColor Green
Write-Host ""

function Show-ServiceUrls {
    Write-Host ""
    Write-Host "================================================" -ForegroundColor Green
    Write-Host "  System is running!" -ForegroundColor Green
    Write-Host "================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "  API Gateway:      " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:80"
    Write-Host "  Auth Service:     " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:8001"
    Write-Host "  Movie Service:    " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:8002"
    Write-Host "  Booking Service:  " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:8003"
    Write-Host "  Payment Service:  " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:8004"
    Write-Host "  Notification:     " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:8005"
    Write-Host ""
    Write-Host "  RabbitMQ UI:      " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:15672 (admin/admin123)"
    Write-Host "  Prometheus:       " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:9090"
    Write-Host "  Grafana:          " -NoNewline -ForegroundColor Blue
    Write-Host "http://localhost:3000 (admin/admin123)"
    Write-Host ""
    Write-Host "  Run " -NoNewline
    Write-Host "scripts\e2e_test.ps1" -ForegroundColor Yellow -NoNewline
    Write-Host " to verify the system"
}

switch ($Action) {
    { $_ -in "up", "start" } {
        Write-Host "Starting all services..." -ForegroundColor Yellow
        Write-Host ""
        
        if ($ComposeCmd -eq "docker compose") {
            docker compose up -d --build
        } else {
            docker-compose up -d --build
        }
        
        Write-Host ""
        Write-Host "Waiting for services to be healthy..." -ForegroundColor Yellow
        Start-Sleep -Seconds 15
        
        Write-Host ""
        Write-Host "Service Status:" -ForegroundColor Blue
        if ($ComposeCmd -eq "docker compose") {
            docker compose ps
        } else {
            docker-compose ps
        }
        
        Show-ServiceUrls
    }
    
    { $_ -in "down", "stop" } {
        Write-Host "Stopping all services..." -ForegroundColor Yellow
        if ($ComposeCmd -eq "docker compose") {
            docker compose down
        } else {
            docker-compose down
        }
        Write-Host "All services stopped" -ForegroundColor Green
    }
    
    "restart" {
        Write-Host "Restarting all services..." -ForegroundColor Yellow
        if ($ComposeCmd -eq "docker compose") {
            docker compose down
            docker compose up -d --build
        } else {
            docker-compose down
            docker-compose up -d --build
        }
        Write-Host "All services restarted" -ForegroundColor Green
        Show-ServiceUrls
    }
    
    "logs" {
        if ($Service) {
            if ($ComposeCmd -eq "docker compose") {
                docker compose logs -f $Service
            } else {
                docker-compose logs -f $Service
            }
        } else {
            if ($ComposeCmd -eq "docker compose") {
                docker compose logs -f
            } else {
                docker-compose logs -f
            }
        }
    }
    
    "status" {
        Write-Host "Service Status:" -ForegroundColor Blue
        if ($ComposeCmd -eq "docker compose") {
            docker compose ps
        } else {
            docker-compose ps
        }
    }
    
    "clean" {
        Write-Host "Cleaning up (removing volumes)..." -ForegroundColor Yellow
        if ($ComposeCmd -eq "docker compose") {
            docker compose down -v --remove-orphans
        } else {
            docker-compose down -v --remove-orphans
        }
        docker system prune -f
        Write-Host "Cleanup complete" -ForegroundColor Green
    }
    
    "help" {
        Write-Host "Usage: .\run_local.ps1 <action> [service]" -ForegroundColor Blue
        Write-Host ""
        Write-Host "Actions:"
        Write-Host "  up, start   - Start all services"
        Write-Host "  down, stop  - Stop all services"
        Write-Host "  restart     - Restart all services"
        Write-Host "  logs [svc]  - View logs (optionally for specific service)"
        Write-Host "  status      - Show service status"
        Write-Host "  clean       - Stop and remove all data"
        Write-Host "  help        - Show this help"
    }
}