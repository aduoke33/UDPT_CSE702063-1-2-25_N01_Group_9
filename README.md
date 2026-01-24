# Movie Booking Distributed System

A production-grade distributed movie ticket booking system built with microservices architecture.

| Field           | Value                                        |
| --------------- | -------------------------------------------- |
| **Course Code** | CSE702063                                    |
| **Course Name** | Distributed Applications (Ung dung Phan tan) |
| **Group**       | N01 - Group 9                                |
| **Semester**    | 1-2-25                                       |
| **Version**     | 2.0                                          |

---

## Project Overview

This project demonstrates a real-world distributed application for booking movie tickets, similar to CGV or Lotte Cinema's online booking system.

### Core Features

- User registration and authentication with JWT
- Browse movies, theaters, and showtimes
- Seat selection with distributed locking (no double-booking)
- Payment processing with idempotency protection
- Email notifications for booking confirmations

### System Architecture

The system consists of 5 independent microservices:

| Service              | Port | Responsibility            |
| -------------------- | ---- | ------------------------- |
| Auth Service         | 8001 | User login, JWT tokens    |
| Movie Service        | 8002 | Movie catalog, showtimes  |
| Booking Service      | 8003 | Seat reservation, locking |
| Payment Service      | 8004 | Payment processing        |
| Notification Service | 8005 | Email/SMS notifications   |

```
                              +-------------+
                              |   Client    |
                              +------+------+
                                     |
                                     | HTTPS
                                     v
                          +----------+----------+
                          |    API Gateway      |
                          |      (NGINX)        |
                          +----------+----------+
                                     |
          +-------------+------------+------------+-------------+
          |             |            |            |             |
          v             v            v            v             v
     +----+----+  +-----+-----+ +----+----+ +-----+-----+ +-----+-----+
     |  Auth   |  |   Movie   | | Booking | |  Payment  | |  Notify   |
     | Service |  |  Service  | | Service | |  Service  | |  Service  |
     +----+----+  +-----+-----+ +----+----+ +-----+-----+ +-----+-----+
          |             |            |            |             |
          +------+------+-----+------+------+-----+------+------+
                 |            |             |            |
                 v            v             v            v
          +------+------+ +---+---+  +------+------+ +---+------+
          | PostgreSQL  | | Redis |  | PostgreSQL  | | RabbitMQ |
          | (per svc)   | | Cache |  | (per svc)   | | Messages |
          +-------------+ +-------+  +-------------+ +----------+
```

---

## Quick Start

### Prerequisites

- Docker Desktop
- Git

### Installation

```bash
# Clone repository
git clone <repository-url>
cd UDPT_CSE702063-1-2-25_N01_Group_9

# Start all services (Windows PowerShell)
.\scripts\run_local.ps1 up

# Or use docker-compose directly
docker-compose up -d --build

# Check service status
docker-compose ps
```

### Verify Installation

```bash
# Health check
curl http://localhost/api/auth/health

# Run automated tests (Windows)
.\scripts\e2e_test.ps1
```

---

## API Endpoints

### Authentication (`/api/auth`)

| Method | Endpoint  | Description    | Auth |
| ------ | --------- | -------------- | ---- |
| POST   | /register | Create account | No   |
| POST   | /token    | Login, get JWT | No   |
| GET    | /verify   | Validate token | Yes  |
| GET    | /health   | Service health | No   |

### Movies (`/api/movies`)

| Method | Endpoint              | Description       | Auth |
| ------ | --------------------- | ----------------- | ---- |
| GET    | /movies               | List movies       | No   |
| GET    | /movies/{id}          | Movie details     | No   |
| GET    | /showtimes            | List showtimes    | No   |
| GET    | /showtimes/{id}/seats | Seat availability | No   |
| GET    | /health               | Service health    | No   |

### Bookings (`/api/bookings`)

| Method | Endpoint              | Description     | Auth |
| ------ | --------------------- | --------------- | ---- |
| POST   | /book                 | Create booking  | Yes  |
| GET    | /bookings             | List bookings   | Yes  |
| GET    | /bookings/{id}        | Booking details | Yes  |
| POST   | /bookings/{id}/cancel | Cancel booking  | Yes  |
| GET    | /health               | Service health  | No   |

### Payments (`/api/payments`)

| Method | Endpoint              | Description     | Auth |
| ------ | --------------------- | --------------- | ---- |
| POST   | /process              | Process payment | Yes  |
| GET    | /payments             | Payment history | Yes  |
| POST   | /payments/{id}/refund | Request refund  | Yes  |
| GET    | /health               | Service health  | No   |

---

## Example Usage

### 1. Register User

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "username": "moviefan",
    "password": "SecurePass123!"
  }'
```

### 2. Login

```bash
curl -X POST http://localhost/api/auth/token \
  -d "username=moviefan&password=SecurePass123!"
```

### 3. Browse Movies

```bash
curl http://localhost/api/movies/movies
```

### 4. Book Seats

```bash
curl -X POST http://localhost/api/bookings/book \
  -H "Authorization: Bearer <your-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "showtime_id": "<showtime-uuid>",
    "seat_ids": ["A1", "A2"]
  }'
```

---

## Technology Stack

### Application

| Component  | Technology     |
| ---------- | -------------- |
| Language   | Python 3.11    |
| Framework  | FastAPI        |
| ORM        | SQLAlchemy 2.0 |
| Validation | Pydantic 2.0   |

### Infrastructure

| Component     | Technology           |
| ------------- | -------------------- |
| Database      | PostgreSQL 15        |
| Cache         | Redis 7              |
| Message Queue | RabbitMQ 3.12        |
| Gateway       | NGINX                |
| Containers    | Docker               |
| Orchestration | Kubernetes           |
| Monitoring    | Prometheus + Grafana |

---

## Key Distributed Patterns

### 1. Distributed Locking

Prevents double-booking using Redis SETNX:

```python
lock_key = f"lock:seat:{showtime_id}:{seat_id}"
acquired = await redis.set(lock_key, booking_id, nx=True, ex=300)
if not acquired:
    raise SeatAlreadyBookedError("Seat is taken")
```

### 2. Event-Driven Architecture

Services communicate via RabbitMQ events:

```python
# Payment service publishes
await rabbitmq.publish("booking_events", "payment.completed", {...})

# Notification service subscribes
@consumer("payment.completed")
async def send_confirmation(event):
    await email.send(event["user_email"], "Tickets ready!")
```

### 3. Idempotency

Safe payment retries:

```python
idempotency_key = f"payment:{booking_id}"
if await redis.exists(idempotency_key):
    return existing_payment  # Don't charge twice
```

### 4. Circuit Breaker

Prevents cascade failures when services are down.

---

## Project Structure

```
UDPT_CSE702063-1-2-25_N01_Group_9/
|
+-- services/                 # Microservices
|   +-- auth-service/
|   +-- movie-service/
|   +-- booking-service/
|   +-- payment-service/
|   +-- notification-service/
|
+-- database/                 # Database init scripts
+-- gateway/                  # NGINX configuration
+-- monitoring/               # Prometheus, Grafana
+-- k8s/                      # Kubernetes manifests
+-- scripts/                  # Helper scripts
+-- docs/                     # Additional documentation
|
+-- docker-compose.yml        # Local development
+-- README.md                 # This file
+-- ARCHITECTURE.md           # System architecture
+-- SYSTEM_DESIGN.md          # Design decisions
```

---

## Service URLs (Development)

| Service              | URL                        |
| -------------------- | -------------------------- |
| API Gateway          | http://localhost           |
| Auth Swagger         | http://localhost:8001/docs |
| Movie Swagger        | http://localhost:8002/docs |
| Booking Swagger      | http://localhost:8003/docs |
| Payment Swagger      | http://localhost:8004/docs |
| Notification Swagger | http://localhost:8005/docs |
| RabbitMQ UI          | http://localhost:15672     |
| Grafana              | http://localhost:3000      |
| Prometheus           | http://localhost:9090      |

---

## Documentation

| Document         | Description                     |
| ---------------- | ------------------------------- |
| ARCHITECTURE.md  | System architecture details     |
| SYSTEM_DESIGN.md | Design decisions and trade-offs |

---

## Testing

```bash
# Run E2E tests (Windows)
.\scripts\e2e_test.ps1

# Run E2E tests (Linux/Mac)
./scripts/e2e_test.sh

# Manual health check
curl http://localhost/api/auth/health
curl http://localhost/api/movies/health
curl http://localhost/api/bookings/health
curl http://localhost/api/payments/health
curl http://localhost/api/notifications/health
```

---

## License

This project is licensed under the MIT License - see LICENSE file for details.

---

**CSE702063 - Distributed Applications**  
**N01 - Group 9 | Semester 1-2-25**
