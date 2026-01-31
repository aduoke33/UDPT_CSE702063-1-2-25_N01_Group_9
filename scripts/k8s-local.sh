#!/bin/bash
# =====================================================
# LOCAL KUBERNETES DEPLOYMENT SCRIPT (Bash)
# Movie Booking System - Automated K8s Setup
# Compatible with: minikube, kind, Docker Desktop
# =====================================================

set -e

# Configuration
NAMESPACE="movie-booking-local"
HELM_RELEASE="movie-booking"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRIVER="${DRIVER:-minikube}"
DEPLOY_METHOD="${DEPLOY_METHOD:-helm}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Logging functions
info() { echo -e "${CYAN}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[SUCCESS]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARNING]${NC} $*"; }
error() { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

show_help() {
    cat << EOF
=====================================================
  Movie Booking - Local K8s Deployment Script
=====================================================

USAGE:
    ./k8s-local.sh <action> [options]

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

ENVIRONMENT VARIABLES:
    DRIVER          Kubernetes driver (minikube|kind|docker-desktop)
                    Default: minikube
    
    DEPLOY_METHOD   Deployment method (helm|kustomize|skaffold)
                    Default: helm
    
    SERVICE         Specific service for logs/restart

EXAMPLES:
    DRIVER=minikube ./k8s-local.sh start
    DEPLOY_METHOD=helm ./k8s-local.sh all
    SERVICE=auth-service ./k8s-local.sh logs
    ./k8s-local.sh clean
EOF
}

check_prerequisites() {
    info "Checking prerequisites..."
    
    local required=("docker" "kubectl")
    
    case "$DRIVER" in
        minikube) required+=("minikube") ;;
        kind) required+=("kind") ;;
    esac
    
    case "$DEPLOY_METHOD" in
        helm) required+=("helm") ;;
        skaffold) required+=("skaffold") ;;
    esac
    
    for cmd in "${required[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            error "$cmd is required but not installed!"
        fi
    done
    
    success "All prerequisites met"
}

start_cluster() {
    info "Starting Kubernetes cluster with $DRIVER..."
    
    case "$DRIVER" in
        minikube)
            # Check if minikube is already running
            if minikube status --format='{{.Host}}' 2>/dev/null | grep -q "Running"; then
                info "Minikube is already running"
            else
                info "Starting minikube..."
                minikube start \
                    --driver=docker \
                    --cpus=4 \
                    --memory=8192 \
                    --disk-size=30g \
                    --kubernetes-version=v1.28.0 \
                    --addons=ingress,metrics-server,dashboard
                
                info "Enabling ingress addon..."
                minikube addons enable ingress
            fi
            
            # Set docker env for local builds
            info "Configuring Docker environment..."
            eval $(minikube -p minikube docker-env)
            ;;
            
        kind)
            if kind get clusters 2>/dev/null | grep -q "movie-booking"; then
                info "Kind cluster 'movie-booking' already exists"
            else
                info "Creating kind cluster..."
                
                cat > /tmp/kind-config.yaml << 'KINDEOF'
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
KINDEOF
                kind create cluster --name movie-booking --config /tmp/kind-config.yaml
            fi
            ;;
            
        docker-desktop)
            info "Using Docker Desktop Kubernetes..."
            info "Please ensure Kubernetes is enabled in Docker Desktop settings"
            kubectl config use-context docker-desktop
            ;;
    esac
    
    info "Waiting for cluster to be ready..."
    kubectl wait --for=condition=Ready nodes --all --timeout=300s
    success "Kubernetes cluster is ready!"
}

stop_cluster() {
    info "Stopping Kubernetes cluster..."
    
    case "$DRIVER" in
        minikube) minikube stop ;;
        kind) kind delete cluster --name movie-booking ;;
        docker-desktop) warn "Docker Desktop cluster cannot be stopped via script" ;;
    esac
    
    success "Cluster stopped"
}

build_images() {
    info "Building Docker images..."
    
    cd "$PROJECT_ROOT"
    
    services=(
        "auth-service:services/auth-service"
        "movie-service:services/movie-service"
        "booking-service:services/booking-service"
        "payment-service:services/payment-service"
        "notification-service:services/notification-service"
    )
    
    # Configure docker to use minikube's daemon if using minikube
    if [ "$DRIVER" = "minikube" ]; then
        eval $(minikube -p minikube docker-env)
    fi
    
    for svc_info in "${services[@]}"; do
        name="${svc_info%%:*}"
        path="${svc_info#*:}"
        
        if [ -n "$SERVICE" ] && [ "$SERVICE" != "$name" ]; then
            continue
        fi
        
        info "Building $name..."
        image_name="movie-booking/${name}:local"
        
        if [ -f "$path/Dockerfile" ]; then
            docker build -t "$image_name" -f "$path/Dockerfile" "$path"
            
            # Load image to kind if using kind
            if [ "$DRIVER" = "kind" ]; then
                kind load docker-image "$image_name" --name movie-booking
            fi
        else
            warn "Dockerfile not found for $name"
        fi
    done
    
    success "All images built successfully!"
}

deploy_application() {
    info "Deploying application using $DEPLOY_METHOD..."
    
    cd "$PROJECT_ROOT"
    
    # Create namespace if not exists
    kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -
    
    case "$DEPLOY_METHOD" in
        helm)
            # Update Helm dependencies
            info "Updating Helm dependencies..."
            helm dependency update helm/movie-booking 2>/dev/null || true
            
            # Install or upgrade release
            info "Installing/Upgrading Helm release..."
            
            helm upgrade --install "$HELM_RELEASE" ./helm/movie-booking \
                -f ./helm/movie-booking/values-local.yaml \
                -n "$NAMESPACE" \
                --create-namespace \
                --wait \
                --timeout 10m
            ;;
            
        kustomize)
            info "Applying Kustomize overlay..."
            kubectl apply -k k8s/overlays/local
            ;;
            
        skaffold)
            info "Running Skaffold..."
            skaffold run -p dev --default-repo=""
            ;;
    esac
    
    info "Waiting for deployments to be ready..."
    for dep in $(kubectl get deployments -n "$NAMESPACE" -o jsonpath='{.items[*].metadata.name}'); do
        if [ -n "$dep" ]; then
            info "Waiting for $dep..."
            kubectl rollout status "deployment/$dep" -n "$NAMESPACE" --timeout=300s
        fi
    done
    
    success "Application deployed successfully!"
    show_status
}

show_status() {
    echo ""
    echo -e "${CYAN}=====================================================${NC}"
    echo -e "${CYAN}  DEPLOYMENT STATUS${NC}"
    echo -e "${CYAN}=====================================================${NC}"
    echo ""
    
    info "Pods:"
    kubectl get pods -n "$NAMESPACE" -o wide
    
    echo ""
    info "Services:"
    kubectl get services -n "$NAMESPACE"
    
    echo ""
    info "Deployments:"
    kubectl get deployments -n "$NAMESPACE"
    
    # Get access URL
    echo ""
    echo -e "${GREEN}=====================================================${NC}"
    echo -e "${GREEN}  ACCESS URLS${NC}"
    echo -e "${GREEN}=====================================================${NC}"
    
    case "$DRIVER" in
        minikube)
            ip=$(minikube ip)
            echo -e "${YELLOW}  API Gateway: http://${ip}:30080${NC}"
            echo -e "${YELLOW}  Minikube Dashboard: minikube dashboard${NC}"
            ;;
        kind|docker-desktop)
            echo -e "${YELLOW}  API Gateway: http://localhost:30080${NC}"
            ;;
    esac
    echo ""
}

show_logs() {
    if [ -n "$SERVICE" ]; then
        info "Showing logs for $SERVICE..."
        kubectl logs -f -l "app=$SERVICE" -n "$NAMESPACE" --all-containers --tail=100
    else
        info "Available services:"
        kubectl get pods -n "$NAMESPACE" -o jsonpath='{.items[*].metadata.labels.app}' | tr ' ' '\n' | sort -u
        echo ""
        info "Use SERVICE=<name> ./k8s-local.sh logs to view specific service logs"
    fi
}

clean_resources() {
    warn "This will delete all resources in namespace $NAMESPACE"
    
    if [ "$FORCE" != "true" ]; then
        read -p "Are you sure? (y/N) " confirm
        if [ "$confirm" != "y" ]; then
            info "Aborted"
            return
        fi
    fi
    
    info "Cleaning up resources..."
    
    case "$DEPLOY_METHOD" in
        helm) helm uninstall "$HELM_RELEASE" -n "$NAMESPACE" 2>/dev/null || true ;;
        kustomize) kubectl delete -k k8s/overlays/local 2>/dev/null || true ;;
        skaffold) skaffold delete -p dev 2>/dev/null || true ;;
    esac
    
    kubectl delete namespace "$NAMESPACE" --ignore-not-found
    
    success "Cleanup completed"
}

# Main execution
ACTION="${1:-help}"

check_prerequisites

case "$ACTION" in
    start) start_cluster ;;
    stop) stop_cluster ;;
    restart) stop_cluster; start_cluster ;;
    status) show_status ;;
    logs) show_logs ;;
    clean) clean_resources ;;
    build) build_images ;;
    deploy) deploy_application ;;
    all)
        start_cluster
        build_images
        deploy_application
        ;;
    help) show_help ;;
    *) show_help ;;
esac
