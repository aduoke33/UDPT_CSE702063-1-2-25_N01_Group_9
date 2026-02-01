# =====================================================
# K8S DEMO SCRIPT - PowerShell
# Quick demo của các tính năng Kubernetes
# =====================================================

param(
    [Parameter()]
    [ValidateSet('setup', 'autoscaling', 'selfhealing', 'monitoring', 'all')]
    [string]$Demo = 'all'
)

$ErrorActionPreference = "Stop"
$NAMESPACE = "movie-booking"

function Write-DemoHeader {
    param([string]$Title)
    Write-Host "`n" -NoNewline
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host "  $Title" -ForegroundColor Yellow
    Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host ""
}

function Wait-ForUser {
    Write-Host "`nPress any key to continue..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

function Demo-Setup {
    Write-DemoHeader "SETUP: Kiểm tra môi trường K8s"
    
    Write-Host "✓ Checking kubectl..." -ForegroundColor Green
    kubectl version --client --short
    
    Write-Host "`n✓ Checking cluster..." -ForegroundColor Green
    kubectl cluster-info
    
    Write-Host "`n✓ Checking namespace..." -ForegroundColor Green
    kubectl get namespace $NAMESPACE 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Creating namespace..." -ForegroundColor Yellow
        kubectl create namespace $NAMESPACE
    }
    
    Write-Host "`n✓ Checking pods..." -ForegroundColor Green
    kubectl get pods -n $NAMESPACE
    
    Wait-ForUser
}

function Demo-AutoScaling {
    Write-DemoHeader "DEMO 1: Horizontal Pod Autoscaling"
    
    Write-Host "📊 Current HPA status:" -ForegroundColor Cyan
    kubectl get hpa -n $NAMESPACE
    
    Write-Host "`n📊 Current pods:" -ForegroundColor Cyan
    kubectl get pods -n $NAMESPACE -l app=booking-service
    
    Write-Host "`n🚀 Starting load test..." -ForegroundColor Yellow
    Write-Host "This will generate high CPU load to trigger autoscaling" -ForegroundColor Gray
    
    Wait-ForUser
    
    # Start monitoring in background
    Write-Host "`n📈 Monitoring HPA (Ctrl+C to stop)..." -ForegroundColor Cyan
    Write-Host "Open another terminal and run:" -ForegroundColor Gray
    Write-Host "  k6 run tests/k6-load-test.js" -ForegroundColor White
    Write-Host "`nWatching pods scale up..." -ForegroundColor Yellow
    
    kubectl get hpa -n $NAMESPACE -w
}

function Demo-SelfHealing {
    Write-DemoHeader "DEMO 2: Self-Healing"
    
    Write-Host "📋 Current auth-service pods:" -ForegroundColor Cyan
    kubectl get pods -n $NAMESPACE -l app=auth-service
    
    $POD_NAME = kubectl get pods -n $NAMESPACE -l app=auth-service -o jsonpath='{.items[0].metadata.name}'
    
    Write-Host "`n💀 Deleting pod: $POD_NAME" -ForegroundColor Red
    Write-Host "Watch Kubernetes automatically recreate it..." -ForegroundColor Gray
    
    Wait-ForUser
    
    kubectl delete pod $POD_NAME -n $NAMESPACE
    
    Write-Host "`n🔄 Watching pod recreation..." -ForegroundColor Yellow
    Start-Sleep -Seconds 2
    kubectl get pods -n $NAMESPACE -l app=auth-service -w
}

function Demo-Monitoring {
    Write-DemoHeader "DEMO 3: Monitoring with Grafana"
    
    Write-Host "📊 Starting Grafana port-forward..." -ForegroundColor Cyan
    Write-Host "Grafana will be available at: http://localhost:3000" -ForegroundColor Green
    Write-Host "Default credentials: admin / admin" -ForegroundColor Gray
    
    Write-Host "`n📈 Starting Prometheus port-forward..." -ForegroundColor Cyan
    Write-Host "Prometheus will be available at: http://localhost:9090" -ForegroundColor Green
    
    Wait-ForUser
    
    Write-Host "`nStarting port-forwards..." -ForegroundColor Yellow
    Write-Host "Keep this terminal open!" -ForegroundColor Red
    
    # Port-forward in background jobs
    Start-Job -ScriptBlock {
        kubectl port-forward -n movie-booking svc/grafana 3000:3000
    } | Out-Null
    
    Start-Job -ScriptBlock {
        kubectl port-forward -n movie-booking svc/prometheus 9090:9090
    } | Out-Null
    
    Start-Sleep -Seconds 3
    
    Write-Host "`n✅ Port-forwards started!" -ForegroundColor Green
    Write-Host "  Grafana:    http://localhost:3000" -ForegroundColor Cyan
    Write-Host "  Prometheus: http://localhost:9090" -ForegroundColor Cyan
    
    Write-Host "`nOpening browsers..." -ForegroundColor Yellow
    Start-Process "http://localhost:3000"
    Start-Process "http://localhost:9090"
    
    Write-Host "`nPress Ctrl+C to stop port-forwards" -ForegroundColor Gray
    Wait-Event -Timeout 3600
}

function Demo-All {
    Demo-Setup
    Demo-AutoScaling
    Demo-SelfHealing
    Demo-Monitoring
}

# Main execution
try {
    Write-Host "╔═══════════════════════════════════════════╗" -ForegroundColor Magenta
    Write-Host "║   KUBERNETES FEATURES DEMO               ║" -ForegroundColor Magenta
    Write-Host "║   Movie Booking System                   ║" -ForegroundColor Magenta
    Write-Host "╚═══════════════════════════════════════════╝" -ForegroundColor Magenta
    
    switch ($Demo) {
        'setup'       { Demo-Setup }
        'autoscaling' { Demo-AutoScaling }
        'selfhealing' { Demo-SelfHealing }
        'monitoring'  { Demo-Monitoring }
        'all'         { Demo-All }
    }
    
    Write-Host "`n✅ Demo completed!" -ForegroundColor Green
}
catch {
    Write-Host "`n❌ Error: $_" -ForegroundColor Red
    exit 1
}
finally {
    # Cleanup background jobs
    Get-Job | Stop-Job
    Get-Job | Remove-Job
}
