# =====================================================
# KUBERNETES DEPLOYMENT SCRIPT FOR WINDOWS
# Movie Booking System - PowerShell Deployment
# =====================================================

param(
    [Parameter(Position=0)]
    [ValidateSet("deploy", "status", "cleanup", "build")]
    [string]$Action = "deploy"
)

$ErrorActionPreference = "Stop"

# Configuration
$NAMESPACE = "movie-booking"
$SCRIPT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$K8S_DIR = Join-Path $SCRIPT_DIR "k8s"

function Write-ColorOutput($ForegroundColor) {
    $fc = $host.UI.RawUI.ForegroundColor
    $host.UI.RawUI.ForegroundColor = $ForegroundColor
    if ($args) {
        Write-Output $args
    }
    $host.UI.RawUI.ForegroundColor = $fc
}

function Write-Header {
    Write-Host "=========================================" -ForegroundColor Cyan
    Write-Host "  Movie Booking System - K8s Deployment  " -ForegroundColor Cyan
    Write-Host "=========================================" -ForegroundColor Cyan
}

# Check kubectl
function Test-Kubectl {
    try {
        kubectl version --client | Out-Null
        Write-Host "✓ kubectl is installed" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "Error: kubectl is not installed" -ForegroundColor Red
        return $false
    }
}

# Check cluster
function Test-Cluster {
    try {
        kubectl cluster-info | Out-Null
        Write-Host "✓ Connected to Kubernetes cluster" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "Error: Cannot connect to Kubernetes cluster" -ForegroundColor Red
        return $false
    }
}

# Create namespace
function Deploy-Namespace {
    Write-Host "Creating namespace..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\namespace.yaml"
    Write-Host "✓ Namespace created" -ForegroundColor Green
}

# Deploy secrets
function Deploy-Secrets {
    Write-Host "Deploying secrets..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\secrets\" -n $NAMESPACE
    Write-Host "✓ Secrets deployed" -ForegroundColor Green
}

# Deploy configmaps
function Deploy-ConfigMaps {
    Write-Host "Deploying configmaps..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\configmaps\" -n $NAMESPACE
    Write-Host "✓ ConfigMaps deployed" -ForegroundColor Green
}

# Deploy RBAC
function Deploy-RBAC {
    Write-Host "Deploying RBAC..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\rbac\" -n $NAMESPACE
    Write-Host "✓ RBAC deployed" -ForegroundColor Green
}

# Deploy databases
function Deploy-Databases {
    Write-Host "Deploying databases..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\database\" -n $NAMESPACE
    
    Write-Host "Waiting for databases to be ready..." -ForegroundColor Yellow
    kubectl wait --for=condition=ready pod -l app=postgres -n $NAMESPACE --timeout=300s
    kubectl wait --for=condition=ready pod -l app=redis -n $NAMESPACE --timeout=300s
    kubectl wait --for=condition=ready pod -l app=rabbitmq -n $NAMESPACE --timeout=300s
    
    Write-Host "✓ Databases deployed and ready" -ForegroundColor Green
}

# Deploy services
function Deploy-Services {
    Write-Host "Deploying microservices..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\services\" -n $NAMESPACE
    
    Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
    $services = @("auth", "movie", "booking", "payment", "notification")
    foreach ($service in $services) {
        kubectl rollout status deployment/$service-service -n $NAMESPACE --timeout=300s
    }
    
    Write-Host "✓ All microservices deployed" -ForegroundColor Green
}

# Deploy gateway
function Deploy-Gateway {
    Write-Host "Deploying API Gateway..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\gateway\" -n $NAMESPACE
    kubectl rollout status deployment/api-gateway -n $NAMESPACE --timeout=300s
    Write-Host "✓ API Gateway deployed" -ForegroundColor Green
}

# Deploy monitoring
function Deploy-Monitoring {
    Write-Host "Deploying monitoring stack..." -ForegroundColor Yellow
    kubectl apply -f "$K8S_DIR\monitoring\" -n $NAMESPACE
    Write-Host "✓ Monitoring stack deployed" -ForegroundColor Green
}

# Get status
function Get-DeploymentStatus {
    Write-Host "`n=========================================" -ForegroundColor Cyan
    Write-Host "        Deployment Status               " -ForegroundColor Cyan
    Write-Host "=========================================" -ForegroundColor Cyan
    
    Write-Host "`nPods:" -ForegroundColor Yellow
    kubectl get pods -n $NAMESPACE
    
    Write-Host "`nServices:" -ForegroundColor Yellow
    kubectl get svc -n $NAMESPACE
    
    Write-Host "`nDeployments:" -ForegroundColor Yellow
    kubectl get deployments -n $NAMESPACE
}

# Build Docker images
function Build-Images {
    Write-Host "Building Docker images..." -ForegroundColor Yellow
    
    $services = @("auth-service", "movie-service", "booking-service", "payment-service", "notification-service")
    foreach ($service in $services) {
        Write-Host "Building $service..." -ForegroundColor Cyan
        docker build -t "movie-booking/$service`:latest" "$SCRIPT_DIR\services\$service"
    }
    
    Write-Host "✓ All images built" -ForegroundColor Green
}

# Cleanup
function Remove-Deployment {
    Write-Host "Cleaning up deployment..." -ForegroundColor Yellow
    kubectl delete namespace $NAMESPACE --ignore-not-found
    Write-Host "✓ Cleanup complete" -ForegroundColor Green
}

# Main deployment
function Deploy-All {
    if (-not (Test-Kubectl)) { return }
    if (-not (Test-Cluster)) { return }
    
    Deploy-Namespace
    Deploy-Secrets
    Deploy-ConfigMaps
    Deploy-RBAC
    Deploy-Databases
    Deploy-Services
    Deploy-Gateway
    Deploy-Monitoring
    Get-DeploymentStatus
    
    Write-Host "`n=========================================" -ForegroundColor Green
    Write-Host "  Deployment completed successfully!     " -ForegroundColor Green
    Write-Host "=========================================" -ForegroundColor Green
}

# Execute based on action
Write-Header

switch ($Action) {
    "deploy" { Deploy-All }
    "status" { Get-DeploymentStatus }
    "cleanup" { Remove-Deployment }
    "build" { Build-Images }
}
