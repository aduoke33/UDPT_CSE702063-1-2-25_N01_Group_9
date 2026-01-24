#!/bin/bash
# =====================================================
# LOCAL DEVELOPMENT RUNNER
# Movie Booking System - Run all services locally
# =====================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  🎬 Movie Booking System - Local Runner${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Change to project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

# Check Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

if ! docker info &> /dev/null; then
    echo -e "${RED}❌ Docker daemon is not running. Please start Docker.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker is running${NC}"

# Check docker-compose
if command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
elif docker compose version &> /dev/null; then
    COMPOSE_CMD="docker compose"
else
    echo -e "${RED}❌ docker-compose is not installed.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Using: $COMPOSE_CMD${NC}"
echo ""

# Parse arguments
ACTION="${1:-up}"

case "$ACTION" in
    up|start)
        echo -e "${YELLOW}🚀 Starting all services...${NC}"
        echo ""
        $COMPOSE_CMD up -d --build
        
        echo ""
        echo -e "${YELLOW}⏳ Waiting for services to be healthy...${NC}"
        sleep 10
        
        # Check health
        echo ""
        echo -e "${BLUE}📊 Service Status:${NC}"
        $COMPOSE_CMD ps
        
        echo ""
        echo -e "${GREEN}================================================${NC}"
        echo -e "${GREEN}  ✅ System is running!${NC}"
        echo -e "${GREEN}================================================${NC}"
        echo ""
        echo -e "  ${BLUE}API Gateway:${NC}      http://localhost:80"
        echo -e "  ${BLUE}Auth Service:${NC}     http://localhost:8001"
        echo -e "  ${BLUE}Movie Service:${NC}    http://localhost:8002"
        echo -e "  ${BLUE}Booking Service:${NC}  http://localhost:8003"
        echo -e "  ${BLUE}Payment Service:${NC}  http://localhost:8004"
        echo -e "  ${BLUE}Notification:${NC}     http://localhost:8005"
        echo ""
        echo -e "  ${BLUE}RabbitMQ UI:${NC}      http://localhost:15672 (admin/admin123)"
        echo -e "  ${BLUE}Prometheus:${NC}       http://localhost:9090"
        echo -e "  ${BLUE}Grafana:${NC}          http://localhost:3000 (admin/admin123)"
        echo ""
        echo -e "  Run ${YELLOW}./scripts/e2e_test.sh${NC} to verify the system"
        ;;
        
    down|stop)
        echo -e "${YELLOW}🛑 Stopping all services...${NC}"
        $COMPOSE_CMD down
        echo -e "${GREEN}✅ All services stopped${NC}"
        ;;
        
    restart)
        echo -e "${YELLOW}🔄 Restarting all services...${NC}"
        $COMPOSE_CMD down
        $COMPOSE_CMD up -d --build
        echo -e "${GREEN}✅ All services restarted${NC}"
        ;;
        
    logs)
        SERVICE="${2:-}"
        if [ -n "$SERVICE" ]; then
            $COMPOSE_CMD logs -f "$SERVICE"
        else
            $COMPOSE_CMD logs -f
        fi
        ;;
        
    status)
        echo -e "${BLUE}📊 Service Status:${NC}"
        $COMPOSE_CMD ps
        ;;
        
    clean)
        echo -e "${YELLOW}🧹 Cleaning up (removing volumes)...${NC}"
        $COMPOSE_CMD down -v --remove-orphans
        docker system prune -f
        echo -e "${GREEN}✅ Cleanup complete${NC}"
        ;;
        
    *)
        echo -e "${BLUE}Usage:${NC} $0 {up|down|restart|logs|status|clean}"
        echo ""
        echo "  up, start   - Start all services"
        echo "  down, stop  - Stop all services"
        echo "  restart     - Restart all services"
        echo "  logs [svc]  - View logs (optionally for specific service)"
        echo "  status      - Show service status"
        echo "  clean       - Stop and remove all data"
        exit 1
        ;;
esac
