# =====================================================
# K8S LOCAL HEALTH CHECK SCRIPT (PowerShell)
# Verifies all services are running and healthy
# =====================================================

param(
    [string]$Namespace = "movie-booking-local",
    [int]$Timeout = 300,
    [switch]$Wait
)

$ErrorActionPreference = "Continue"

function Write-Status {
    param([string]$Status, [string]$Message)
    switch ($Status) {
        "success" { Write-Host "  ✅ $Message" -ForegroundColor Green }
        "error"   { Write-Host "  ❌ $Message" -ForegroundColor Red }
        "warning" { Write-Host "  ⚠️  $Message" -ForegroundColor Yellow }
        "info"    { Write-Host "  ℹ️  $Message" -ForegroundColor Cyan }
        "wait"    { Write-Host "  ⏳ $Message" -ForegroundColor Yellow }
    }
}

Write-Host @"

╔═══════════════════════════════════════════════════════════════════╗
║         🏥 Movie Booking - Health Check Script 🏥                  ║
╚═══════════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan

# Check if namespace exists
$nsExists = kubectl get namespace $Namespace 2>$null
if (-not $nsExists) {
    Write-Host "❌ Namespace $Namespace does not exist!" -ForegroundColor Red
    exit 1
}

Write-Host "📊 Checking deployments in namespace: $Namespace" -ForegroundColor Cyan
Write-Host ""

$allHealthy = $true

# Get all deployments
$deployments = kubectl get deployments -n $Namespace -o json | ConvertFrom-Json

foreach ($dep in $deployments.items) {
    $name = $dep.metadata.name
    $desired = $dep.spec.replicas
    $ready = if ($dep.status.readyReplicas) { $dep.status.readyReplicas } else { 0 }
    
    if ($ready -ge $desired -and $ready -gt 0) {
        Write-Status "success" "$name`: $ready/$desired replicas ready"
    } else {
        Write-Status "error" "$name`: $ready/$desired replicas ready"
        $allHealthy = $false
        
        # Show pod status
        $pods = kubectl get pods -n $Namespace -l app=$name -o json | ConvertFrom-Json
        foreach ($pod in $pods.items) {
            $podName = $pod.metadata.name
            $phase = $pod.status.phase
            $containerReady = if ($pod.status.containerStatuses) { $pod.status.containerStatuses[0].ready } else { $false }
            Write-Host "      → $podName | $phase | Ready: $containerReady" -ForegroundColor Gray
        }
    }
}

Write-Host ""
Write-Host "━" * 60 -ForegroundColor Cyan

# Check services
Write-Host ""
Write-Host "🔌 Checking services..." -ForegroundColor Cyan
Write-Host ""

$services = kubectl get services -n $Namespace -o json | ConvertFrom-Json

foreach ($svc in $services.items) {
    $name = $svc.metadata.name
    $endpoints = kubectl get endpoints $name -n $Namespace -o json 2>$null | ConvertFrom-Json
    
    $hasEndpoints = $endpoints.subsets -and $endpoints.subsets.Count -gt 0
    
    if ($hasEndpoints) {
        Write-Status "success" "$name`: Endpoints ready"
    } else {
        Write-Status "error" "$name`: No endpoints"
        $allHealthy = $false
    }
}

Write-Host ""
Write-Host "━" * 60 -ForegroundColor Cyan

# API Health Check
Write-Host ""
Write-Host "🌐 Testing API Gateway..." -ForegroundColor Cyan
Write-Host ""

# Try to get the API gateway URL
$gatewayUrl = "http://localhost:30080"

try {
    $minikubeIp = minikube ip 2>$null
    if ($minikubeIp) {
        $gatewayUrl = "http://${minikubeIp}:30080"
    }
} catch {}

try {
    $response = Invoke-WebRequest -Uri "$gatewayUrl/health" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    if ($response.StatusCode -eq 200) {
        Write-Status "success" "API Gateway health check passed at $gatewayUrl"
    }
} catch {
    Write-Status "error" "API Gateway health check failed at $gatewayUrl"
    Write-Host ""
    Write-Host "    You may need to:" -ForegroundColor Yellow
    Write-Host "    - Wait for services to start" -ForegroundColor Yellow
    Write-Host "    - Run: kubectl port-forward svc/api-gateway 8080:80 -n $Namespace" -ForegroundColor Yellow
    $allHealthy = $false
}

Write-Host ""
Write-Host "━" * 60 -ForegroundColor Cyan

# Summary
Write-Host ""
if ($allHealthy) {
    Write-Host "✅ All services are healthy!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Access the application:" -ForegroundColor Cyan
    Write-Host "  API Gateway: $gatewayUrl" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Quick test:" -ForegroundColor Cyan
    Write-Host "  curl $gatewayUrl/health" -ForegroundColor Gray
    Write-Host "  curl $gatewayUrl/api/v1/movies/" -ForegroundColor Gray
} else {
    Write-Host "⚠️  Some services have issues. Check the logs:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  kubectl logs -f -l tier=backend -n $Namespace --all-containers" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Or check specific service:" -ForegroundColor Yellow
    Write-Host "  kubectl logs -f deployment/<service-name> -n $Namespace" -ForegroundColor Gray
}

Write-Host ""
