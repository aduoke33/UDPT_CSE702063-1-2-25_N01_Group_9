#!/bin/bash
# =====================================================
# K8S LOCAL HEALTH CHECK SCRIPT
# Verifies all services are running and healthy
# =====================================================

NAMESPACE="${NAMESPACE:-movie-booking-local}"
TIMEOUT="${TIMEOUT:-300}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info() { echo -e "${CYAN}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[✓]${NC} $*"; }
error() { echo -e "${RED}[✗]${NC} $*"; }
waiting() { echo -e "${YELLOW}[...]${NC} $*"; }

echo ""
echo "╔═══════════════════════════════════════════════════════════════════╗"
echo "║         🏥 Movie Booking - Health Check Script 🏥                  ║"
echo "╚═══════════════════════════════════════════════════════════════════╝"
echo ""

# Check if namespace exists
if ! kubectl get namespace "$NAMESPACE" &>/dev/null; then
    error "Namespace $NAMESPACE does not exist!"
    exit 1
fi

info "Checking deployments in namespace: $NAMESPACE"
echo ""

# Get all deployments
DEPLOYMENTS=$(kubectl get deployments -n "$NAMESPACE" -o jsonpath='{.items[*].metadata.name}')

all_healthy=true

for dep in $DEPLOYMENTS; do
    waiting "Checking $dep..."
    
    # Get desired and ready replicas
    DESIRED=$(kubectl get deployment "$dep" -n "$NAMESPACE" -o jsonpath='{.spec.replicas}')
    READY=$(kubectl get deployment "$dep" -n "$NAMESPACE" -o jsonpath='{.status.readyReplicas}')
    READY=${READY:-0}
    
    if [ "$READY" -ge "$DESIRED" ] && [ "$READY" -gt 0 ]; then
        success "$dep: $READY/$DESIRED replicas ready"
    else
        error "$dep: $READY/$DESIRED replicas ready"
        all_healthy=false
        
        # Show pod status
        echo "    Pod status:"
        kubectl get pods -n "$NAMESPACE" -l app="$dep" -o custom-columns="NAME:.metadata.name,STATUS:.status.phase,READY:.status.containerStatuses[0].ready" | tail -n +2 | while read line; do
            echo "      $line"
        done
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check services
info "Checking services..."
echo ""

SERVICES=$(kubectl get services -n "$NAMESPACE" -o jsonpath='{.items[*].metadata.name}')

for svc in $SERVICES; do
    # Get endpoints
    ENDPOINTS=$(kubectl get endpoints "$svc" -n "$NAMESPACE" -o jsonpath='{.subsets[*].addresses[*].ip}' 2>/dev/null)
    
    if [ -n "$ENDPOINTS" ]; then
        success "$svc: Endpoints ready"
    else
        error "$svc: No endpoints"
        all_healthy=false
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# API Health Check
info "Testing API Gateway health endpoint..."
echo ""

# Try to get the API gateway URL
if command -v minikube &>/dev/null; then
    IP=$(minikube ip 2>/dev/null)
    if [ -n "$IP" ]; then
        GATEWAY_URL="http://$IP:30080"
    fi
fi

if [ -z "$GATEWAY_URL" ]; then
    GATEWAY_URL="http://localhost:30080"
fi

# Test health endpoint
if curl -s --connect-timeout 5 "$GATEWAY_URL/health" &>/dev/null; then
    success "API Gateway health check passed at $GATEWAY_URL"
else
    error "API Gateway health check failed at $GATEWAY_URL"
    info "You may need to:"
    info "  - Wait for services to start"
    info "  - Run: kubectl port-forward svc/api-gateway 8080:80 -n $NAMESPACE"
    all_healthy=false
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Summary
echo ""
if [ "$all_healthy" = true ]; then
    echo -e "${GREEN}✅ All services are healthy!${NC}"
    echo ""
    echo "Access the application:"
    echo "  API Gateway: $GATEWAY_URL"
    echo ""
    echo "Quick test:"
    echo "  curl $GATEWAY_URL/health"
    echo "  curl $GATEWAY_URL/api/v1/movies/"
else
    echo -e "${YELLOW}⚠️  Some services have issues. Check the logs:${NC}"
    echo ""
    echo "  kubectl logs -f -l tier=backend -n $NAMESPACE --all-containers"
    echo ""
    echo "Or check specific service:"
    echo "  kubectl logs -f deployment/<service-name> -n $NAMESPACE"
fi

echo ""
