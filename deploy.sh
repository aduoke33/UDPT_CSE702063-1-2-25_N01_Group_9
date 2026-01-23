#!/bin/bash
# =====================================================
# KUBERNETES DEPLOYMENT SCRIPT
# Movie Booking System - Automated Deployment
# =====================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="movie-booking"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K8S_DIR="${SCRIPT_DIR}/k8s"

echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}  Movie Booking System - K8s Deployment  ${NC}"
echo -e "${BLUE}=========================================${NC}"

# Function to check if kubectl is installed
check_kubectl() {
    if ! command -v kubectl &> /dev/null; then
        echo -e "${RED}Error: kubectl is not installed${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ kubectl is installed${NC}"
}

# Function to check cluster connectivity
check_cluster() {
    if ! kubectl cluster-info &> /dev/null; then
        echo -e "${RED}Error: Cannot connect to Kubernetes cluster${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Connected to Kubernetes cluster${NC}"
}

# Function to create namespace
create_namespace() {
    echo -e "${YELLOW}Creating namespace...${NC}"
    kubectl apply -f ${K8S_DIR}/namespace.yaml
    echo -e "${GREEN}✓ Namespace created${NC}"
}

# Function to deploy secrets
deploy_secrets() {
    echo -e "${YELLOW}Deploying secrets...${NC}"
    kubectl apply -f ${K8S_DIR}/secrets/ -n ${NAMESPACE}
    echo -e "${GREEN}✓ Secrets deployed${NC}"
}

# Function to deploy configmaps
deploy_configmaps() {
    echo -e "${YELLOW}Deploying configmaps...${NC}"
    kubectl apply -f ${K8S_DIR}/configmaps/ -n ${NAMESPACE}
    echo -e "${GREEN}✓ ConfigMaps deployed${NC}"
}

# Function to deploy RBAC
deploy_rbac() {
    echo -e "${YELLOW}Deploying RBAC...${NC}"
    kubectl apply -f ${K8S_DIR}/rbac/ -n ${NAMESPACE}
    echo -e "${GREEN}✓ RBAC deployed${NC}"
}

# Function to deploy databases
deploy_databases() {
    echo -e "${YELLOW}Deploying databases...${NC}"
    kubectl apply -f ${K8S_DIR}/database/ -n ${NAMESPACE}
    
    echo -e "${YELLOW}Waiting for databases to be ready...${NC}"
    kubectl wait --for=condition=ready pod -l app=postgres -n ${NAMESPACE} --timeout=300s
    kubectl wait --for=condition=ready pod -l app=redis -n ${NAMESPACE} --timeout=300s
    kubectl wait --for=condition=ready pod -l app=rabbitmq -n ${NAMESPACE} --timeout=300s
    
    echo -e "${GREEN}✓ Databases deployed and ready${NC}"
}

# Function to deploy services
deploy_services() {
    echo -e "${YELLOW}Deploying microservices...${NC}"
    kubectl apply -f ${K8S_DIR}/services/ -n ${NAMESPACE}
    
    echo -e "${YELLOW}Waiting for services to be ready...${NC}"
    for service in auth movie booking payment notification; do
        kubectl rollout status deployment/${service}-service -n ${NAMESPACE} --timeout=300s
    done
    
    echo -e "${GREEN}✓ All microservices deployed${NC}"
}

# Function to deploy gateway
deploy_gateway() {
    echo -e "${YELLOW}Deploying API Gateway...${NC}"
    kubectl apply -f ${K8S_DIR}/gateway/ -n ${NAMESPACE}
    kubectl rollout status deployment/api-gateway -n ${NAMESPACE} --timeout=300s
    echo -e "${GREEN}✓ API Gateway deployed${NC}"
}

# Function to deploy monitoring
deploy_monitoring() {
    echo -e "${YELLOW}Deploying monitoring stack...${NC}"
    kubectl apply -f ${K8S_DIR}/monitoring/ -n ${NAMESPACE}
    echo -e "${GREEN}✓ Monitoring stack deployed${NC}"
}

# Function to get deployment status
get_status() {
    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}        Deployment Status               ${NC}"
    echo -e "${BLUE}=========================================${NC}"
    
    echo -e "\n${YELLOW}Pods:${NC}"
    kubectl get pods -n ${NAMESPACE}
    
    echo -e "\n${YELLOW}Services:${NC}"
    kubectl get svc -n ${NAMESPACE}
    
    echo -e "\n${YELLOW}Deployments:${NC}"
    kubectl get deployments -n ${NAMESPACE}
    
    # Get API Gateway external IP
    GATEWAY_IP=$(kubectl get svc api-gateway -n ${NAMESPACE} -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
    echo -e "\n${GREEN}API Gateway IP: ${GATEWAY_IP}${NC}"
}

# Function to cleanup
cleanup() {
    echo -e "${YELLOW}Cleaning up deployment...${NC}"
    kubectl delete namespace ${NAMESPACE} --ignore-not-found
    echo -e "${GREEN}✓ Cleanup complete${NC}"
}

# Main deployment function
deploy_all() {
    check_kubectl
    check_cluster
    create_namespace
    deploy_secrets
    deploy_configmaps
    deploy_rbac
    deploy_databases
    deploy_services
    deploy_gateway
    deploy_monitoring
    get_status
    
    echo -e "\n${GREEN}=========================================${NC}"
    echo -e "${GREEN}  Deployment completed successfully!     ${NC}"
    echo -e "${GREEN}=========================================${NC}"
}

# Parse command line arguments
case "${1:-deploy}" in
    deploy)
        deploy_all
        ;;
    status)
        get_status
        ;;
    cleanup)
        cleanup
        ;;
    *)
        echo "Usage: $0 {deploy|status|cleanup}"
        exit 1
        ;;
esac
