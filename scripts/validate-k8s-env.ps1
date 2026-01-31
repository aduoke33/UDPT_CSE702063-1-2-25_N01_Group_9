# =====================================================
# K8S LOCAL QUICK START - VALIDATION SCRIPT
# Checks all prerequisites before deployment
# =====================================================

param(
    [switch]$Fix,
    [switch]$Verbose
)

$ErrorActionPreference = "Continue"

Write-Host @"

╔═══════════════════════════════════════════════════════════════════╗
║     🎬 Movie Booking System - K8s Environment Validator 🎬        ║
╚═══════════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan

$checks = @{
    passed = @()
    failed = @()
    warnings = @()
}

function Test-Command {
    param([string]$Name)
    return [bool](Get-Command $Name -ErrorAction SilentlyContinue)
}

function Write-Check {
    param([string]$Name, [bool]$Result, [string]$Message = "")
    if ($Result) {
        Write-Host "  ✅ $Name" -ForegroundColor Green
        $script:checks.passed += $Name
    } else {
        Write-Host "  ❌ $Name - $Message" -ForegroundColor Red
        $script:checks.failed += $Name
    }
}

function Write-Warn {
    param([string]$Name, [string]$Message)
    Write-Host "  ⚠️  $Name - $Message" -ForegroundColor Yellow
    $script:checks.warnings += $Name
}

# ===========================================
# 1. Check Required Tools
# ===========================================
Write-Host "`n📦 Checking required tools..." -ForegroundColor Cyan

# Docker
$dockerRunning = $false
if (Test-Command "docker") {
    try {
        $dockerInfo = docker info 2>&1
        $dockerRunning = $LASTEXITCODE -eq 0
    } catch { }
}
Write-Check "Docker" $dockerRunning "Docker not running or not installed"

# kubectl
$kubectlInstalled = Test-Command "kubectl"
Write-Check "kubectl" $kubectlInstalled "Install from: https://kubernetes.io/docs/tasks/tools/"

# Minikube or other K8s
$k8sReady = $false
if (Test-Command "minikube") {
    Write-Check "minikube" $true
    $status = minikube status --format='{{.Host}}' 2>$null
    if ($status -eq "Running") {
        $k8sReady = $true
        Write-Check "Minikube cluster" $true
    } else {
        Write-Warn "Minikube cluster" "Not running. Start with: minikube start"
    }
} elseif (Test-Command "kind") {
    Write-Check "kind" $true
    $clusters = kind get clusters 2>$null
    if ($clusters -match "movie-booking") {
        $k8sReady = $true
        Write-Check "Kind cluster" $true
    } else {
        Write-Warn "Kind cluster" "Not created. Create with: kind create cluster --name movie-booking"
    }
} else {
    Write-Warn "K8s runtime" "No minikube or kind found. Using Docker Desktop?"
    # Check if docker-desktop context exists
    $contexts = kubectl config get-contexts -o name 2>$null
    if ($contexts -match "docker-desktop") {
        $k8sReady = $true
        Write-Check "Docker Desktop K8s" $true
    }
}

# ===========================================
# 2. Check Optional Tools
# ===========================================
Write-Host "`n📦 Checking optional tools..." -ForegroundColor Cyan

if (Test-Command "helm") {
    $helmVersion = helm version --short 2>$null
    Write-Check "Helm" $true
    if ($Verbose) { Write-Host "    Version: $helmVersion" -ForegroundColor Gray }
} else {
    Write-Warn "Helm" "Recommended for easier deployment"
}

if (Test-Command "skaffold") {
    Write-Check "Skaffold" $true
} else {
    Write-Warn "Skaffold" "Optional: for hot-reload development"
}

# ===========================================
# 3. Check Project Structure
# ===========================================
Write-Host "`n📁 Checking project structure..." -ForegroundColor Cyan

$projectRoot = Split-Path $PSScriptRoot -Parent
$requiredFiles = @(
    "helm/movie-booking/Chart.yaml",
    "helm/movie-booking/values.yaml",
    "helm/movie-booking/values-local.yaml",
    "k8s/overlays/local/kustomization.yaml",
    "services/auth-service/Dockerfile",
    "services/movie-service/Dockerfile",
    "services/booking-service/Dockerfile",
    "services/payment-service/Dockerfile",
    "services/notification-service/Dockerfile"
)

foreach ($file in $requiredFiles) {
    $fullPath = Join-Path $projectRoot $file
    $exists = Test-Path $fullPath
    if ($Verbose) {
        Write-Check $file $exists "File not found"
    } elseif (-not $exists) {
        Write-Check $file $exists "File not found"
    }
}
Write-Host "  ✅ Project structure validated" -ForegroundColor Green

# ===========================================
# 4. Check Resources
# ===========================================
Write-Host "`n💻 Checking system resources..." -ForegroundColor Cyan

$mem = Get-CimInstance Win32_ComputerSystem | Select-Object -ExpandProperty TotalPhysicalMemory
$memGB = [math]::Round($mem / 1GB, 2)
$memOk = $memGB -ge 8

Write-Check "RAM (min 8GB)" $memOk "Available: ${memGB}GB"

$cpuCores = (Get-CimInstance Win32_Processor).NumberOfCores
$cpuOk = $cpuCores -ge 4
Write-Check "CPU (min 4 cores)" $cpuOk "Available: $cpuCores cores"

# ===========================================
# 5. Check Docker Images (if minikube running)
# ===========================================
if ($k8sReady -and (Test-Command "minikube")) {
    Write-Host "`n🐳 Checking Docker images..." -ForegroundColor Cyan
    
    # Set minikube docker env
    & minikube -p minikube docker-env --shell powershell | Invoke-Expression
    
    $services = @("auth", "movie", "booking", "payment", "notification")
    foreach ($svc in $services) {
        $imageName = "movie-booking/${svc}-service:local"
        $imageExists = docker images -q $imageName 2>$null
        if ($imageExists) {
            Write-Check "$imageName" $true
        } else {
            Write-Warn "$imageName" "Not built yet. Run: make k8s-local-build"
        }
    }
}

# ===========================================
# Summary
# ===========================================
Write-Host "`n" + "═" * 60 -ForegroundColor Cyan
Write-Host "📊 VALIDATION SUMMARY" -ForegroundColor Cyan
Write-Host "═" * 60 -ForegroundColor Cyan

Write-Host "`n  ✅ Passed:   $($checks.passed.Count)" -ForegroundColor Green
Write-Host "  ❌ Failed:   $($checks.failed.Count)" -ForegroundColor Red
Write-Host "  ⚠️  Warnings: $($checks.warnings.Count)" -ForegroundColor Yellow

if ($checks.failed.Count -eq 0) {
    Write-Host "`n🎉 All checks passed! You can proceed with deployment." -ForegroundColor Green
    Write-Host @"

Next steps:
  1. Start minikube:     minikube start --cpus=4 --memory=8192
  2. Build images:       .\scripts\k8s-local.ps1 build
  3. Deploy:             .\scripts\k8s-local.ps1 deploy
  
Or run everything at once:
  .\scripts\k8s-local.ps1 all

"@ -ForegroundColor Cyan
} else {
    Write-Host "`n⚠️  Please fix the failed checks before proceeding." -ForegroundColor Yellow
    Write-Host "`nFailed checks:" -ForegroundColor Red
    foreach ($item in $checks.failed) {
        Write-Host "  - $item" -ForegroundColor Red
    }
}

Write-Host ""
