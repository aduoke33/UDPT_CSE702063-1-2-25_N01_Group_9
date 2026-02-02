# Technical Documentation

## Movie Booking Distributed System

| Field            | Value                                |
| :--------------- | :----------------------------------- |
| **Version**      | 2.0                                  |
| **Last Updated** | February 2026                        |
| **Course**       | CSE702063 - Distributed Applications |
| **Group**        | N01 - Group 9                        |

---

## Table of Contents

1. [Technology Stack](#1-technology-stack)
2. [System Architecture](#2-system-architecture)
3. [Database Schema](#3-database-schema)
4. [API Documentation](#4-api-documentation)
5. [Security Implementation](#5-security-implementation)
6. [Distributed Patterns](#6-distributed-patterns)
7. [Deployment Guide](#7-deployment-guide)

---

## 1. Technology Stack

### 1.1 Backend Services

| Component         | Technology | Version      | Purpose                              |
| ----------------- | ---------- | ------------ | ------------------------------------ |
| **Framework**     | FastAPI    | 0.104+       | High-performance async REST API      |
| **Language**      | Python     | 3.11+        | Backend programming language         |
| **Database**      | PostgreSQL | 15-alpine    | Primary data storage                 |
| **Cache**         | Redis      | 7-alpine     | Caching, distributed locks, sessions |
| **Message Queue** | RabbitMQ   | 3-management | Async messaging between services     |
| **ORM**           | SQLAlchemy | 2.0+         | Async database operations            |

### 1.2 Frontend Application

| Component       | Technology | Version | Purpose                       |
| --------------- | ---------- | ------- | ----------------------------- |
| **Framework**   | Laravel    | 10.x    | PHP web framework             |
| **Language**    | PHP        | 8.2+    | Frontend programming language |
| **Template**    | Blade      | -       | Server-side rendering         |
| **HTTP Client** | Guzzle     | 7.x     | API communication             |

### 1.3 Infrastructure

| Component            | Technology     | Version | Purpose                             |
| -------------------- | -------------- | ------- | ----------------------------------- |
| **Containerization** | Docker         | 24.x    | Application containers              |
| **Orchestration**    | Docker Compose | 2.x     | Local development orchestration     |
| **API Gateway**      | NGINX          | Alpine  | Load balancing, routing, SSL        |
| **Monitoring**       | Prometheus     | Latest  | Metrics collection                  |
| **Dashboards**       | Grafana        | Latest  | Visualization & alerting            |
| **Kubernetes**       | K8s            | 1.28+   | Production orchestration (optional) |

### 1.4 Development Tools

| Tool           | Purpose               |
| -------------- | --------------------- |
| **pytest**     | Python unit testing   |
| **locust**     | Load testing          |
| **k6**         | Performance testing   |
| **Mermaid.js** | Architecture diagrams |

---

## 2. System Architecture

### 2.1 Microservices Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SYSTEM ARCHITECTURE                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   ┌─────────┐                                                       │
│   │ Client  │ ──────► ┌──────────────────┐                         │
│   └─────────┘         │   NGINX Gateway   │                         │
│                       │    Port: 80/443   │                         │
│                       └────────┬─────────┘                         │
│                                │                                    │
│         ┌──────────────────────┼──────────────────────┐            │
│         │          │           │          │           │            │
│         ▼          ▼           ▼          ▼           ▼            │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐│
│   │   Auth   │ │  Movie   │ │ Booking  │ │ Payment  │ │  Notify  ││
│   │  :8001   │ │  :8002   │ │  :8003   │ │  :8004   │ │  :8005   ││
│   └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘│
│        │            │            │            │            │       │
│        ▼            ▼            ▼            ▼            ▼       │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐│
│   │ auth_db  │ │movies_db │ │bookings_ │ │payments_ │ │ notify_  ││
│   │  :5433   │ │  :5434   │ │   :5435  │ │   :5436  │ │   :5437  ││
│   └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘│
│                                                                     │
│   ┌──────────────────────┐    ┌──────────────────────┐            │
│   │       Redis          │    │      RabbitMQ        │            │
│   │       :6379          │    │    :5672 / :15672    │            │
│   └──────────────────────┘    └──────────────────────┘            │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Service Responsibilities

| Service                  | Port | Database                | Responsibility                                           |
| ------------------------ | ---- | ----------------------- | -------------------------------------------------------- |
| **Auth Service**         | 8001 | auth_db (5433)          | User authentication, JWT tokens, profile management      |
| **Movie Service**        | 8002 | movies_db (5434)        | Movie catalog, showtimes, theaters, seat availability    |
| **Booking Service**      | 8003 | bookings_db (5435)      | Seat reservation, distributed locking, booking lifecycle |
| **Payment Service**      | 8004 | payments_db (5436)      | Payment processing, idempotency, refunds                 |
| **Notification Service** | 8005 | notifications_db (5437) | Email/SMS notifications via RabbitMQ                     |

### 2.3 Communication Patterns

| Pattern          | Usage              | Implementation               |
| ---------------- | ------------------ | ---------------------------- |
| **Synchronous**  | Real-time requests | HTTP/REST via API Gateway    |
| **Asynchronous** | Background tasks   | RabbitMQ message queues      |
| **Event-Driven** | Notifications      | Publisher/Subscriber pattern |

---

## 3. Database Schema

### 3.1 Entity Relationship Diagram

```
┌───────────────────┐       ┌───────────────────┐
│      users        │       │      movies       │
├───────────────────┤       ├───────────────────┤
│ id (PK, UUID)     │       │ id (PK, UUID)     │
│ email (UNIQUE)    │       │ title             │
│ username (UNIQUE) │       │ description       │
│ password_hash     │       │ duration_minutes  │
│ full_name         │       │ genre             │
│ phone_number      │       │ language          │
│ role              │       │ rating            │
│ is_active         │       │ release_date      │
│ created_at        │       │ poster_url        │
│ updated_at        │       │ director          │
└───────────────────┘       │ is_active         │
         │                  └─────────┬─────────┘
         │                            │
         │                            │ 1:N
         │                            ▼
         │                  ┌───────────────────┐
         │                  │    showtimes      │
         │                  ├───────────────────┤
         │                  │ id (PK, UUID)     │
         │                  │ movie_id (FK)     │◄───────┐
         │                  │ theater_id (FK)   │        │
         │                  │ show_date         │        │
         │                  │ show_time         │        │
         │                  │ price             │        │
         │                  │ available_seats   │        │
         │                  │ total_seats       │        │
         │                  └─────────┬─────────┘        │
         │                            │                  │
         │              ┌─────────────┴─────────────┐    │
         │              │ 1:N                   1:N │    │
         │              ▼                           ▼    │
         │    ┌───────────────────┐      ┌────────────────┐
         │    │    bookings       │      │    theaters    │
         │    ├───────────────────┤      ├────────────────┤
         │    │ id (PK, UUID)     │      │ id (PK, UUID)  │
         │    │ user_id (FK)      │◄─────│ name           │
         │    │ showtime_id (FK)  │      │ location       │
         │    │ booking_code      │      │ city           │
         │    │ total_seats       │      │ total_seats    │
         │    │ total_price       │      └───────┬────────┘
         │    │ status            │              │
         │    │ payment_status    │              │ 1:N
         │    │ expires_at        │              ▼
         │    └─────────┬─────────┘      ┌────────────────┐
         │              │                │     seats      │
         │              │ 1:N            ├────────────────┤
         │              ▼                │ id (PK, UUID)  │
         │    ┌───────────────────┐      │ theater_id(FK) │
         │    │  booking_seats    │      │ seat_row       │
         │    ├───────────────────┤      │ seat_number    │
         │    │ id (PK, UUID)     │      │ seat_type      │
         │    │ booking_id (FK)   │      └────────────────┘
         │    │ seat_id (FK)      │
         │    │ showtime_id (FK)  │
         │    │ status            │
         │    └───────────────────┘
         │
         │    ┌───────────────────┐      ┌───────────────────┐
         │    │    payments       │      │  notifications    │
         │    ├───────────────────┤      ├───────────────────┤
         └───►│ id (PK, UUID)     │      │ id (PK, UUID)     │
              │ booking_id (FK)   │      │ user_id (FK)      │◄───┘
              │ user_id           │      │ type              │
              │ amount            │      │ subject           │
              │ payment_method    │      │ message           │
              │ transaction_id    │      │ status            │
              │ idempotency_key   │      │ sent_at           │
              │ status            │      │ created_at        │
              └───────────────────┘      └───────────────────┘
```

### 3.2 Table Definitions

#### 3.2.1 Users Table (auth_db)

| Column          | Type         | Constraints                    | Description            |
| --------------- | ------------ | ------------------------------ | ---------------------- |
| `id`            | UUID         | PK, DEFAULT uuid_generate_v4() | Unique identifier      |
| `email`         | VARCHAR(255) | UNIQUE, NOT NULL               | User email address     |
| `username`      | VARCHAR(100) | UNIQUE, NOT NULL               | Login username         |
| `password_hash` | VARCHAR(255) | NOT NULL                       | BCrypt hashed password |
| `full_name`     | VARCHAR(255) | -                              | User's full name       |
| `phone_number`  | VARCHAR(20)  | -                              | Contact phone          |
| `role`          | VARCHAR(20)  | CHECK (customer/admin/staff)   | User role              |
| `is_active`     | BOOLEAN      | DEFAULT true                   | Account status         |
| `created_at`    | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP      | Creation time          |
| `updated_at`    | TIMESTAMP    | AUTO UPDATE                    | Last modification      |

#### 3.2.2 Movies Table (movies_db)

| Column             | Type         | Constraints  | Description                 |
| ------------------ | ------------ | ------------ | --------------------------- |
| `id`               | UUID         | PK           | Unique identifier           |
| `title`            | VARCHAR(255) | NOT NULL     | Movie title                 |
| `description`      | TEXT         | -            | Synopsis                    |
| `duration_minutes` | INTEGER      | NOT NULL     | Runtime in minutes          |
| `genre`            | VARCHAR(100) | -            | Genre category              |
| `language`         | VARCHAR(50)  | -            | Audio language              |
| `rating`           | VARCHAR(10)  | -            | Age rating (PG-13, R, etc.) |
| `release_date`     | DATE         | -            | Theatrical release date     |
| `poster_url`       | VARCHAR(500) | -            | Movie poster image URL      |
| `director`         | VARCHAR(255) | -            | Director name               |
| `is_active`        | BOOLEAN      | DEFAULT true | Currently showing           |

#### 3.2.3 Bookings Table (bookings_db)

| Column           | Type          | Constraints       | Description                         |
| ---------------- | ------------- | ----------------- | ----------------------------------- |
| `id`             | UUID          | PK                | Unique identifier                   |
| `user_id`        | UUID          | FK → users.id     | Booking owner                       |
| `showtime_id`    | UUID          | FK → showtimes.id | Selected showtime                   |
| `booking_code`   | VARCHAR(20)   | UNIQUE, NOT NULL  | Human-readable code                 |
| `total_seats`    | INTEGER       | NOT NULL          | Number of seats                     |
| `total_price`    | DECIMAL(10,2) | NOT NULL          | Total amount                        |
| `status`         | VARCHAR(20)   | CHECK constraint  | pending/confirmed/cancelled/expired |
| `payment_status` | VARCHAR(20)   | CHECK constraint  | unpaid/paid/refunded                |
| `expires_at`     | TIMESTAMP     | -                 | Pending booking expiry              |

#### 3.2.4 Payments Table (payments_db)

| Column            | Type          | Constraints | Description                        |
| ----------------- | ------------- | ----------- | ---------------------------------- |
| `id`              | UUID          | PK          | Unique identifier                  |
| `booking_id`      | UUID          | NOT NULL    | Associated booking                 |
| `user_id`         | UUID          | NOT NULL    | Payment owner                      |
| `amount`          | DECIMAL(10,2) | NOT NULL    | Payment amount                     |
| `payment_method`  | VARCHAR(50)   | -           | credit_card/bank_transfer/e_wallet |
| `transaction_id`  | VARCHAR(255)  | UNIQUE      | Gateway transaction ID             |
| `idempotency_key` | VARCHAR(255)  | UNIQUE      | Client idempotency key             |
| `status`          | VARCHAR(20)   | CHECK       | pending/completed/failed/refunded  |

---

## 4. API Documentation

### 4.1 Auth Service (Port: 8001)

Base URL: `http://localhost:8001` or `/api/auth` via Gateway

| Method | Endpoint           | Description              | Auth Required |
| ------ | ------------------ | ------------------------ | ------------- |
| `POST` | `/token`           | User login (OAuth2 form) | No            |
| `POST` | `/register`        | Create new user account  | No            |
| `GET`  | `/verify`          | Verify JWT token         | Yes           |
| `PUT`  | `/profile`         | Update user profile      | Yes           |
| `POST` | `/change-password` | Change password          | Yes           |
| `POST` | `/logout`          | Invalidate token         | Yes           |
| `GET`  | `/health`          | Health check             | No            |

#### Example: Login

```http
POST /api/auth/token
Content-Type: application/x-www-form-urlencoded

username=user@example.com&password=secret123
```

**Response (200 OK):**

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "bearer",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "username": "user@example.com",
    "full_name": "John Doe",
    "role": "customer",
    "is_active": true
  }
}
```

### 4.2 Movie Service (Port: 8002)

Base URL: `http://localhost:8002` or `/api/movies` via Gateway

| Method | Endpoint                          | Description                           | Auth Required |
| ------ | --------------------------------- | ------------------------------------- | ------------- |
| `GET`  | `/movies`                         | List all active movies                | No            |
| `GET`  | `/movies/{id}`                    | Get movie details                     | No            |
| `POST` | `/movies`                         | Create new movie                      | Yes (Admin)   |
| `GET`  | `/theaters`                       | List all theaters                     | No            |
| `POST` | `/theaters`                       | Create new theater                    | Yes (Admin)   |
| `GET`  | `/showtimes`                      | List showtimes (filter by movie/date) | No            |
| `GET`  | `/showtimes/{id}`                 | Get showtime details                  | No            |
| `POST` | `/showtimes`                      | Create new showtime                   | Yes (Admin)   |
| `GET`  | `/showtimes/{id}/available-seats` | Get available seats                   | No            |
| `GET`  | `/seats/{theater_id}`             | Get theater seats                     | No            |
| `GET`  | `/health`                         | Health check                          | No            |

#### Example: Get Movies

```http
GET /api/movies?genre=Sci-Fi&q=Matrix
```

**Response (200 OK):**

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "title": "The Matrix Resurrections",
    "description": "Neo returns to the Matrix",
    "duration_minutes": 148,
    "genre": "Sci-Fi",
    "language": "English",
    "rating": "PG-13",
    "release_date": "2024-01-15",
    "poster_url": "/images/matrix.jpg",
    "director": "Lana Wachowski",
    "is_active": true
  }
]
```

### 4.3 Booking Service (Port: 8003)

Base URL: `http://localhost:8003` or `/api/bookings` via Gateway

| Method | Endpoint                | Description            | Auth Required |
| ------ | ----------------------- | ---------------------- | ------------- |
| `POST` | `/book`                 | Create new booking     | Yes           |
| `GET`  | `/bookings`             | Get user's bookings    | Yes           |
| `GET`  | `/bookings/{id}`        | Get booking details    | Yes           |
| `POST` | `/bookings/{id}/cancel` | Cancel booking         | Yes           |
| `POST` | `/seats/hold`           | Hold seats temporarily | Yes           |
| `POST` | `/seats/release`        | Release held seats     | Yes           |
| `GET`  | `/health`               | Health check           | No            |

#### Example: Create Booking

```http
POST /api/bookings/book
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json

{
  "showtime_id": "550e8400-e29b-41d4-a716-446655440002",
  "seat_ids": [
    "550e8400-e29b-41d4-a716-446655440010",
    "550e8400-e29b-41d4-a716-446655440011"
  ]
}
```

**Response (201 Created):**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440003",
  "user_id": "550e8400-e29b-41d4-a716-446655440000",
  "booking_code": "BK7X9M2P4Q",
  "showtime_id": "550e8400-e29b-41d4-a716-446655440002",
  "total_seats": 2,
  "total_price": 240000.0,
  "status": "pending",
  "payment_status": "unpaid",
  "expires_at": "2024-01-15T15:30:00Z"
}
```

### 4.4 Payment Service (Port: 8004)

Base URL: `http://localhost:8004` or `/api/payments` via Gateway

| Method | Endpoint                | Description                | Auth Required |
| ------ | ----------------------- | -------------------------- | ------------- |
| `POST` | `/process`              | Process payment            | Yes           |
| `GET`  | `/payments`             | Get user's payment history | Yes           |
| `GET`  | `/payments/{id}`        | Get payment details        | Yes           |
| `POST` | `/payments/{id}/refund` | Request refund             | Yes           |
| `GET`  | `/health`               | Health check               | No            |

#### Example: Process Payment

```http
POST /api/payments/process
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json
Idempotency-Key: unique-client-key-12345

{
  "booking_id": "550e8400-e29b-41d4-a716-446655440003",
  "payment_method": "credit_card",
  "amount": 240000.00
}
```

**Response (201 Created):**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440004",
  "booking_id": "550e8400-e29b-41d4-a716-446655440003",
  "amount": 240000.0,
  "payment_method": "credit_card",
  "transaction_id": "TXN_1705323600_ABC123",
  "status": "completed",
  "payment_date": "2024-01-15T14:20:00Z"
}
```

### 4.5 Notification Service (Port: 8005)

Base URL: `http://localhost:8005` or `/api/notifications` via Gateway

| Method | Endpoint                   | Description                | Auth Required |
| ------ | -------------------------- | -------------------------- | ------------- |
| `POST` | `/send`                    | Send notification directly | Yes           |
| `GET`  | `/notifications`           | Get user's notifications   | Yes           |
| `POST` | `/notifications/{id}/read` | Mark as read               | Yes           |
| `GET`  | `/health`                  | Health check               | No            |

### 4.6 Error Response Format

All services return standardized error responses:

```json
{
  "detail": "Error message description",
  "status_code": 400,
  "error_type": "ValidationError"
}
```

| Status Code | Meaning                                                  |
| ----------- | -------------------------------------------------------- |
| `400`       | Bad Request - Invalid input                              |
| `401`       | Unauthorized - Invalid/missing token                     |
| `403`       | Forbidden - Insufficient permissions                     |
| `404`       | Not Found - Resource doesn't exist                       |
| `409`       | Conflict - Resource conflict (e.g., seat already booked) |
| `429`       | Too Many Requests - Rate limited                         |
| `500`       | Internal Server Error                                    |
| `503`       | Service Unavailable - Circuit breaker open               |

---

## 5. Security Implementation

### 5.1 Authentication & Authorization

#### JWT Token Structure

```json
{
  "header": {
    "alg": "HS256",
    "typ": "JWT"
  },
  "payload": {
    "sub": "user@example.com",
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "role": "customer",
    "exp": 1705325400,
    "iat": 1705321800
  }
}
```

#### Configuration

| Parameter                     | Default Value        | Description               |
| ----------------------------- | -------------------- | ------------------------- |
| `SECRET_KEY`                  | Environment variable | JWT signing key (256-bit) |
| `ALGORITHM`                   | HS256                | JWT signing algorithm     |
| `ACCESS_TOKEN_EXPIRE_MINUTES` | 30                   | Token validity period     |

### 5.2 Password Security

- **Hashing Algorithm:** BCrypt (12 rounds)
- **Salt:** Automatically generated per password
- **Minimum Length:** 6 characters
- **Storage:** Only hash stored, never plaintext

```python
# Password hashing implementation
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

def get_password_hash(password: str) -> str:
    return pwd_context.hash(password)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)
```

### 5.3 API Security Headers

The NGINX gateway adds security headers to all responses:

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

### 5.4 CORS Configuration

Environment-configurable CORS origins:

```python
CORS_ORIGINS = os.getenv(
    "CORS_ORIGINS",
    "http://localhost:8080,http://localhost:3000"
).split(",")

app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS", "PATCH"],
    allow_headers=["Authorization", "Content-Type", "X-Correlation-ID"],
)
```

### 5.5 Rate Limiting

NGINX-based rate limiting:

| Zone      | Limit      | Purpose                       |
| --------- | ---------- | ----------------------------- |
| `general` | 60 req/min | Normal API endpoints          |
| `booking` | 10 req/min | Booking operations (stricter) |

```nginx
limit_req_zone $binary_remote_addr zone=general:10m rate=60r/m;
limit_req_zone $binary_remote_addr zone=booking:10m rate=10r/m;
```

### 5.6 Database Security

- **Credentials:** Environment variables (never in code)
- **Connections:** SSL/TLS in production
- **Per-Service Databases:** Data isolation between services
- **Parameterized Queries:** SQLAlchemy ORM prevents SQL injection

---

## 6. Distributed Patterns

### 6.1 Distributed Locking (Redis)

Prevents double-booking using Redis `SET NX EX`:

```python
async def acquire_lock(lock_name: str, expire_time: int = 10) -> bool:
    """Acquire distributed lock using Redis SET NX EX"""
    lock_key = f"lock:{lock_name}"
    acquired = await redis_client.set(lock_key, "locked", nx=True, ex=expire_time)
    return acquired is not None

async def release_lock(lock_name: str):
    """Release distributed lock"""
    await redis_client.delete(f"lock:{lock_name}")
```

**Usage in Booking:**

```python
# Lock pattern: lock:showtime:{showtime_id}
if not await acquire_lock(f"showtime:{showtime_id}"):
    raise HTTPException(409, "Seats being booked by another user")
try:
    # Perform seat reservation
    ...
finally:
    await release_lock(f"showtime:{showtime_id}")
```

### 6.2 Circuit Breaker Pattern

Prevents cascading failures between services:

```python
class CircuitBreaker:
    def __init__(self, failure_threshold: int = 5, recovery_timeout: int = 30):
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.failures = 0
        self.state = "CLOSED"  # CLOSED, OPEN, HALF_OPEN

    def can_execute(self) -> bool:
        if self.state == "CLOSED":
            return True
        if self.state == "OPEN":
            if time.time() - self.last_failure_time > self.recovery_timeout:
                self.state = "HALF_OPEN"
                return True
            return False
        return True  # HALF_OPEN
```

### 6.3 Idempotency (Payment Service)

Prevents duplicate payments:

```python
async def check_idempotency(idempotency_key: str, db: AsyncSession) -> Optional[Payment]:
    # Check Redis cache first (24h TTL)
    cached = await redis_client.get(f"idempotency:{idempotency_key}")
    if cached:
        return await get_payment_by_id(cached)

    # Check database
    result = await db.execute(
        select(Payment).filter(Payment.idempotency_key == idempotency_key)
    )
    return result.scalar_one_or_none()
```

### 6.4 Correlation ID Tracing

Request tracing across services:

```python
class CorrelationIdMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        correlation_id = request.headers.get("X-Correlation-ID", str(uuid.uuid4()))
        correlation_id_ctx.set(correlation_id)
        response = await call_next(request)
        response.headers["X-Correlation-ID"] = correlation_id
        return response
```

### 6.5 Retry with Exponential Backoff

```python
async def retry_with_backoff(func, max_retries: int = 3, base_delay: float = 0.5):
    for attempt in range(max_retries):
        try:
            return await func()
        except Exception as e:
            if attempt == max_retries - 1:
                raise
            delay = base_delay * (2 ** attempt)  # 0.5s, 1s, 2s
            await asyncio.sleep(delay)
```

---

## 7. Deployment Guide

### 7.1 Docker Compose (Development)

```bash
# Start all services
docker-compose up -d --build

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### 7.2 Kubernetes (Production)

```bash
# Create namespace
kubectl apply -f k8s/namespace.yaml

# Deploy databases
kubectl apply -f k8s/database/

# Deploy services
kubectl apply -f k8s/services/

# Deploy monitoring
kubectl apply -f k8s/monitoring/

# Deploy HPA for auto-scaling
kubectl apply -f k8s/autoscaling/hpa.yaml
```

### 7.3 Environment Variables

| Variable       | Service                        | Default   | Description                  |
| -------------- | ------------------------------ | --------- | ---------------------------- |
| `DATABASE_URL` | All                            | -         | PostgreSQL connection string |
| `REDIS_URL`    | Auth, Movie, Booking, Payment  | -         | Redis connection string      |
| `RABBITMQ_URL` | Booking, Payment, Notification | -         | RabbitMQ connection string   |
| `SECRET_KEY`   | Auth                           | -         | JWT signing secret           |
| `CORS_ORIGINS` | All                            | localhost | Allowed CORS origins         |

---

## Appendix A: Service Health Endpoints

All services expose `/health` endpoint returning:

```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T14:30:00Z"
}
```

## Appendix B: Prometheus Metrics

Each service exposes `/metrics` for Prometheus scraping:

| Metric                          | Type      | Description            |
| ------------------------------- | --------- | ---------------------- |
| `http_requests_total`           | Counter   | Total HTTP requests    |
| `http_request_duration_seconds` | Histogram | Request latency        |
| `bookings_total`                | Counter   | Total booking attempts |
| `payments_total`                | Counter   | Total payment attempts |
| `cache_hits_total`              | Counter   | Redis cache hits       |
| `circuit_breaker_state`         | Gauge     | Circuit breaker status |

---

**Document Version:** 2.0  
**Last Updated:** February 2, 2026
