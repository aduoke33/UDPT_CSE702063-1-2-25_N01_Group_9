# Movie Booking Distributed System

**A Production-Grade Distributed Movie Ticket Booking System**

Built with Microservices Architecture, Event-Driven Design, and Enterprise Patterns

---

## Course Information

| Field | Value |
|:------|:------|
| **Course Code** | CSE702063 |
| **Course Name** | Distributed Applications (Ứng dụng Phân tán) |
| **Group** | N01 - Group 9 |
| **Semester** | 1-2-25 |
| **Version** | 2.0 |

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
- [Technology Stack](#technology-stack)
- [Prerequisites](#prerequisites)
- [Installation Guide](#installation-guide)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [API Documentation](#api-documentation)
- [Testing](#testing)
- [Monitoring and Observability](#monitoring-and-observability)
- [Project Structure](#project-structure)
- [Distributed Patterns](#distributed-patterns)
- [Security Implementation](#security-implementation)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

This project demonstrates a **real-world distributed application** for booking movie tickets, implementing patterns and practices used by industry leaders like CGV, Lotte Cinema, and AMC Theatres.

The system showcases critical distributed systems concepts:

- **Microservices Architecture** - 5 independent, loosely-coupled services
- **Database per Service** - Each service owns its data with PostgreSQL
- **Event-Driven Communication** - Asynchronous messaging via RabbitMQ
- **Distributed Locking** - Preventing double-booking with Redis locks
- **API Gateway Pattern** - Unified entry point with NGINX
- **Circuit Breaker Pattern** - Fault tolerance and graceful degradation
- **Idempotency** - Safe payment retry mechanisms

---

## Key Features

### Core Functionality

| Feature | Description |
|:--------|:------------|
| **User Authentication** | JWT-based secure authentication with token refresh |
| **Movie Catalog** | Browse movies with details, posters, genres, ratings |
| **Theater Management** | Multiple theaters with hall configurations |
| **Showtime Scheduling** | Dynamic showtime listings with real-time availability |
| **Seat Selection** | Interactive seat map with real-time status |
| **Ticket Booking** | Atomic booking with distributed locking |
| **Payment Processing** | Secure payment with idempotency protection |
| **Notifications** | Asynchronous email confirmations via message queue |

### Distributed System Features

| Pattern | Implementation |
|:--------|:---------------|
| **Distributed Locking** | Redis `SET NX EX` for seat reservation |
| **Circuit Breaker** | Automatic failover on service failures |
| **Idempotency Keys** | Prevent duplicate payment processing |
| **Correlation IDs** | Request tracing across services |
| **Rate Limiting** | NGINX-based API throttling |
| **Retry with Backoff** | Exponential backoff for transient failures |

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                    Laravel Frontend (:8080)                         │    │
│  │                 Blade Templates + Bootstrap 5                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            API GATEWAY LAYER                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                      NGINX Gateway (:80)                            │    │
│  │         Rate Limiting • CORS • SSL Termination • Load Balance       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
          ┌───────────────────────────┼───────────────────────────┐
          ▼                           ▼                           ▼
┌─────────────────┐         ┌─────────────────┐         ┌─────────────────┐
│  Auth Service   │         │  Movie Service  │         │ Booking Service │
│     (:8001)     │         │     (:8002)     │         │     (:8003)     │
│   FastAPI +     │         │   FastAPI +     │         │   FastAPI +     │
│   PostgreSQL    │         │   PostgreSQL    │         │   PostgreSQL    │
└────────┬────────┘         └────────┬────────┘         └────────┬────────┘
         │                           │                           │
         ▼                           ▼                           ▼
   ┌──────────┐              ┌───────────┐              ┌────────────┐
   │ auth_db  │              │ movies_db │              │ bookings_db│
   │  (:5433) │              │  (:5434)  │              │   (:5435)  │
   └──────────┘              └───────────┘              └────────────┘

          ┌───────────────────────────┼───────────────────────────┐
          ▼                           ▼                           │
┌─────────────────┐         ┌─────────────────┐                   │
│Payment Service  │         │Notification Svc │                   │
│     (:8004)     │         │     (:8005)     │                   │
│   FastAPI +     │◄────────│   FastAPI +     │◄──────────────────┘
│   PostgreSQL    │ RabbitMQ│   PostgreSQL    │    Events
└────────┬────────┘         └────────┬────────┘
         │                           │
         ▼                           ▼
  ┌───────────┐             ┌────────────────┐
  │payments_db│             │notifications_db│
  │  (:5436)  │             │    (:5437)     │
  └───────────┘             └────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                         INFRASTRUCTURE LAYER                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │    Redis     │  │   RabbitMQ   │  │  Prometheus  │  │   Grafana    │    │
│  │   (:6379)    │  │   (:5672)    │  │   (:9090)    │  │   (:3000)    │    │
│  │Cache & Locks │  │Message Queue │  │   Metrics    │  │  Dashboards  │    │
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Service Responsibilities

| Service | Port | Database | Key Responsibilities |
|:--------|:----:|:---------|:---------------------|
| **Auth Service** | 8001 | auth_db:5433 | User registration, login, JWT tokens, password hashing |
| **Movie Service** | 8002 | movies_db:5434 | Movie catalog, theaters, showtimes, seat management |
| **Booking Service** | 8003 | bookings_db:5435 | Seat locking, booking creation, booking management |
| **Payment Service** | 8004 | payments_db:5436 | Payment processing, refunds, idempotency |
| **Notification Service** | 8005 | notifications_db:5437 | Email notifications, async message consumption |

---

## Technology Stack

### Backend Services

| Component | Technology | Version | Purpose |
|:----------|:-----------|:--------|:--------|
| Language | Python | 3.11+ | Service implementation |
| Framework | FastAPI | 0.104+ | REST API framework |
| ORM | SQLAlchemy | 2.0+ | Database abstraction |
| Validation | Pydantic | 2.0+ | Data validation |
| Auth | PyJWT | 2.8+ | JWT token handling |
| HTTP Client | HTTPX | 0.25+ | Async HTTP requests |

### Frontend

| Component | Technology | Version | Purpose |
|:----------|:-----------|:--------|:--------|
| Framework | Laravel | 10.x | PHP web framework |
| Language | PHP | 8.2+ | Server-side logic |
| Template Engine | Blade | - | View rendering |
| CSS Framework | Bootstrap | 5.x | UI components |

### Infrastructure

| Component | Technology | Version | Purpose |
|:----------|:-----------|:--------|:--------|
| Database | PostgreSQL | 15-alpine | Persistent storage |
| Cache | Redis | 7-alpine | Caching & distributed locks |
| Message Queue | RabbitMQ | 3-management | Async messaging |
| Gateway | NGINX | alpine | API routing & load balancing |
| Containerization | Docker | 24+ | Service packaging |
| Orchestration | Docker Compose | 2.x | Local development |
| Orchestration | Kubernetes | 1.28+ | Production deployment |

### Monitoring & Observability

| Component | Technology | Purpose |
|:----------|:-----------|:--------|
| Metrics | Prometheus | Time-series metrics collection |
| Visualization | Grafana | Dashboards and alerting |
| Tracing | Correlation IDs | Distributed request tracing |

---

## Prerequisites

### Required Software

| Software | Minimum Version | Download Link |
|:---------|:----------------|:--------------|
| Docker Desktop | 4.0+ | [docker.com/products/docker-desktop](https://docker.com/products/docker-desktop) |
| Git | 2.30+ | [git-scm.com/downloads](https://git-scm.com/downloads) |

### System Requirements

| Resource | Minimum | Recommended |
|:---------|:--------|:------------|
| RAM | 8 GB | 16 GB |
| CPU | 4 cores | 8 cores |
| Disk Space | 10 GB | 20 GB |

### Optional (For Development)

| Software | Purpose |
|:---------|:--------|
| Python 3.11+ | Running services locally |
| Node.js 18+ | Frontend development |
| kubectl | Kubernetes management |
| Helm 3+ | Kubernetes package management |

---

## Installation Guide

### Step 1: Clone Repository

```bash
# Clone the repository
git clone <repository-url>
cd UDPT_CSE702063-1-2-25_N01_Group_9
```

### Step 2: Environment Configuration

```bash
# Copy environment template (if exists)
cp .env.example .env

# Or use default configuration (no .env needed for basic setup)
```

### Step 3: Build and Start Services

**Windows (PowerShell):**
```powershell
# Using helper script
.\scripts\run_local.ps1 up

# Or using docker-compose directly
docker-compose up -d --build
```

**Linux/macOS:**
```bash
# Using helper script
./scripts/run_local.sh up

# Or using docker-compose directly
docker-compose up -d --build
```

### Step 4: Verify Installation

```bash
# Check all services are running
docker-compose ps

# Expected output: All services showing "Up" status
```

### Step 5: Seed Database (Optional)

```bash
# Seed sample movies and showtimes
docker-compose exec movie-db psql -U postgres -d movies_db -f /docker-entrypoint-initdb.d/seed_movies.sql
```

---

## Configuration

### Service Ports

| Service | Internal Port | External Port | URL |
|:--------|:--------------|:--------------|:----|
| API Gateway | 80 | 80 | http://localhost |
| Frontend | 80 | 8080 | http://localhost:8080 |
| Auth Service | 8000 | 8001 | http://localhost:8001 |
| Movie Service | 8000 | 8002 | http://localhost:8002 |
| Booking Service | 8000 | 8003 | http://localhost:8003 |
| Payment Service | 8000 | 8004 | http://localhost:8004 |
| Notification Service | 8000 | 8005 | http://localhost:8005 |

### Database Ports

| Database | Port | Database Name |
|:---------|:-----|:--------------|
| Auth DB | 5433 | auth_db |
| Movies DB | 5434 | movies_db |
| Bookings DB | 5435 | bookings_db |
| Payments DB | 5436 | payments_db |
| Notifications DB | 5437 | notifications_db |

### Infrastructure Ports

| Service | Port | Credentials |
|:--------|:-----|:------------|
| Redis | 6379 | No auth (dev) |
| RabbitMQ | 5672 | guest/guest |
| RabbitMQ UI | 15672 | guest/guest |
| Prometheus | 9090 | No auth |
| Grafana | 3000 | admin/admin |

---

## Running the Application

### Start All Services

```powershell
# Windows
docker-compose up -d --build

# With monitoring stack
docker-compose --profile monitoring up -d --build
```

### Access Points

| Interface | URL | Description |
|:----------|:----|:------------|
| **Frontend** | http://localhost:8080 | User interface |
| **API Gateway** | http://localhost | Unified API entry |
| **Swagger Docs** | http://localhost:800X/docs | API documentation (per service) |
| **RabbitMQ** | http://localhost:15672 | Message queue management |
| **Grafana** | http://localhost:3000 | Monitoring dashboards |

### Stop Services

```powershell
# Stop all services
docker-compose down

# Stop and remove volumes (clean reset)
docker-compose down -v
```

---

## API Documentation

### Interactive Documentation

Each service provides Swagger UI documentation:

| Service | Swagger URL |
|:--------|:------------|
| Auth | http://localhost:8001/docs |
| Movie | http://localhost:8002/docs |
| Booking | http://localhost:8003/docs |
| Payment | http://localhost:8004/docs |
| Notification | http://localhost:8005/docs |

### API Endpoints Overview

#### Authentication Service (`/api/auth`)

| Method | Endpoint | Description | Auth Required |
|:-------|:---------|:------------|:--------------|
| `POST` | `/register` | Register new user | No |
| `POST` | `/token` | Login and get JWT | No |
| `GET` | `/verify` | Verify JWT token | Yes |
| `GET` | `/users/me` | Get current user | Yes |
| `GET` | `/health` | Health check | No |

#### Movie Service (`/api/movies`)

| Method | Endpoint | Description | Auth Required |
|:-------|:---------|:------------|:--------------|
| `GET` | `/movies` | List all movies | No |
| `GET` | `/movies/{id}` | Get movie details | No |
| `GET` | `/theaters` | List theaters | No |
| `GET` | `/showtimes` | List showtimes | No |
| `GET` | `/showtimes/{id}/seats` | Get seat availability | No |
| `GET` | `/health` | Health check | No |

#### Booking Service (`/api/bookings`)

| Method | Endpoint | Description | Auth Required |
|:-------|:---------|:------------|:--------------|
| `POST` | `/book` | Create booking | Yes |
| `GET` | `/bookings` | List user bookings | Yes |
| `GET` | `/bookings/{id}` | Get booking details | Yes |
| `POST` | `/bookings/{id}/cancel` | Cancel booking | Yes |
| `GET` | `/health` | Health check | No |

#### Payment Service (`/api/payments`)

| Method | Endpoint | Description | Auth Required |
|:-------|:---------|:------------|:--------------|
| `POST` | `/process` | Process payment | Yes |
| `GET` | `/payments` | Payment history | Yes |
| `GET` | `/payments/{id}` | Payment details | Yes |
| `POST` | `/payments/{id}/refund` | Request refund | Yes |
| `GET` | `/health` | Health check | No |

### Quick Test Commands

```bash
# 1. Register a new user
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","username":"testuser","password":"Password123!"}'

# 2. Login and get token
curl -X POST http://localhost/api/auth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=testuser&password=Password123!"

# 3. Browse movies
curl http://localhost/api/movies/movies

# 4. Get showtimes
curl http://localhost/api/movies/showtimes

# 5. Book seats (requires token)
curl -X POST http://localhost/api/bookings/book \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"showtime_id":"SHOWTIME_UUID","seat_ids":["A1","A2"]}'
```

---

## Testing

### Automated End-to-End Tests

**Windows:**
```powershell
.\scripts\e2e_test.ps1
```

**Linux/macOS:**
```bash
./scripts/e2e_test.sh
```

### Health Check All Services

```bash
# Quick health check
curl http://localhost/api/auth/health
curl http://localhost/api/movies/health
curl http://localhost/api/bookings/health
curl http://localhost/api/payments/health
curl http://localhost/api/notifications/health
```

### Integration Tests

```bash
# Run Python integration tests
cd tests
python -m pytest integration_test.py -v
```

### Load Testing

```bash
# Run load tests with Locust
cd tests
locust -f load_test.py --host=http://localhost
```

---

## Monitoring and Observability

### Grafana Dashboards

Access Grafana at http://localhost:3000 (admin/admin)

**Available Dashboards:**
- Service Health Overview
- Request Rate & Latency
- Database Connections
- Redis Cache Metrics
- RabbitMQ Queue Metrics

### Prometheus Metrics

Access Prometheus at http://localhost:9090

**Key Metrics:**
- `http_requests_total` - Total HTTP requests
- `http_request_duration_seconds` - Request latency
- `booking_created_total` - Bookings created
- `payment_processed_total` - Payments processed

### Health Endpoints

All services expose health endpoints:

```bash
GET /health
```

Response:
```json
{
  "status": "healthy",
  "service": "auth-service",
  "timestamp": "2025-01-15T10:30:00Z",
  "dependencies": {
    "database": "connected",
    "redis": "connected"
  }
}
```

---

## Project Structure

```
UDPT_CSE702063-1-2-25_N01_Group_9/
|
+-- services/                       # Microservices
|   +-- auth-service/               # Authentication service
|   |   +-- app/
|   |   |   +-- main.py             # FastAPI application
|   |   |   +-- models.py           # SQLAlchemy models
|   |   |   +-- schemas.py          # Pydantic schemas
|   |   |   +-- crud.py             # Database operations
|   |   |   +-- auth.py             # JWT and password utilities
|   |   +-- Dockerfile
|   |   +-- requirements.txt
|   |
|   +-- movie-service/              # Movie catalog service
|   +-- booking-service/            # Booking management service
|   +-- payment-service/            # Payment processing service
|   +-- notification-service/       # Notification service
|   +-- shared/                     # Shared utilities
|
+-- frontend/                       # Laravel frontend
|   +-- app/
|   +-- resources/views/
|   +-- routes/
|   +-- Dockerfile
|
+-- database/                       # Database scripts
|   +-- init.sql                    # Schema initialization
|   +-- seed_movies.sql             # Sample movie data
|   +-- seed_showtimes.sql          # Sample showtime data
|
+-- gateway/                        # NGINX configuration
|   +-- nginx.conf                  # Gateway routing
|   +-- ssl/                        # SSL certificates
|
+-- monitoring/                     # Observability stack
|   +-- prometheus.yml              # Prometheus configuration
|   +-- grafana-dashboard.json      # Grafana dashboards
|   +-- grafana-datasources.yml     # Grafana data sources
|
+-- k8s/                            # Kubernetes manifests
|   +-- base/                       # Base configurations
|   +-- services/                   # Service deployments
|   +-- database/                   # Database deployments
|   +-- monitoring/                 # Monitoring stack
|
+-- helm/                           # Helm charts
|   +-- movie-booking/              # Application chart
|
+-- scripts/                        # Helper scripts
|   +-- run_local.ps1               # Windows startup
|   +-- run_local.sh                # Linux/macOS startup
|   +-- e2e_test.ps1                # Windows E2E tests
|   +-- e2e_test.sh                 # Linux/macOS E2E tests
|
+-- docs/                           # Documentation
|   +-- diagrams/                   # Architecture diagrams
|
+-- tests/                          # Test suites
|   +-- integration_test.py         # Integration tests
|   +-- load_test.py                # Load tests
|
+-- docker-compose.yml              # Main compose file
+-- docker-compose.dev.yml          # Development overrides
+-- docker-compose.test.yml         # Test configuration
+-- ARCHITECTURE.md                 # Architecture documentation
+-- DOCUMENTATION.md                # Technical documentation
+-- SYSTEM_DESIGN.md                # Design decisions
+-- GLOSSARY.md                     # Terms and definitions
+-- README.md                       # This file
```

---

## Distributed Patterns

### 1. Distributed Locking (Redis)

Prevents double-booking of seats:

```python
async def acquire_seat_lock(redis, showtime_id: str, seat_id: str, booking_id: str):
    lock_key = f"lock:seat:{showtime_id}:{seat_id}"
    acquired = await redis.set(lock_key, booking_id, nx=True, ex=300)
    if not acquired:
        raise SeatAlreadyBookedException(f"Seat {seat_id} is already locked")
    return True
```

### 2. Idempotency Keys (Payment)

Prevents duplicate charges:

```python
async def process_payment(booking_id: str, amount: float, idempotency_key: str):
    cache_key = f"idempotency:{idempotency_key}"
    existing = await redis.get(cache_key)
    if existing:
        return json.loads(existing)  # Return cached result
    
    result = await execute_payment(booking_id, amount)
    await redis.setex(cache_key, 86400, json.dumps(result))
    return result
```

### 3. Circuit Breaker

Handles service failures gracefully:

```python
@circuit_breaker(failure_threshold=5, recovery_timeout=30)
async def call_payment_service(booking_id: str):
    async with httpx.AsyncClient() as client:
        response = await client.post(f"{PAYMENT_URL}/process", json={...})
        return response.json()
```

### 4. Event-Driven Architecture

Asynchronous communication via RabbitMQ:

```python
# Publisher (Payment Service)
await rabbitmq.publish(
    exchange="booking_events",
    routing_key="payment.completed",
    message={"booking_id": booking_id, "amount": amount}
)

# Consumer (Notification Service)
@consumer("payment.completed")
async def handle_payment_completed(message):
    await send_confirmation_email(message["booking_id"])
```

---

## Security Implementation

### Authentication

- **JWT Tokens**: Stateless authentication with access tokens
- **Password Hashing**: BCrypt with salt rounds
- **Token Expiration**: Configurable token lifetime

### API Security

- **CORS**: Strict origin validation
- **Rate Limiting**: Request throttling at gateway
- **Input Validation**: Pydantic schema validation
- **SQL Injection Prevention**: Parameterized queries via SQLAlchemy

### Infrastructure Security

- **Network Isolation**: Docker networks per service group
- **Secrets Management**: Environment variables for sensitive data
- **TLS/SSL**: HTTPS termination at gateway

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Additional Documentation

| Document | Description |
|:---------|:------------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Detailed system architecture |
| [DOCUMENTATION.md](DOCUMENTATION.md) | Technical documentation |
| [SYSTEM_DESIGN.md](SYSTEM_DESIGN.md) | Design decisions and trade-offs |
| [GLOSSARY.md](GLOSSARY.md) | Terms and definitions |
| [docs/K8S_LOCAL_DEPLOYMENT.md](docs/K8S_LOCAL_DEPLOYMENT.md) | Kubernetes local setup |
| [docs/LOAD_TESTING.md](docs/LOAD_TESTING.md) | Load testing guide |
| [docs/RUNBOOK.md](docs/RUNBOOK.md) | Operations runbook |

---

**CSE702063 - Distributed Applications (Ung dung Phan tan)**

**N01 - Group 9 | Semester 1-2-25**
