# =====================================================
# LOCAL KUBERNETES DEPLOYMENT SCRIPT (PowerShell)
# Movie Booking System - Automated K8s Setup
# Compatible with: minikube, kind, Docker Desktop
# =====================================================

param(
    [Parameter(Position=0)]
    [ValidateSet("start", "stop", "restart", "status", "logs", "clean", "build", "deploy", "all", "help")]
    [string]$Action = "help",
    
    [Parameter()]
    [ValidateSet("minikube", "kind", "docker-desktop")]
    [string]$Driver = "minikube",
    
    [Parameter()]
    [ValidateSet("helm", "kustomize", "skaffold")]
    [string]$DeployMethod = "helm",
    
    [Parameter()]
    [string]$Service = "",
    
    [Parameter()]
    [switch]$Force,
    
    [Parameter()]
    [switch]$Verbose
)

$ErrorActionPreference = "Stop"
$NAMESPACE = "movie-booking-local"
$PROJECT_ROOT = $PSScriptRoot | Split-Path -Parent
$HELM_RELEASE = "movie-booking"

# Colors for output
function Write-Info { Write-Host "[INFO] $args" -ForegroundColor Cyan }
function Write-Success { Write-Host "[SUCCESS] $args" -ForegroundColor Green }
function Write-Warning { Write-Host "[WARNING] $args" -ForegroundColor Yellow }
function Write-Error { Write-Host "[ERROR] $args" -ForegroundColor Red }

function Show-Help {
    Write-Host @"
=====================================================
  Movie Booking - Local K8s Deployment Script
=====================================================

USAGE:
    .\k8s-local.ps1 <action> [options]

ACTIONS:
    start       Start the local Kubernetes cluster
    stop        Stop the local Kubernetes cluster
    restart     Restart the cluster
    status      Show cluster and deployment status
    logs        View logs for services
    clean       Clean up all resources
    build       Build Docker images
    deploy      Deploy to Kubernetes
    all         Build and deploy everything
    help        Show this help message

OPTIONS:
    -Driver <minikube|kind|docker-desktop>
                Kubernetes driver to use (default: minikube)
    
    -DeployMethod <helm|kustomize|skaffold>
                Deployment method (default: helm)
    
    -Service <name>
                Specific service for logs/restart
    
    -Force      Force rebuild/redeploy
    -Verbose    Show detailed output

EXAMPLES:
    .\k8s-local.ps1 start -Driver minikube
    .\k8s-local.ps1 all -DeployMethod helm
    .\k8s-local.ps1 logs -Service auth-service
    .\k8s-local.ps1 clean -Force
"@
}

function Test-Prerequisites {
    Write-Info "Checking prerequisites..."
    
    $required = @("docker", "kubectl")
    $optional = @{}
    
    switch ($Driver) {
        "minikube" { $required += "minikube" }
        "kind" { $required += "kind" }
    }
    
    switch ($DeployMethod) {
        "helm" { $required += "helm" }
        "skaffold" { $required += "skaffold" }
    }
    
    foreach ($cmd in $required) {
        if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
            Write-Error "$cmd is required but not installed!"
            exit 1
        }
    }
    
    Write-Success "All prerequisites met"
}

function Start-Cluster {
    Write-Info "Starting Kubernetes cluster with $Driver..."
    
    switch ($Driver) {
        "minikube" {
            # Check if minikube is already running
            $status = minikube status --format='{{.Host}}' 2>$null
            if ($status -eq "Running") {
                Write-Info "Minikube is already running"
            } else {
                Write-Info "Starting minikube..."
                minikube start `
                    --driver=docker `
                    --cpus=4 `
                    --memory=8192 `
                    --disk-size=30g `
                    --kubernetes-version=v1.28.0 `
                    --addons=ingress,metrics-server,dashboard
                
                Write-Info "Enabling ingress addon..."
                minikube addons enable ingress
            }
            
            # Set docker env for local builds
            Write-Info "Configuring Docker environment..."
            & minikube -p minikube docker-env --shell powershell | Invoke-Expression
        }
        
        "kind" {
            $clusters = kind get clusters 2>$null
            if ($clusters -contains "movie-booking") {
                Write-Info "Kind cluster 'movie-booking' already exists"
            } else {
                Write-Info "Creating kind cluster..."
                
                # Create kind config
                $kindConfig = @"
kind: Cluster
apiVersion: kind.x-k8s.io/v1alpha4
nodes:
- role: control-plane
  kubeadmConfigPatches:
  - |
    kind: InitConfiguration
    nodeRegistration:
      kubeletExtraArgs:
        node-labels: "ingress-ready=true"
  extraPortMappings:
  - containerPort: 80
    hostPort: 80
    protocol: TCP
  - containerPort: 443
    hostPort: 443
    protocol: TCP
  - containerPort: 30080
    hostPort: 30080
    protocol: TCP
- role: worker
- role: worker
"@
                $kindConfig | Out-File -FilePath "$env:TEMP\kind-config.yaml" -Encoding utf8
                kind create cluster --name movie-booking --config "$env:TEMP\kind-config.yaml"
            }
        }
        
        "docker-desktop" {
            Write-Info "Using Docker Desktop Kubernetes..."
            Write-Info "Please ensure Kubernetes is enabled in Docker Desktop settings"
            kubectl config use-context docker-desktop
        }
    }
    
    Write-Info "Waiting for cluster to be ready..."
    kubectl wait --for=condition=Ready nodes --all --timeout=300s
    Write-Success "Kubernetes cluster is ready!"
}

function Stop-Cluster {
    Write-Info "Stopping Kubernetes cluster..."
    
    switch ($Driver) {
        "minikube" { minikube stop }
        "kind" { kind delete cluster --name movie-booking }
        "docker-desktop" { Write-Warning "Docker Desktop cluster cannot be stopped via script" }
    }
    
    Write-Success "Cluster stopped"
}

function Build-Images {
    Write-Info "Building Docker images..."
    
    Push-Location $PROJECT_ROOT
    
    $services = @(
        @{Name="auth-service"; Path="services/auth-service"},
        @{Name="movie-service"; Path="services/movie-service"},
        @{Name="booking-service"; Path="services/booking-service"},
        @{Name="payment-service"; Path="services/payment-service"},
        @{Name="notification-service"; Path="services/notification-service"}
    )
    
    # Configure docker to use minikube's daemon if using minikube
    if ($Driver -eq "minikube") {
        & minikube -p minikube docker-env --shell powershell | Invoke-Expression
    }
    
    foreach ($svc in $services) {
        if ($Service -and $Service -ne $svc.Name) { continue }
        
        Write-Info "Building $($svc.Name)..."
        $imageName = "movie-booking/$($svc.Name):local"
        
        if (Test-Path "$($svc.Path)/Dockerfile") {
            docker build -t $imageName -f "$($svc.Path)/Dockerfile" $svc.Path
            
            # Load image to kind if using kind
            if ($Driver -eq "kind") {
                kind load docker-image $imageName --name movie-booking
            }
        } else {
            Write-Warning "Dockerfile not found for $($svc.Name)"
        }
    }
    
    Pop-Location
    Write-Success "All images built successfully!"
}

function Deploy-Application {
    Write-Info "Deploying application using $DeployMethod..."
    
    Push-Location $PROJECT_ROOT
    
    # Create namespace if not exists
    kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -
    
    switch ($DeployMethod) {
        "helm" {
            # Update Helm dependencies
            Write-Info "Updating Helm dependencies..."
            helm dependency update helm/movie-booking 2>$null
            
            # Install or upgrade release
            Write-Info "Installing/Upgrading Helm release..."
            
            if ($Force) {
                helm uninstall $HELM_RELEASE -n $NAMESPACE 2>$null
                Start-Sleep -Seconds 5
            }
            
            helm upgrade --install $HELM_RELEASE ./helm/movie-booking `
                -f ./helm/movie-booking/values-local.yaml `
                -n $NAMESPACE `
                --create-namespace `
                --wait `
                --timeout 10m
        }
        
        "kustomize" {
            Write-Info "Applying Kustomize overlay..."
            kubectl apply -k k8s/overlays/local
        }
        
        "skaffold" {
            Write-Info "Running Skaffold..."
            skaffold run -p dev --default-repo=""
        }
    }
    
    Pop-Location
    
    Write-Info "Waiting for deployments to be ready..."
    $deployments = kubectl get deployments -n $NAMESPACE -o jsonpath='{.items[*].metadata.name}'
    foreach ($dep in $deployments.Split(" ")) {
        if ($dep) {
            Write-Info "Waiting for $dep..."
            kubectl rollout status deployment/$dep -n $NAMESPACE --timeout=300s
        }
    }
    
    Write-Success "Application deployed successfully!"
    Show-Status
}

function Show-Status {
    Write-Host ""
    Write-Host "====================================================="-ForegroundColor Cyan
    Write-Host "  DEPLOYMENT STATUS" -ForegroundColor Cyan
    Write-Host "=====================================================" -ForegroundColor Cyan
    Write-Host ""
    
    Write-Info "Pods:"
    kubectl get pods -n $NAMESPACE -o wide
    
    Write-Host ""
    Write-Info "Services:"
    kubectl get services -n $NAMESPACE
    
    Write-Host ""
    Write-Info "Deployments:"
    kubectl get deployments -n $NAMESPACE
    
    # Get access URL
    Write-Host ""
    Write-Host "====================================================="-ForegroundColor Green
    Write-Host "  ACCESS URLS" -ForegroundColor Green
    Write-Host "=====================================================" -ForegroundColor Green
    
    switch ($Driver) {
        "minikube" {
            $ip = minikube ip
            Write-Host "  API Gateway: http://${ip}:30080" -ForegroundColor Yellow
            Write-Host "  Minikube Dashboard: minikube dashboard" -ForegroundColor Yellow
        }
        "kind" {
            Write-Host "  API Gateway: http://localhost:30080" -ForegroundColor Yellow
        }
        "docker-desktop" {
            Write-Host "  API Gateway: http://localhost:30080" -ForegroundColor Yellow
        }
    }
    Write-Host ""
}

function Show-Logs {
    if ($Service) {
        Write-Info "Showing logs for $Service..."
        kubectl logs -f -l app=$Service -n $NAMESPACE --all-containers --tail=100
    } else {
        Write-Info "Available services:"
        kubectl get pods -n $NAMESPACE -o jsonpath='{.items[*].metadata.labels.app}' | 
            ForEach-Object { $_.Split(" ") | Sort-Object -Unique }
        Write-Host ""
        Write-Info "Use -Service <name> to view specific service logs"
    }
}

function Clean-Resources {
    Write-Warning "This will delete all resources in namespace $NAMESPACE"
    
    if (-not $Force) {
        $confirm = Read-Host "Are you sure? (y/N)"
        if ($confirm -ne "y") {
            Write-Info "Aborted"
            return
        }
    }
    
    Write-Info "Cleaning up resources..."
    
    switch ($DeployMethod) {
        "helm" {
            helm uninstall $HELM_RELEASE -n $NAMESPACE 2>$null
        }
        "kustomize" {
            kubectl delete -k k8s/overlays/local 2>$null
        }
        "skaffold" {
            skaffold delete -p dev 2>$null
        }
    }
    
    kubectl delete namespace $NAMESPACE --ignore-not-found
    
    Write-Success "Cleanup completed"
}

# Main execution
Test-Prerequisites

switch ($Action) {
    "start" { Start-Cluster }
    "stop" { Stop-Cluster }
    "restart" { Stop-Cluster; Start-Cluster }
    "status" { Show-Status }
    "logs" { Show-Logs }
    "clean" { Clean-Resources }
    "build" { Build-Images }
    "deploy" { Deploy-Application }
    "all" { 
        Start-Cluster
        Build-Images
        Deploy-Application 
    }
    "help" { Show-Help }
    default { Show-Help }
}
