# System Architecture

## Movie Booking Distributed System - Technical Architecture Documentation

---

## Table of Contents

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Service Components](#service-components)
- [Data Architecture](#data-architecture)
- [Communication Patterns](#communication-patterns)
- [Technology Stack](#technology-stack)
- [Security Architecture](#security-architecture)
- [Deployment Architecture](#deployment-architecture)

---

## Overview

The Movie Booking System is a distributed application built using microservices architecture. The system demonstrates key distributed systems concepts including service decomposition, database-per-service pattern, API gateway, asynchronous messaging, and distributed transactions.

**Key Characteristics:**

- **Scalability**: Each service can scale independently based on load
- **Resilience**: Failure isolation prevents cascading failures
- **Maintainability**: Independent deployment and development cycles
- **Technology Diversity**: Each service optimized for its specific needs

---

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Client Layer                            │
│  (Web Browser / Mobile App / External API Consumers)            │
└────────────────────────┬────────────────────────────────────────┘
                         │ HTTPS
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                      API Gateway (NGINX)                        │
│  - Request Routing                                              │
│  - Load Balancing                                               │
│  - SSL Termination                                              │
│  - Rate Limiting                                                │
└─────┬─────────┬─────────┬─────────┬─────────┬───────────────────┘
      │         │         │         │         │
      │ HTTP    │         │         │         │
      │         │         │         │         │
┌─────▼─────┐ ┌─▼──────┐ ┌▼──────┐ ┌▼──────┐ ┌▼───────────┐
│   Auth    │ │ Movie  │ │Booking│ │Payment│ │Notification│
│  Service  │ │Service │ │Service│ │Service│ │  Service   │
│           │ │        │ │       │ │       │ │            │
│  :8001    │ │ :8002  │ │ :8003 │ │ :8004 │ │   :8005    │
└─────┬─────┘ └─┬──────┘ └┬──────┘ └┬──────┘ └┬───────────┘
      │         │         │         │         │
      │         │         │         │         │
┌─────▼─────────▼─────────▼─────────▼─────────▼──────────────────┐
│                    Infrastructure Layer                        │
│                                                                │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │PostgreSQL│  │PostgreSQL│  │PostgreSQL│  │PostgreSQL│        │
│  │  Auth DB │  │ Movie DB │  │Booking DB│  │Payment DB│        │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘        │
│                                                                │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐          │
│  │  Redis   │  │ RabbitMQ │  │  Monitoring Stack    │          │
│  │  Cache   │  │ Messages │  │ (Prometheus/Grafana) │          │
│  └──────────┘  └──────────┘  └──────────────────────┘          │
└────────────────────────────────────────────────────────────────┘
```

### Request Flow Example: Complete Booking Process

```
Step 1: Browse Movies
Client --> Gateway --> Movie Service --> PostgreSQL (movies_db)
                                    --> Redis (cache hit/miss)

Step 2: Select Seats & Create Booking
Client --> Gateway --> Booking Service --> Redis (distributed lock)
                                       --> PostgreSQL (bookings_db)
                                       --> RabbitMQ (publish "booking.created")

Step 3: Process Payment (Asynchronous)
RabbitMQ --> Payment Service --> PostgreSQL (payments_db)
                             --> RabbitMQ (publish "payment.completed")

Step 4: Send Notifications (Asynchronous)
RabbitMQ --> Notification Service --> Email/SMS Gateway
                                  --> PostgreSQL (notifications_db)
```

---

## Service Components

### 1. Auth Service (Port 8001)

**Responsibility**: User authentication and authorization using JWT tokens

**Key Endpoints**:

- `POST /register` - User registration with password hashing
- `POST /token` - Login and JWT token generation
- `GET /users/me` - Get authenticated user profile
- `GET /health` - Health check endpoint

**Database**: `auth_db` (PostgreSQL)

- Tables: `users`, `roles`, `permissions`, `user_roles`

**Dependencies**:

- Redis (session caching, token blacklist)

**Technology**: Python 3.11 + FastAPI + SQLAlchemy

**Key Features**:

- Password hashing with bcrypt
- JWT token generation and validation
- Role-based access control (RBAC)
- Session management with Redis

---

### 2. Movie Service (Port 8002)

**Responsibility**: Movie catalog and showtime management

**Key Endpoints**:

- `GET /movies` - List all movies with filters
- `GET /movies/{id}` - Get detailed movie information
- `GET /showtimes` - Get available showtimes
- `GET /theaters` - List all theaters
- `GET /health` - Health check endpoint

**Database**: `movies_db` (PostgreSQL)

- Tables: `movies`, `theaters`, `screens`, `showtimes`, `seats`

**Dependencies**:

- Redis (caching frequently accessed data)
- Auth Service (user authorization)

**Technology**: Python 3.11 + FastAPI + SQLAlchemy

**Key Features**:

- Read-heavy optimization with Redis caching
- Support for multiple theaters and screens
- Showtime availability calculation

---

### 3. Booking Service (Port 8003)

**Responsibility**: Seat reservation with distributed locking

**Key Endpoints**:

- `POST /bookings` - Create new booking with seat locking
- `GET /bookings` - List user's bookings
- `GET /bookings/{id}` - Get specific booking details
- `DELETE /bookings/{id}` - Cancel booking
- `GET /health` - Health check endpoint

**Database**: `bookings_db` (PostgreSQL)

- Tables: `bookings`, `booking_seats`, `seat_status`

**Dependencies**:

- Redis (distributed locking mechanism)
- RabbitMQ (event publishing)
- Auth Service (user validation)
- Movie Service (showtime validation)

**Technology**: Python 3.11 + FastAPI + SQLAlchemy

**Critical Features**:

- Distributed locking with Redis to prevent double bookings
- Idempotency keys for duplicate request handling
- Event-driven architecture (publishes booking events)
- Automatic lock expiration and cleanup

**Locking Mechanism**:

```python
# Acquire distributed lock for seat selection
lock_key = f"lock:showtime:{showtime_id}:seat:{seat_id}"
acquired = redis.set(lock_key, user_id, nx=True, ex=300)  # 5-minute expiry

if not acquired:
    raise HTTPException(409, "Seat already locked")
```

---

### 4. Payment Service (Port 8004)

**Responsibility**: Payment processing and transaction management

**Key Endpoints**:

- `POST /payments` - Process payment with idempotency
- `GET /payments/{id}` - Get payment status
- `POST /refunds` - Process refund for cancellation
- `GET /health` - Health check endpoint

**Database**: `payments_db` (PostgreSQL)

- Tables: `payments`, `transactions`, `refunds`, `payment_methods`

**Dependencies**:

- RabbitMQ (consume booking events, publish payment events)
- Auth Service (user authorization)

**Technology**: Python 3.11 + FastAPI + SQLAlchemy

**Critical Features**:

- Idempotency keys to prevent duplicate charges
- Two-phase commit simulation for distributed transactions
- Saga pattern for compensating transactions
- Payment gateway integration (simulated)

**Transaction Flow**:

```
1. Consume "booking.created" event from RabbitMQ
2. Validate payment method and amount
3. Process payment (call external payment gateway)
4. Update payment status in database
5. Publish "payment.completed" or "payment.failed" event
6. Handle compensating transaction on failure
```

---

### 5. Notification Service (Port 8005)

**Responsibility**: Email and SMS notifications for system events

**Key Endpoints**:

- `POST /notifications/email` - Send email notification
- `POST /notifications/sms` - Send SMS notification
- `GET /notifications/history` - Get notification history
- `GET /health` - Health check endpoint

**Database**: `notifications_db` (PostgreSQL)

- Tables: `notifications`, `notification_templates`, `delivery_log`

**Dependencies**:

- RabbitMQ (consume events from all services)

**Technology**: Python 3.11 + FastAPI + SQLAlchemy

**Event Handlers**:

- `booking.created` --> Send booking confirmation email
- `payment.completed` --> Send payment receipt
- `booking.cancelled` --> Send cancellation notice

---

## Data Architecture

### Database-Per-Service Pattern

Each microservice owns its database exclusively. No direct database access between services.

**Benefits**:

- Data isolation and service autonomy
- Independent scaling per service needs
- Technology freedom (can use different database types)
- Failure isolation (database failure affects only one service)

**Trade-offs**:

- No ACID transactions across services (use Saga pattern)
- Data duplication where necessary
- More complex queries across service boundaries

```
Service Layer:
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│ Auth Service │     │Movie Service │     │Booking Svc   │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │ owns               │ owns               │ owns
       │                    │                    │
Database Layer:
┌──────▼───────┐     ┌──────▼───────┐     ┌──────▼───────┐
│   auth_db    │     │  movies_db   │     │ bookings_db  │
│ (PostgreSQL) │     │ (PostgreSQL) │     │ (PostgreSQL) │
└──────────────┘     └──────────────┘     └──────────────┘
```

### Database Configuration

| Service      | Database Name    | Port | User              | Container Name        |
| ------------ | ---------------- | ---- | ----------------- | --------------------- |
| Auth         | auth_db          | 5433 | auth_user         | postgres_auth         |
| Movie        | movies_db        | 5434 | movie_user        | postgres_movie        |
| Booking      | bookings_db      | 5435 | booking_user      | postgres_booking      |
| Payment      | payments_db      | 5436 | payment_user      | postgres_payment      |
| Notification | notifications_db | 5437 | notification_user | postgres_notification |

**Note**: Each database runs in its own PostgreSQL container with isolated storage volumes.

---

## Communication Patterns

### 1. Synchronous Communication (HTTP/REST)

**Use Cases**:

- Client-to-Service communication
- Service-to-Service queries requiring immediate response

**Example**: Booking Service validates showtime availability

```python
# Synchronous HTTP request
response = requests.get(
    f"{MOVIE_SERVICE_URL}/showtimes/{showtime_id}",
    headers={"Authorization": f"Bearer {token}"}
)
showtime_data = response.json()
```

**Advantages**:

- Simple request-response model
- Immediate feedback
- Easy to implement and debug

**Disadvantages**:

- Tight coupling between services
- Risk of cascading failures
- Performance bottleneck under high load

---

### 2. Asynchronous Communication (Message Queue)

**Use Cases**:

- Event notifications between services
- Background processing tasks
- Decoupling service operations

**Technology**: RabbitMQ with topic exchanges

**Exchange Configuration**:

- Exchange Name: `booking_events`, `payment_events`
- Exchange Type: Topic (pattern-based routing)
- Durability: Persistent (survive broker restart)

**Example Flow**:

```
1. Booking Service publishes event:
   Exchange: "booking_events"
   Routing Key: "booking.created"
   Message: {
     "booking_id": "12345",
     "user_id": "user_001",
     "showtime_id": "show_789",
     "total_amount": 25000,
     "timestamp": "2026-01-24T10:30:00Z"
   }

2. Payment Service subscribes to "booking.created":
   Queue: "payment_queue"
   Binding: "booking.created"
   Handler: process_booking_payment()

3. Notification Service subscribes to "booking.*":
   Queue: "notification_queue"
   Binding: "booking.*"  (wildcard - receives all booking events)
   Handler: send_notification()
```

**Message Reliability**:

- Publisher confirms (ensure message reached broker)
- Consumer acknowledgments (manual ack after processing)
- Dead letter queues (handle failed messages)

---

## Technology Stack

### Backend Services

| Component      | Technology | Version | Purpose                    |
| -------------- | ---------- | ------- | -------------------------- |
| Runtime        | Python     | 3.11    | Service implementation     |
| Web Framework  | FastAPI    | 0.104   | REST API framework         |
| ASGI Server    | Uvicorn    | 0.24    | Production ASGI server     |
| Database       | PostgreSQL | 15      | Primary data store         |
| ORM            | SQLAlchemy | 2.0     | Database abstraction       |
| Cache          | Redis      | 7       | Caching & distributed lock |
| Message Broker | RabbitMQ   | 3.12    | Async messaging            |
| API Gateway    | NGINX      | 1.25    | Reverse proxy & routing    |

### Infrastructure & Operations

| Component        | Technology     | Purpose                    |
| ---------------- | -------------- | -------------------------- |
| Containerization | Docker         | Application packaging      |
| Orchestration    | Docker Compose | Local development          |
| Orchestration    | Kubernetes     | Production deployment      |
| Monitoring       | Prometheus     | Metrics collection         |
| Visualization    | Grafana        | Dashboards & alerts        |
| CI/CD            | GitHub Actions | Automated testing & deploy |

### Development & Testing

| Tool        | Purpose                      |
| ----------- | ---------------------------- |
| pytest      | Unit and integration testing |
| k6 / Locust | Load and performance testing |
| Postman     | Manual API testing           |
| Git         | Version control              |

---

## Security Architecture

### Authentication Flow (JWT-Based)

```
Step 1: User Login
Client --> POST /api/auth/token
Body: {"username": "user@example.com", "password": "secret"}

Step 2: Credential Validation
Auth Service:
  - Retrieve user from database
  - Verify password hash (bcrypt.verify)
  - Generate JWT token

Step 3: JWT Token Structure
Header:  {"alg": "HS256", "typ": "JWT"}
Payload: {
  "user_id": "123",
  "username": "user@example.com",
  "roles": ["user"],
  "exp": 1706181600  // Expiration timestamp
}
Signature: HMACSHA256(base64(header).base64(payload), SECRET_KEY)

Step 4: Protected Request
Client --> GET /api/bookings
Headers: {"Authorization": "Bearer <jwt_token>"}

Step 5: Token Validation (in each service)
Service:
  - Extract token from Authorization header
  - Verify signature with shared SECRET_KEY
  - Check expiration time
  - Extract user_id from payload
  - Proceed with request
```

### Security Measures

**1. Transport Security**:

- HTTPS/TLS for all external communication
- Certificate-based authentication (production)

**2. Authentication & Authorization**:

- JWT tokens with 24-hour expiration
- Password hashing with bcrypt (cost factor 12)
- Role-based access control (RBAC)
- Token refresh mechanism

**3. Data Security**:

- Database connection encryption (SSL mode)
- Environment-based secrets management
- No hardcoded credentials
- Sensitive data encryption at rest

**4. API Security**:

- Rate limiting at API Gateway (100 req/min per IP)
- Input validation and sanitization
- SQL injection prevention (ORM parameterized queries)
- CORS configuration for allowed origins
- Request size limits

**5. Operational Security**:

- Health check endpoints (no authentication required)
- Audit logging for sensitive operations
- Monitoring and alerting
- Regular security updates

---

## Deployment Architecture

### Local Development (Docker Compose)

**Network Architecture**:

```
Docker Network: movie_booking_network (bridge)
├── Gateway (nginx) - Exposed: 80
├── Auth Service - Internal: 8001
├── Movie Service - Internal: 8002
├── Booking Service - Internal: 8003
├── Payment Service - Internal: 8004
├── Notification Service - Internal: 8005
├── PostgreSQL Instances (5 separate containers)
├── Redis - Exposed: 6379
├── RabbitMQ - Exposed: 5672, 15672 (management)
├── Prometheus - Exposed: 9090
└── Grafana - Exposed: 3000
```

**Container Configuration**:

```yaml
services:
  gateway:
    image: nginx:latest
    ports: ["80:80"]
    volumes: ["./gateway/nginx.conf:/etc/nginx/nginx.conf"]
    depends_on: [auth-service, movie-service, booking-service, ...]

  auth-service:
    build: ./services/auth-service
    environment:
      DATABASE_URL: postgresql://auth_user:auth_pass@postgres-auth/auth_db
      REDIS_URL: redis://redis:6379
      JWT_SECRET: ${JWT_SECRET}
    depends_on:
      postgres-auth:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8001/health"]
      interval: 10s
      timeout: 5s
      retries: 5

  postgres-auth:
    image: postgres:15
    environment:
      POSTGRES_USER: auth_user
      POSTGRES_PASSWORD: auth_pass
      POSTGRES_DB: auth_db
    volumes:
      - auth-db-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U auth_user -d auth_db"]
      interval: 10s
      timeout: 5s
      retries: 5
```

### Production Deployment (Kubernetes)

**Cluster Architecture**:

```
Kubernetes Cluster
├── Namespace: movie-booking-prod
│   │
│   ├── Workloads:
│   │   ├── Deployment: auth-service (3 replicas)
│   │   ├── Deployment: movie-service (3 replicas)
│   │   ├── Deployment: booking-service (5 replicas)
│   │   ├── Deployment: payment-service (3 replicas)
│   │   ├── Deployment: notification-service (2 replicas)
│   │   ├── StatefulSet: postgres-auth (1 replica + PVC)
│   │   ├── StatefulSet: postgres-movie (1 replica + PVC)
│   │   ├── StatefulSet: redis (1 replica + PVC)
│   │   └── StatefulSet: rabbitmq (3 replicas + PVC)
│   │
│   ├── Services:
│   │   ├── Service: gateway (LoadBalancer) - External access
│   │   ├── Service: auth-svc (ClusterIP) - Internal only
│   │   ├── Service: movie-svc (ClusterIP) - Internal only
│   │   └── ... (ClusterIP for all internal services)
│   │
│   ├── Configuration:
│   │   ├── ConfigMap: nginx-config
│   │   ├── ConfigMap: app-config
│   │   ├── Secret: db-credentials
│   │   └── Secret: jwt-secret
│   │
│   └── Storage:
│       ├── PVC: auth-db-pvc (10Gi)
│       ├── PVC: movie-db-pvc (20Gi)
│       └── ... (Persistent volumes for each database)
```

**Resource Configuration**:

```yaml
# Example: Booking Service Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: booking-service
  namespace: movie-booking-prod
spec:
  replicas: 5
  selector:
    matchLabels:
      app: booking-service
  template:
    metadata:
      labels:
        app: booking-service
    spec:
      containers:
        - name: booking-service
          image: booking-service:v2.0
          ports:
            - containerPort: 8003
          resources:
            requests:
              memory: "256Mi"
              cpu: "200m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          env:
            - name: DATABASE_URL
              valueFrom:
                secretKeyRef:
                  name: db-credentials
                  key: booking-db-url
          livenessProbe:
            httpGet:
              path: /health
              port: 8003
            initialDelaySeconds: 30
            periodSeconds: 10
            timeoutSeconds: 5
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /health
              port: 8003
            initialDelaySeconds: 5
            periodSeconds: 5
            timeoutSeconds: 3
            failureThreshold: 3
```

**Horizontal Pod Autoscaling**:

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: booking-service-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: booking-service
  minReplicas: 3
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
```

---

## CI/CD Pipeline

### Pipeline Overview

The project uses GitHub Actions for continuous integration and continuous deployment. The pipeline automatically triggers on push to `main`/`develop` branches and pull requests.

### CI/CD Pipeline Diagram

```
                              GITHUB ACTIONS CI/CD PIPELINE
+-------------------------------------------------------------------------------------------+
|                                                                                           |
|   TRIGGER: Push to main/develop OR Pull Request to main                                   |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
                                            |
                                            v
+-------------------------------------------------------------------------------------------+
|                              STAGE 1: CODE QUALITY CHECK                                  |
|                                                                                           |
|   +-------------+    +-------------+    +-------------+    +-------------+                |
|   |    Black    |    |    isort    |    |   Flake8    |    |    MyPy     |                |
|   | (formatter) |    |  (imports)  |    |  (linter)   |    |   (types)   |                |
|   +-------------+    +-------------+    +-------------+    +-------------+                |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
                                            |
                           +----------------+----------------+
                           |                                 |
                           v                                 v
+------------------------------------------+  +------------------------------------------+
|      STAGE 2: BUILD BACKEND SERVICES     |  |      STAGE 3: BUILD INFRASTRUCTURE       |
|                                          |  |                                          |
|  +------------+  +------------+          |  |  +------------+  +------------+          |
|  |   auth     |  |   movie    |          |  |  |  gateway   |  |  frontend  |          |
|  |  service   |  |  service   |          |  |  |  (nginx)   |  |   (web)    |          |
|  +------------+  +------------+          |  |  +------------+  +------------+          |
|  +------------+  +------------+          |  |                                          |
|  |  booking   |  |  payment   |          |  |                                          |
|  |  service   |  |  service   |          |  |                                          |
|  +------------+  +------------+          |  |                                          |
|  +------------+                          |  |                                          |
|  |notification|                          |  |                                          |
|  |  service   |                          |  |                                          |
|  +------------+                          |  |                                          |
|              |                           |  |              |                           |
|              v                           |  |              v                           |
|  +----------------------------------+    |  |  +----------------------------------+    |
|  |     Push to ghcr.io Registry    |    |  |  |     Push to ghcr.io Registry    |    |
|  +----------------------------------+    |  |  +----------------------------------+    |
+------------------------------------------+  +------------------------------------------+
                           |                                 |
                           +----------------+----------------+
                                            |
                                            v
+-------------------------------------------------------------------------------------------+
|                         STAGE 4: VALIDATE KUBERNETES MANIFESTS                            |
|                                                                                           |
|   +------------------+    +------------------+    +------------------+                    |
|   | kubectl dry-run  |    |  Syntax Check    |    | Schema Validate  |                   |
|   | (k8s/base/*.yaml)|    | (k8s/overlays/*) |    | (all manifests)  |                   |
|   +------------------+    +------------------+    +------------------+                    |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
                                            |
                                            v
+-------------------------------------------------------------------------------------------+
|                              STAGE 5: SMOKE TEST                                          |
|                                                                                           |
|   +------------------+         +------------------+         +------------------+          |
|   |   PostgreSQL     |         |      Redis       |         |   Run Tests      |          |
|   |   (test db)      |  <-->   |   (test cache)   |  <-->   |   (pytest)       |          |
|   +------------------+         +------------------+         +------------------+          |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
                                            |
                                            v
+-------------------------------------------------------------------------------------------+
|                              STAGE 6: NOTIFY SUCCESS                                      |
|                                                                                           |
|   Pipeline Status: SUCCESS                                                                |
|   - Code Quality: PASSED                                                                  |
|   - Backend Build: PASSED                                                                 |
|   - Infra Build: PASSED                                                                   |
|   - K8s Validation: PASSED                                                                |
|   - Smoke Tests: PASSED                                                                   |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
```

### Pipeline Stages Detail

| Stage | Name                    | Description                              | Dependencies |
|-------|-------------------------|------------------------------------------|--------------|
| 1     | Code Quality Check      | Run Black, isort, Flake8, MyPy           | None         |
| 2     | Build Backend Services  | Build & push 5 microservice images       | Stage 1      |
| 3     | Build Infrastructure    | Build & push gateway & frontend images   | Stage 1      |
| 4     | Validate K8s Manifests  | Dry-run kubectl on all YAML files        | Stage 1      |
| 5     | Smoke Test              | Run integration tests with test DB/Redis | Stage 2, 3   |
| 6     | Notify Success          | Report pipeline completion status        | Stage 2-5    |

### Container Registry

All Docker images are pushed to GitHub Container Registry (ghcr.io):

```
ghcr.io/<owner>/movie-booking/
    auth-service:latest
    auth-service:sha-abc1234
    movie-service:latest
    booking-service:latest
    payment-service:latest
    notification-service:latest
    gateway:latest
    frontend:latest
```

---

## Infrastructure Diagram

### Local Development Environment (Docker Compose)

```
+-------------------------------------------------------------------------------------------+
|                           LOCAL DEVELOPMENT ENVIRONMENT                                   |
|                              (Docker Compose Network)                                     |
+-------------------------------------------------------------------------------------------+
|                                                                                           |
|                                  EXTERNAL ACCESS                                          |
|                                        |                                                  |
|                              +---------+---------+                                        |
|                              |   localhost:80    |                                        |
|                              +---------+---------+                                        |
|                                        |                                                  |
|   +------------------------------------+------------------------------------+             |
|   |                          API GATEWAY (NGINX)                           |             |
|   |                      Container: movie_booking_gateway                  |             |
|   |                                                                        |             |
|   |   Routes:                                                              |             |
|   |     /api/auth/*      --> auth-service:8001                             |             |
|   |     /api/movies/*    --> movie-service:8002                            |             |
|   |     /api/bookings/*  --> booking-service:8003                          |             |
|   |     /api/payments/*  --> payment-service:8004                          |             |
|   |     /api/notifications/* --> notification-service:8005                 |             |
|   |     /*               --> frontend:80                                   |             |
|   +------------------------------------------------------------------------+             |
|                                        |                                                  |
|        +---------------+---------------+---------------+---------------+                  |
|        |               |               |               |               |                  |
|        v               v               v               v               v                  |
|   +---------+    +---------+    +---------+    +---------+    +-----------+              |
|   |  Auth   |    |  Movie  |    | Booking |    | Payment |    |Notification|             |
|   | Service |    | Service |    | Service |    | Service |    |  Service  |              |
|   |  :8001  |    |  :8002  |    |  :8003  |    |  :8004  |    |   :8005   |              |
|   +---------+    +---------+    +---------+    +---------+    +-----------+              |
|        |               |               |               |               |                  |
|        |               |               |               |               |                  |
|   +----+---------------+---------------+---------------+---------------+----+            |
|   |                          INFRASTRUCTURE LAYER                          |             |
|   |                                                                        |             |
|   |   DATABASES (Database-Per-Service Pattern)                             |             |
|   |   +-------------+  +-------------+  +-------------+  +-------------+   |             |
|   |   | postgres_   |  | postgres_   |  | postgres_   |  | postgres_   |   |             |
|   |   | auth        |  | movie       |  | booking     |  | payment     |   |             |
|   |   | :5433       |  | :5434       |  | :5435       |  | :5436       |   |             |
|   |   | (auth_db)   |  | (movies_db) |  |(bookings_db)|  |(payments_db)|   |             |
|   |   +-------------+  +-------------+  +-------------+  +-------------+   |             |
|   |                                                                        |             |
|   |   CACHE & MESSAGING                                                    |             |
|   |   +-------------------------+  +-------------------------+             |             |
|   |   |         REDIS           |  |       RABBITMQ          |             |             |
|   |   |  movie_booking_redis    |  |  movie_booking_rabbitmq |             |             |
|   |   |  :6379                  |  |  :5672 (AMQP)           |             |             |
|   |   |                         |  |  :15672 (Management)    |             |             |
|   |   |  - Session cache        |  |                         |             |             |
|   |   |  - Distributed locks    |  |  - booking_events       |             |             |
|   |   |  - Rate limiting        |  |  - payment_events       |             |             |
|   |   +-------------------------+  +-------------------------+             |             |
|   |                                                                        |             |
|   +------------------------------------------------------------------------+             |
|                                                                                           |
|   MONITORING STACK                                                                        |
|   +-------------------------+  +-------------------------+                               |
|   |      PROMETHEUS         |  |        GRAFANA          |                               |
|   |  prometheus:9090        |  |  grafana:3000           |                               |
|   |                         |  |                         |                               |
|   |  - Metrics collection   |  |  - Dashboards           |                               |
|   |  - Service discovery    |  |  - Alerts               |                               |
|   |  - Alert rules          |  |  - Visualization        |                               |
|   +-------------------------+  +-------------------------+                               |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
|   Docker Network: movie_booking_network (bridge mode)                                     |
|   Volumes: postgres_*_data, redis_data, rabbitmq_data, grafana_data, prometheus_data     |
+-------------------------------------------------------------------------------------------+
```

### Production Environment (Kubernetes)

```
+-------------------------------------------------------------------------------------------+
|                           KUBERNETES PRODUCTION CLUSTER                                   |
+-------------------------------------------------------------------------------------------+
|                                                                                           |
|   INTERNET                                                                                |
|       |                                                                                   |
|       v                                                                                   |
|   +-------+                                                                               |
|   | Cloud |                                                                               |
|   |  LB   |  (Cloud Provider Load Balancer)                                               |
|   +-------+                                                                               |
|       |                                                                                   |
|       v                                                                                   |
|   +-----------------------------------------------------------------------------------+   |
|   |                         INGRESS CONTROLLER (NGINX)                                |   |
|   |                                                                                   |   |
|   |   TLS Termination (cert-manager)                                                  |   |
|   |   Host: api.moviebooking.example.com                                              |   |
|   +-----------------------------------------------------------------------------------+   |
|                                        |                                                  |
|   +------------------------------------+------------------------------------+             |
|   |                    NAMESPACE: movie-booking-prod                       |             |
|   +------------------------------------------------------------------------+             |
|   |                                                                        |             |
|   |   MICROSERVICES (Deployments with HPA)                                 |             |
|   |                                                                        |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |   | auth-svc    |  | movie-svc   |  | booking-svc |                    |             |
|   |   | Deployment  |  | Deployment  |  | Deployment  |                    |             |
|   |   | 2-5 replicas|  | 3-10 replicas| | 3-10 replicas|                   |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |                                                                        |             |
|   |   +-------------+  +---------------+                                   |             |
|   |   | payment-svc |  | notification  |                                   |             |
|   |   | Deployment  |  | Deployment    |                                   |             |
|   |   | 2-5 replicas|  | 2-5 replicas  |                                   |             |
|   |   +-------------+  +---------------+                                   |             |
|   |                                                                        |             |
|   |   STATEFUL SERVICES (StatefulSets with PVC)                            |             |
|   |                                                                        |             |
|   |   +-------------+  +-------------+  +-------------+  +-------------+   |             |
|   |   | postgres-   |  | postgres-   |  | postgres-   |  | postgres-   |   |             |
|   |   | auth        |  | movie       |  | booking     |  | payment     |   |             |
|   |   | StatefulSet |  | StatefulSet |  | StatefulSet |  | StatefulSet |   |             |
|   |   | PVC: 10Gi   |  | PVC: 20Gi   |  | PVC: 20Gi   |  | PVC: 10Gi   |   |             |
|   |   +-------------+  +-------------+  +-------------+  +-------------+   |             |
|   |                                                                        |             |
|   |   +-------------------------+  +-------------------------+             |             |
|   |   |     redis-cluster       |  |    rabbitmq-cluster     |             |             |
|   |   |     StatefulSet         |  |     StatefulSet         |             |             |
|   |   |     3 replicas          |  |     3 replicas          |             |             |
|   |   |     PVC: 5Gi each       |  |     PVC: 10Gi each      |             |             |
|   |   +-------------------------+  +-------------------------+             |             |
|   |                                                                        |             |
|   |   CONFIGURATION                                                        |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |   | ConfigMaps  |  |   Secrets   |  | NetworkPolicy|                   |             |
|   |   | - nginx-cfg |  | - db-creds  |  | - deny-all  |                    |             |
|   |   | - app-cfg   |  | - jwt-secret|  | - allow-svc |                    |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |                                                                        |             |
|   +------------------------------------------------------------------------+             |
|                                                                                           |
|   +------------------------------------+------------------------------------+             |
|   |                    NAMESPACE: monitoring                               |             |
|   +------------------------------------------------------------------------+             |
|   |                                                                        |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |   | Prometheus  |  |   Grafana   |  |  Alertmanager|                   |             |
|   |   | StatefulSet |  | Deployment  |  | Deployment  |                    |             |
|   |   | PVC: 50Gi   |  | PVC: 10Gi   |  |             |                    |             |
|   |   +-------------+  +-------------+  +-------------+                    |             |
|   |                                                                        |             |
|   +------------------------------------------------------------------------+             |
|                                                                                           |
+-------------------------------------------------------------------------------------------+
|   Storage Class: standard (SSD)                                                           |
|   Network Plugin: Calico/Cilium                                                           |
|   DNS: CoreDNS                                                                            |
+-------------------------------------------------------------------------------------------+
```

### Environment Comparison

| Aspect              | Local (Docker Compose)          | Production (Kubernetes)              |
|---------------------|---------------------------------|--------------------------------------|
| Orchestration       | Docker Compose                  | Kubernetes                           |
| Scaling             | Manual (docker-compose scale)   | Auto (HPA based on CPU/Memory)       |
| Load Balancing      | NGINX (single instance)         | Cloud LB + Ingress Controller        |
| Service Discovery   | Docker DNS                      | Kubernetes DNS (CoreDNS)             |
| Storage             | Docker Volumes                  | Persistent Volume Claims (PVC)       |
| Secrets             | Environment variables           | Kubernetes Secrets (encrypted)       |
| Monitoring          | Prometheus + Grafana            | Prometheus Operator + Grafana        |
| High Availability   | No (single instance)            | Yes (multi-replica + pod anti-affinity)|
| TLS/SSL             | Optional (self-signed)          | cert-manager (Let's Encrypt)         |
| Network Policy      | None                            | Calico/Cilium NetworkPolicy          |

---

## Scalability & Performance

### Horizontal Scaling Strategy

| Service      | Min Replicas | Max Replicas | Scale Trigger                          |
| ------------ | ------------ | ------------ | -------------------------------------- |
| Auth         | 2            | 5            | CPU > 70% OR Login rate > 100/s        |
| Movie        | 3            | 10           | CPU > 70% OR Read requests > 500/s     |
| Booking      | 3            | 10           | Memory > 80% OR Active bookings > 50/s |
| Payment      | 2            | 5            | CPU > 70% OR Transaction rate > 100/s  |
| Notification | 2            | 5            | Queue depth > 1000 messages            |

### Caching Strategy

**Redis Cache Layers**:

1. **Session Cache** (TTL: 24 hours)
   - User sessions
   - JWT token blacklist

2. **Data Cache** (TTL: 5 minutes)
   - Movie listings
   - Theater information

3. **Availability Cache** (TTL: 1 minute)
   - Seat availability
   - Showtime availability

4. **Distributed Locks** (TTL: 5 minutes)
   - Seat booking locks
   - Payment processing locks

**Cache Invalidation**:

- Write-through on data modifications
- Event-based invalidation (RabbitMQ events)
- Automatic TTL expiration

### Database Optimization

**Indexing Strategy**:

```sql
-- Auth Service
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- Movie Service
CREATE INDEX idx_movies_release_date ON movies(release_date);
CREATE INDEX idx_showtimes_theater_screen ON showtimes(theater_id, screen_id, showtime);

-- Booking Service
CREATE INDEX idx_bookings_user_id ON bookings(user_id);
CREATE INDEX idx_bookings_showtime_id ON bookings(showtime_id);
CREATE INDEX idx_booking_seats_showtime_seat ON booking_seats(showtime_id, seat_id);

-- Payment Service
CREATE INDEX idx_payments_booking_id ON payments(booking_id);
CREATE INDEX idx_payments_status ON payments(status);
```

**Connection Pooling**:

```python
# SQLAlchemy connection pool configuration
engine = create_engine(
    DATABASE_URL,
    pool_size=20,          # Number of persistent connections
    max_overflow=10,       # Additional connections under load
    pool_timeout=30,       # Wait timeout for connection
    pool_recycle=3600      # Recycle connections every hour
)
```

**Read Replicas** (Production):

- Movie Service: 2 read replicas (read-heavy workload)
- Other services: Primary only (write-heavy or low traffic)

---

## Monitoring & Observability

### Metrics Collection (Prometheus)

**System Metrics**:

- Container CPU usage, memory usage, disk I/O
- Network throughput (bytes in/out)
- Container restart count

**Application Metrics**:

```python
# HTTP request metrics
http_requests_total = Counter('http_requests_total',
                               'Total HTTP requests',
                               ['service', 'method', 'endpoint', 'status'])

http_request_duration = Histogram('http_request_duration_seconds',
                                   'HTTP request latency',
                                   ['service', 'endpoint'])

# Business metrics
active_bookings = Gauge('booking_active_count',
                        'Number of active bookings')

payment_success_rate = Gauge('payment_success_rate',
                             'Payment success rate percentage')
```

**Database Metrics**:

- Connection pool usage
- Query duration (p50, p95, p99)
- Active connections
- Slow query count

**Message Queue Metrics**:

- Queue depth per queue
- Message publish rate
- Message consume rate
- Consumer lag

### Dashboards (Grafana)

**1. System Overview Dashboard**:

- All services health status
- Overall request rate and error rate
- System resource usage (CPU, Memory)
- Network traffic

**2. Service-Level Dashboard** (per service):

- Request rate, latency (p50, p95, p99)
- Error rate by endpoint
- Database query performance
- Cache hit ratio

**3. Business Metrics Dashboard**:

- Bookings per hour/day
- Revenue (total payment amount)
- Payment success vs failure rate
- Popular movies and showtimes
- User registration rate

**4. Infrastructure Dashboard**:

- Container health and restarts
- Database connections and query stats
- RabbitMQ queue depths
- Redis memory usage

### Alerting Rules

**Critical Alerts** (PagerDuty / Slack):

```yaml
- alert: ServiceDown
  expr: up{job="service"} == 0
  for: 2m
  annotations:
    summary: "Service {{ $labels.instance }} is down"

- alert: HighErrorRate
  expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.05
  for: 5m
  annotations:
    summary: "Error rate > 5% on {{ $labels.service }}"

- alert: HighLatency
  expr: histogram_quantile(0.95, http_request_duration_seconds_bucket) > 1.0
  for: 10m
  annotations:
    summary: "p95 latency > 1s on {{ $labels.service }}"
```

**Warning Alerts** (Slack only):

```yaml
- alert: HighCPUUsage
  expr: container_cpu_usage_percent > 80
  for: 15m
  annotations:
    summary: "CPU usage > 80% on {{ $labels.container }}"

- alert: HighMemoryUsage
  expr: container_memory_usage_percent > 85
  for: 15m
  annotations:
    summary: "Memory usage > 85% on {{ $labels.container }}"

- alert: QueueBacklog
  expr: rabbitmq_queue_messages_ready > 1000
  for: 10m
  annotations:
    summary: "Queue {{ $labels.queue }} has > 1000 messages"
```

---

## Architectural Decisions & Trade-offs

### Why Microservices Architecture?

**Decision**: Split the system into 5 independent microservices

**Alternatives Considered**:

1. Monolithic architecture (single application)
2. Modular monolith (modules within one codebase)

**Why Microservices**:

- Independent scaling (booking service scales more than auth)
- Team autonomy (different teams can own different services)
- Failure isolation (booking failure doesn't crash payment)
- Technology flexibility (can use different tools per service)

**Trade-offs Accepted**:

- Increased operational complexity (more deployments)
- Network latency between services
- Distributed transaction complexity
- More difficult end-to-end testing

---

### Why Database-Per-Service?

**Decision**: Each service owns its database exclusively

**Alternatives Considered**:

1. Shared database (all services access one DB)
2. Shared database with separate schemas

**Why Database-Per-Service**:

- Strong service boundaries and ownership
- Independent database scaling
- Failure isolation
- Schema evolution independence

**Trade-offs Accepted**:

- No ACID transactions across services
- Data duplication where necessary
- More complex cross-service queries
- More databases to manage

---

### Why RabbitMQ for Async Communication?

**Decision**: Use RabbitMQ for asynchronous messaging

**Alternatives Considered**:

1. Apache Kafka (log-based streaming)
2. Redis Pub/Sub (lightweight messaging)
3. AWS SQS (managed queue service)

**Why RabbitMQ**:

- Message persistence and delivery guarantees
- Flexible routing (exchanges and bindings)
- Good for small-to-medium scale
- Easy local development
- No vendor lock-in

**Trade-offs Accepted**:

- Not optimized for high-throughput streaming
- More resource-intensive than Redis Pub/Sub
- Requires operational expertise

---

### Why PostgreSQL for All Services?

**Decision**: Use PostgreSQL as primary database for all services

**Alternatives Considered**:

1. NoSQL databases (MongoDB, DynamoDB)
2. Mix of SQL and NoSQL per service needs

**Why PostgreSQL**:

- ACID compliance for booking/payment transactions
- Strong consistency guarantees
- Rich querying capabilities (joins, aggregations)
- Team familiarity and expertise
- JSON support for semi-structured data

**Trade-offs Accepted**:

- Not optimized for write-heavy workloads
- Scaling challenges (vertical scaling primarily)
- Schema migrations require coordination

---

## Future Enhancements

### Short-term (Next 3-6 months)

- [ ] Implement circuit breaker pattern (Resilience4j/PyBreaker)
- [ ] Add distributed tracing (Jaeger/OpenTelemetry)
- [ ] Implement API rate limiting per user
- [ ] Database read replicas for Movie Service
- [ ] Automated database backups to S3/GCS
- [ ] Blue-green deployment strategy

### Medium-term (6-12 months)

- [ ] Event sourcing for booking history
- [ ] CQRS pattern for analytics and reporting
- [ ] GraphQL API as alternative to REST
- [ ] Advanced caching with CDN
- [ ] Multi-region deployment
- [ ] Mobile app (iOS/Android)

### Long-term (12+ months)

- [ ] Service mesh implementation (Istio/Linkerd)
- [ ] Chaos engineering and fault injection testing
- [ ] Machine learning for seat recommendations
- [ ] Real-time seat availability WebSocket
- [ ] Blockchain-based ticketing (NFTs)
- [ ] Multi-tenant architecture (white-label solution)

---

## References & Further Reading

**Microservices Patterns**:

- [Microservices Patterns by Chris Richardson](https://microservices.io/patterns/)
- [Building Microservices by Sam Newman](https://samnewman.io/books/building_microservices/)

**Distributed Systems**:

- [Designing Data-Intensive Applications by Martin Kleppmann](https://dataintensive.net/)
- [Distributed Systems: Principles and Paradigms by Tanenbaum](https://www.distributed-systems.net/)

**Technology Documentation**:

- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [RabbitMQ Tutorials](https://www.rabbitmq.com/getstarted.html)
- [Kubernetes Documentation](https://kubernetes.io/docs/)

---

## Document Information

**Version**: 2.0  
**Last Updated**: 2026-01-24  
**Maintained By**: Group 9 - CSE702063  
**Review Cycle**: Monthly  
**Next Review**: 2026-02-24

---

## Appendix

### Glossary of Terms

- **API Gateway**: Single entry point for all client requests, handles routing and load balancing
- **Circuit Breaker**: Design pattern to prevent cascading failures by stopping requests to failing services
- **CQRS**: Command Query Responsibility Segregation - separate models for read and write operations
- **Idempotency**: Property where performing operation multiple times has same effect as performing it once
- **JWT**: JSON Web Token - stateless authentication mechanism
- **Saga Pattern**: Distributed transaction pattern using compensating transactions for rollback
- **Service Mesh**: Infrastructure layer for managing service-to-service communication

### Common Troubleshooting

**Issue**: Service health check fails  
**Solution**: Check database connectivity, Redis connectivity, environment variables

**Issue**: RabbitMQ message not consumed  
**Solution**: Verify consumer is running, check queue bindings, inspect dead letter queue

**Issue**: Double booking despite distributed lock  
**Solution**: Verify Redis connectivity, check lock TTL configuration, review booking logic

**Issue**: High database connection count  
**Solution**: Review connection pool settings, check for connection leaks, scale database

---

**End of Architecture Documentation**
