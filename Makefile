# Kubernetes Deployment Scripts
# =====================================================
# MAKEFILE - Automation for Movie Booking System
# =====================================================

.PHONY: help build push deploy test clean logs

# Variables
DOCKER_REGISTRY ?= ghcr.io
DOCKER_REPO ?= your-org/movie-booking
VERSION ?= latest

help: ## Display this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# =====================================================
# Docker Commands
# =====================================================

build: ## Build all Docker images
	@echo "Building Docker images..."
	docker-compose build

build-service: ## Build a specific service (usage: make build-service SERVICE=auth)
	@echo "Building $(SERVICE) service..."
	docker build -t $(DOCKER_REGISTRY)/$(DOCKER_REPO)/$(SERVICE):$(VERSION) services/$(SERVICE)

push: ## Push all images to registry
	@echo "Pushing Docker images..."
	docker-compose push

push-service: ## Push a specific service (usage: make push-service SERVICE=auth)
	@echo "Pushing $(SERVICE) service..."
	docker push $(DOCKER_REGISTRY)/$(DOCKER_REPO)/$(SERVICE):$(VERSION)

# =====================================================
# Docker Compose Commands
# =====================================================

up: ## Start all services with docker-compose
	@echo "Starting services..."
	docker-compose up -d --build

down: ## Stop all services
	@echo "Stopping services..."
	docker-compose down

restart: ## Restart all services
	@echo "Restarting services..."
	docker-compose restart

logs: ## View logs for all services
	docker-compose logs -f

logs-service: ## View logs for specific service (usage: make logs-service SERVICE=auth)
	docker-compose logs -f $(SERVICE)-service

ps: ## List running containers
	docker-compose ps

# =====================================================
# Kubernetes Commands
# =====================================================

k8s-namespace: ## Create Kubernetes namespace
	kubectl apply -f k8s/namespace.yaml

k8s-secrets: ## Apply Kubernetes secrets
	kubectl apply -f k8s/secrets/

k8s-config: ## Apply Kubernetes configmaps
	kubectl apply -f k8s/configmaps/

k8s-database: ## Deploy database services
	kubectl apply -f k8s/database/

k8s-services: ## Deploy application services
	kubectl apply -f k8s/services/

k8s-gateway: ## Deploy API gateway
	kubectl apply -f k8s/gateway/

k8s-monitoring: ## Deploy monitoring stack
	kubectl apply -f k8s/monitoring/

k8s-rbac: ## Apply RBAC configuration
	kubectl apply -f k8s/rbac/

k8s-deploy: k8s-namespace k8s-secrets k8s-config k8s-database ## Full Kubernetes deployment
	@echo "Waiting for database services..."
	sleep 30
	$(MAKE) k8s-rbac
	$(MAKE) k8s-services
	$(MAKE) k8s-gateway
	$(MAKE) k8s-monitoring
	@echo "Deployment complete!"

k8s-delete: ## Delete all Kubernetes resources
	kubectl delete namespace movie-booking --ignore-not-found=true

k8s-status: ## Check Kubernetes deployment status
	kubectl get all -n movie-booking

k8s-pods: ## List all pods
	kubectl get pods -n movie-booking -o wide

k8s-logs: ## View logs for a pod (usage: make k8s-logs POD=auth-service-xxx)
	kubectl logs -f $(POD) -n movie-booking

k8s-describe: ## Describe a pod (usage: make k8s-describe POD=auth-service-xxx)
	kubectl describe pod $(POD) -n movie-booking

k8s-exec: ## Execute command in pod (usage: make k8s-exec POD=auth-service-xxx CMD="bash")
	kubectl exec -it $(POD) -n movie-booking -- $(CMD)

# =====================================================
# Helm Commands
# =====================================================

helm-install: ## Install Helm chart
	helm install movie-booking ./helm/movie-booking -n movie-booking --create-namespace

helm-upgrade: ## Upgrade Helm release
	helm upgrade movie-booking ./helm/movie-booking -n movie-booking

helm-uninstall: ## Uninstall Helm release
	helm uninstall movie-booking -n movie-booking

helm-template: ## Template Helm chart (dry-run)
	helm template movie-booking ./helm/movie-booking

# =====================================================
# Local K8s Commands (minikube/kind/docker-desktop)
# =====================================================

k8s-local-start: ## Start local Kubernetes cluster (minikube)
	@echo "Starting minikube cluster..."
	minikube start --cpus=4 --memory=8192 --driver=docker
	minikube addons enable ingress
	minikube addons enable metrics-server
	@echo "Cluster started! Run 'eval \$$(minikube docker-env)' to use minikube's Docker daemon"

k8s-local-stop: ## Stop local Kubernetes cluster
	minikube stop

k8s-local-build: ## Build images for local K8s
	@echo "Building images for local K8s..."
	eval $$(minikube docker-env) && \
	docker build -t movie-booking/auth-service:local services/auth-service && \
	docker build -t movie-booking/movie-service:local services/movie-service && \
	docker build -t movie-booking/booking-service:local services/booking-service && \
	docker build -t movie-booking/payment-service:local services/payment-service && \
	docker build -t movie-booking/notification-service:local services/notification-service
	@echo "All images built successfully!"

k8s-local-deploy: ## Deploy to local K8s with Helm
	@echo "Deploying to local Kubernetes..."
	helm upgrade --install movie-booking ./helm/movie-booking \
		-f ./helm/movie-booking/values-local.yaml \
		-n movie-booking-local \
		--create-namespace \
		--wait \
		--timeout 10m
	@echo "Deployment complete!"

k8s-local-all: k8s-local-start k8s-local-build k8s-local-deploy ## Full local K8s setup
	@echo ""
	@echo "======================================"
	@echo "  Local K8s deployment complete! 🎉"
	@echo "======================================"
	@echo ""
	@echo "Access the API Gateway:"
	@echo "  minikube service api-gateway -n movie-booking-local"
	@echo ""
	@echo "Or port-forward:"
	@echo "  kubectl port-forward svc/api-gateway 8080:80 -n movie-booking-local"
	@echo ""

k8s-local-status: ## Check local K8s deployment status
	kubectl get all -n movie-booking-local

k8s-local-logs: ## View logs for local K8s services
	kubectl logs -f -l tier=backend -n movie-booking-local --all-containers

k8s-local-clean: ## Clean local K8s resources
	helm uninstall movie-booking -n movie-booking-local || true
	kubectl delete namespace movie-booking-local --ignore-not-found=true

k8s-local-dashboard: ## Open minikube dashboard
	minikube dashboard

k8s-local-tunnel: ## Create minikube tunnel for LoadBalancer services
	minikube tunnel

# =====================================================
# Kustomize Commands
# =====================================================

kustomize-local: ## Deploy using Kustomize (local overlay)
	kubectl apply -k k8s/overlays/local

kustomize-staging: ## Deploy using Kustomize (staging overlay)
	kubectl apply -k k8s/overlays/staging

kustomize-local-delete: ## Delete Kustomize local deployment
	kubectl delete -k k8s/overlays/local

# =====================================================
# Skaffold Commands
# =====================================================

skaffold-dev: ## Start Skaffold development mode
	skaffold dev -p local

skaffold-run: ## Deploy with Skaffold
	skaffold run -p local

skaffold-delete: ## Delete Skaffold deployment
	skaffold delete -p local

# =====================================================
# Testing Commands
# =====================================================

test: ## Run all tests
	@echo "Running tests..."
	./test_api.sh

test-unit: ## Run unit tests for all services
	@for service in auth movie booking payment notification; do \
		echo "Testing $$service-service..."; \
		cd services/$$service-service && python -m pytest tests/ -v || exit 1; \
		cd ../..; \
	done

test-integration: ## Run integration tests
	@echo "Running integration tests..."
	./test_api_full.sh

lint: ## Run linting for Python services
	@for service in auth movie booking payment notification; do \
		echo "Linting $$service-service..."; \
		cd services/$$service-service && flake8 app/ || exit 1; \
		cd ../..; \
	done

format: ## Format Python code
	@for service in auth movie booking payment notification; do \
		echo "Formatting $$service-service..."; \
		cd services/$$service-service && black app/ && isort app/; \
		cd ../..; \
	done

# =====================================================
# Cleanup Commands
# =====================================================

clean: ## Clean all generated files and containers
	@echo "Cleaning up..."
	docker-compose down -v --remove-orphans
	docker system prune -f

clean-images: ## Remove all project images
	docker images | grep movie-booking | awk '{print $$3}' | xargs -r docker rmi -f

clean-volumes: ## Remove all project volumes
	docker volume ls | grep movie-booking | awk '{print $$2}' | xargs -r docker volume rm

clean-all: clean clean-images clean-volumes ## Complete cleanup

# =====================================================
# Monitoring Commands
# =====================================================

prometheus: ## Open Prometheus UI
	@echo "Opening Prometheus at http://localhost:9090"
	@start http://localhost:9090 2>/dev/null || open http://localhost:9090 2>/dev/null || xdg-open http://localhost:9090

grafana: ## Open Grafana UI
	@echo "Opening Grafana at http://localhost:3000"
	@start http://localhost:3000 2>/dev/null || open http://localhost:3000 2>/dev/null || xdg-open http://localhost:3000

# =====================================================
# Development Commands
# =====================================================

dev: ## Start development environment
	docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d

dev-logs: ## View development logs
	docker-compose -f docker-compose.yml -f docker-compose.dev.yml logs -f

shell: ## Open shell in a service container (usage: make shell SERVICE=auth)
	docker-compose exec $(SERVICE)-service sh

db-shell: ## Open PostgreSQL shell
	docker-compose exec postgres psql -U movie_user -d movie_booking

redis-cli: ## Open Redis CLI
	docker-compose exec redis redis-cli

rabbitmq-mgmt: ## Open RabbitMQ Management UI
	@echo "Opening RabbitMQ at http://localhost:15672"
	@start http://localhost:15672 2>/dev/null || open http://localhost:15672 2>/dev/null || xdg-open http://localhost:15672

# =====================================================
# Documentation
# =====================================================

docs: ## Generate API documentation
	@echo "API Documentation available at:"
	@echo "  - Auth Service:    http://localhost:8001/docs"
	@echo "  - Movie Service:   http://localhost:8002/docs"
	@echo "  - Booking Service: http://localhost:8003/docs"
	@echo "  - Payment Service: http://localhost:8004/docs"
	@echo "  - Notification:    http://localhost:8005/docs"
