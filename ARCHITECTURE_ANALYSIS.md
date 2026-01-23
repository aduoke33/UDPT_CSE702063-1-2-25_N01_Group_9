# 🏗️ System Architecture Analysis - Movie Booking System
## Corrected & Technically Rigorous Review

> ⚠️ **Methodology**: This analysis is based strictly on verified code in `docker-compose.yml`, service source files, and `init.sql`. No assumptions about unimplemented features.

---

## 📊 1. Verified System Architecture Diagram

```mermaid
flowchart TB
    subgraph External["🌐 External"]
        Client["👤 Client"]
    end

    subgraph Gateway["🚪 API Gateway"]
        NGINX["NGINX<br/>:80/:443"]
    end

    subgraph Services["🔧 Microservices (FastAPI)"]
        AS["🔐 Auth Service<br/>:8001"]
        MS["🎬 Movie Service<br/>:8002"]
        BS["🎫 Booking Service<br/>:8003"]
        PS["💳 Payment Service<br/>:8004"]
        NS["📧 Notification Service<br/>:8005"]
    end

    subgraph SharedDB["⚠️ SHARED DATABASE"]
        PG[("🐘 PostgreSQL<br/>:5432<br/>ALL TABLES")]
    end

    subgraph Cache["💾 Cache Layer"]
        RD[("⚡ Redis<br/>:6379<br/>DB 0-3")]
    end

    subgraph Queue["📬 Message Queue"]
        RMQ[("🐰 RabbitMQ<br/>:5672")]
    end

    Client --> NGINX
    NGINX --> AS & MS & BS & PS & NS

    %% ALL services connect to SAME database
    AS --> PG
    MS --> PG
    BS --> PG
    PS --> PG
    NS --> PG

    %% Redis connections
    AS -.-> RD
    MS -.-> RD
    BS -->|"Locks"| RD
    PS -->|"Idempotency"| RD

    %% Sync HTTP dependencies (CRITICAL)
    BS ==>|"⚠️ SYNC HTTP"| AS
    PS ==>|"⚠️ SYNC HTTP"| AS

    %% Async (only Payment → Notification)
    PS -->|"Publish"| RMQ
    RMQ -->|"Consume"| NS

    classDef critical fill:#e74c3c,stroke:#c0392b,color:white
    classDef warning fill:#f39c12,stroke:#d68910,color:white
    classDef normal fill:#3498db,stroke:#2980b9,color:white

    class PG critical
    class BS,PS warning
    class AS,MS,NS normal
```

---

## 📋 2. Verified Components

### Services & Ports (Confirmed from docker-compose.yml)

| Service | Internal | External | Verified Dependencies |
|---------|----------|----------|----------------------|
| NGINX Gateway | 80/443 | 80/443 | All services |
| Auth Service | 8000 | 8001 | PostgreSQL, Redis DB0 |
| Movie Service | 8000 | 8002 | PostgreSQL, Redis DB1 |
| Booking Service | 8000 | 8003 | PostgreSQL, Redis DB2, Auth(HTTP), RabbitMQ |
| Payment Service | 8000 | 8004 | PostgreSQL, Redis DB3, Auth(HTTP), RabbitMQ |
| Notification Service | 8000 | 8005 | PostgreSQL, RabbitMQ |

### Database Schema (from init.sql) - **CRITICAL FINDING**

```
┌─────────────────────────────────────────────────────────────────┐
│              SINGLE SHARED PostgreSQL DATABASE                  │
│                    "movie_booking"                              │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │   users     │  │   movies    │  │  theaters   │             │
│  │ (Auth Svc)  │  │ (Movie Svc) │  │ (Movie Svc) │             │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘             │
│         │                │                │                     │
│         │         ┌──────┴──────┐  ┌──────┴──────┐             │
│         │         │  showtimes  │  │    seats    │             │
│         │         │ (Movie Svc) │  │ (Movie Svc) │             │
│         │         └──────┬──────┘  └──────┬──────┘             │
│         │                │                │                     │
│  ┌──────┴────────────────┴────────────────┴──────┐             │
│  │                  bookings                      │             │
│  │              (Booking Service)                 │             │
│  │  FK: user_id, showtime_id                     │             │
│  └──────────────────┬────────────────────────────┘             │
│                     │                                           │
│  ┌──────────────────┼──────────────────┐                       │
│  │           booking_seats             │                       │
│  │   FK: booking_id, seat_id, showtime_id                     │
│  └──────────────────┬──────────────────┘                       │
│                     │                                           │
│  ┌──────────────────┴──────────────────┐                       │
│  │              payments               │  ┌─────────────┐      │
│  │         (Payment Service)           │  │notifications│      │
│  │    FK: booking_id                   │  │(Notif Svc)  │      │
│  └─────────────────────────────────────┘  │FK: user_id  │      │
│                                           └─────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🚨 3. Critical Architectural Issues (Severity Classified)

### 🔴 CRITICAL - Data Ownership Violation

| Issue | Evidence | Impact |
|-------|----------|--------|
| **Shared Database Anti-pattern** | All 5 services use `postgresql://admin:admin123@postgres:5432/movie_booking` | **Microservices fundamentally broken** |
| **Cross-service FK constraints** | `payments.booking_id` → `bookings.id`, `bookings.user_id` → `users.id` | Cannot deploy/scale services independently |
| **Schema coupling** | Payment Service directly queries `bookings` table (line 289-296 in payment main.py) | Service boundary violation |

**Code Evidence (payment-service/main.py:289-296):**
```python
# Payment Service DIRECTLY queries Booking table - WRONG
bookings_table = Table('bookings', metadata, autoload_with=engine.sync_engine)
result = await db.execute(
    select(bookings_table).where(
        bookings_table.c.id == payment_data.booking_id,
        bookings_table.c.user_id == uuid.UUID(current_user['user_id'])
    )
)
```

### 🔴 CRITICAL - Payment Consistency Risks

| Issue | Evidence | Risk |
|-------|----------|------|
| **Non-atomic payment + booking update** | Payment created, THEN booking status updated in separate transactions | Data inconsistency on partial failure |
| **No Saga/2PC pattern** | Payment success but booking update fails = orphaned payment | Money lost, no rollback |
| **Idempotency key in both Redis AND DB** | Dual storage without coordination | Race condition possible |

**Payment Flow Gap:**
```
1. Check idempotency (Redis) ←── Can fail here
2. Check idempotency (DB)    ←── Inconsistent with Redis
3. Create Payment record     ←── SUCCESS
4. Update Booking status     ←── FAILS = INCONSISTENT STATE
5. Publish to RabbitMQ       ←── Never happens
```

### 🔴 CRITICAL - Synchronous Dependency Chain

| Dependency | Code Location | Failure Mode |
|------------|---------------|--------------|
| Booking → Auth | booking-service/main.py:231-254 | Auth down = ALL bookings fail |
| Payment → Auth | payment-service/main.py:175-186 | Auth down = ALL payments fail |
| Booking → (Auth + Movie) | Sequential HTTP calls | 2x latency, 2x failure points |

**Actual Code (booking-service):**
```python
async def get_current_user(token: str = Depends(oauth2_scheme)):
    if not auth_circuit_breaker.can_execute():
        raise HTTPException(status_code=503, detail="Auth service temporarily unavailable")
    # BLOCKS until Auth responds
    response = await client.get(f"{AUTH_SERVICE_URL}/verify", ...)
```

### 🟠 HIGH - Redis Single Point of Failure

| Usage | Service | Failure Impact |
|-------|---------|----------------|
| Distributed Lock | Booking | Double-booking possible |
| Seat availability cache | Booking | Stale data = overselling |
| Idempotency cache | Payment | Duplicate payments |
| Session/Token cache | Auth | Auth failures |

**Redis Lock Implementation (booking-service/main.py:264-270):**
```python
async def acquire_lock(lock_name: str, expire_time: int = 10) -> bool:
    lock_key = f"lock:{lock_name}"
    acquired = await redis_client.set(lock_key, "locked", nx=True, ex=expire_time)
    return acquired is not None
# ⚠️ No Redlock, no quorum - Redis crash = lost locks
```

### 🟠 HIGH - Event-Driven Design Gaps

| Gap | Current State | Impact |
|-----|---------------|--------|
| **Only Payment→Notification is async** | Booking creates no events | Other services can't react to bookings |
| **No booking events** | Booking confirmed/cancelled not published | Payment must poll or sync call |
| **No retry mechanism for MQ** | publish_notification has no error handling | Lost notifications |

**RabbitMQ Publish (payment-service/main.py:191-200) - No error handling:**
```python
async def publish_notification(message: dict):
    if rabbitmq_channel:  # ⚠️ What if channel is None?
        await rabbitmq_channel.default_exchange.publish(...)
        # ⚠️ No try/catch, no retry, no DLQ
```

### 🟡 MEDIUM - Notification Service Responsibilities

| Issue | Evidence |
|-------|----------|
| **Direct DB writes for notifications** | Writes to shared `notifications` table |
| **User data access** | Needs to query `users` table for email |
| **No outbox pattern** | Notification state split between RabbitMQ and DB |

### 🟡 MEDIUM - Lock Contention

**Current Lock Scope:**
```python
lock_name = f"showtime:{showtime_id}"  # Locks ENTIRE showtime
```

**Problem**: 150-seat theater, 1 user booking = ALL 150 seats locked for 10 seconds. No concurrent bookings for same showtime.

---

## 4. Concrete Architectural Fixes

### Fix #1: Database Per Service (CRITICAL)

**Current:**
```
All Services → Single PostgreSQL (movie_booking)
```

**Fix:**
```yaml
# docker-compose.yml changes
services:
  postgres-auth:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: auth_db
    # Contains: users table only

  postgres-movies:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: movies_db
    # Contains: movies, theaters, showtimes, seats

  postgres-bookings:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: bookings_db
    # Contains: bookings, booking_seats (with denormalized user_id, showtime snapshot)

  postgres-payments:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: payments_db
    # Contains: payments (with denormalized booking snapshot)
```

**Trade-offs:**
| Pro | Con |
|-----|-----|
| Independent deployment | Data duplication |
| Isolated failures | Eventual consistency required |
| Scale per service | Cross-service queries need API calls |
| Clear ownership | Need event sync for denormalized data |

---

### Fix #2: Saga Pattern for Payment (CRITICAL)

**Current Flow (Broken):**
```
Payment Service:
1. Create payment → 2. Update booking → 3. Send notification
(If step 2 fails, payment exists but booking is stale)
```

**Fix - Choreography Saga:**
```python
# payment-service/main.py - Publish event, don't update booking directly
async def process_payment(...):
    # 1. Create payment with status='pending'
    new_payment = Payment(status='pending', ...)
    db.add(new_payment)
    await db.commit()
    
    # 2. Publish PaymentCompleted event (booking service listens)
    await publish_event("payment.completed", {
        "payment_id": str(new_payment.id),
        "booking_id": str(payment_data.booking_id),
        "amount": float(payment_data.amount)
    })
    
    # 3. Booking Service receives event, updates own DB
    # 4. Booking Service publishes "booking.confirmed"
    # 5. Notification Service receives, sends email
```

**Trade-offs:**
| Pro | Con |
|-----|-----|
| Atomic within each service | Eventually consistent |
| Recoverable via event replay | Requires compensation logic |
| Loosely coupled | More complex debugging |

---

### Fix #3: Remove Sync Auth Dependency (HIGH)

**Current:**
```python
# Every request to Booking/Payment
response = await client.get(f"{AUTH_SERVICE_URL}/verify", ...)
```

**Fix - JWT Local Validation:**
```python
# booking-service/main.py
from jose import jwt, JWTError

# Fetch public key on startup (cache it)
AUTH_PUBLIC_KEY = None

@app.on_event("startup")
async def fetch_auth_public_key():
    global AUTH_PUBLIC_KEY
    async with httpx.AsyncClient() as client:
        resp = await client.get(f"{AUTH_SERVICE_URL}/.well-known/jwks.json")
        AUTH_PUBLIC_KEY = resp.json()["keys"][0]

async def get_current_user(token: str = Depends(oauth2_scheme)):
    try:
        # LOCAL validation - no HTTP call
        payload = jwt.decode(token, AUTH_PUBLIC_KEY, algorithms=["RS256"])
        return {"user_id": payload["sub"], "email": payload["email"]}
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
```

**Trade-offs:**
| Pro | Con |
|-----|-----|
| No runtime dependency on Auth | Can't revoke tokens instantly |
| Much lower latency | Need token refresh mechanism |
| Auth can be down, bookings work | Public key rotation needed |

---

### Fix #4: Finer-Grained Locking (HIGH)

**Current:**
```python
lock_name = f"showtime:{showtime_id}"  # Blocks ALL seats
```

**Fix - Per-Seat Locking:**
```python
async def book_tickets(booking_data: BookingCreate, ...):
    seat_locks = []
    try:
        # Lock individual seats, not entire showtime
        for seat_id in booking_data.seat_ids:
            lock_key = f"lock:seat:{showtime_id}:{seat_id}"
            if not await redis_client.set(lock_key, "1", nx=True, ex=30):
                # Release already acquired locks
                for acquired in seat_locks:
                    await redis_client.delete(acquired)
                raise HTTPException(400, f"Seat {seat_id} is being booked")
            seat_locks.append(lock_key)
        
        # All seats locked, proceed with booking
        ...
    finally:
        # Release all locks
        for lock_key in seat_locks:
            await redis_client.delete(lock_key)
```

**Trade-offs:**
| Pro | Con |
|-----|-----|
| Concurrent bookings for same showtime | More Redis operations |
| Better throughput | Need atomic multi-key operations |
| Granular contention | Deadlock possible (mitigate with ordering) |

---

### Fix #5: Event-Driven Booking (MEDIUM)

**Current:** Only Payment publishes events.

**Fix:** All state changes emit events.

```python
# booking-service/main.py
EVENTS = {
    "booking.created": "bookings.created",
    "booking.confirmed": "bookings.confirmed",
    "booking.cancelled": "bookings.cancelled",
    "booking.expired": "bookings.expired"
}

async def create_booking(...):
    # ... create booking ...
    
    # Publish event for other services
    await publish_event("booking.created", {
        "booking_id": str(booking.id),
        "user_id": str(booking.user_id),
        "showtime_id": str(booking.showtime_id),
        "total_price": float(booking.total_price),
        "expires_at": booking.expires_at.isoformat()
    })
```

**Services can now react:**
- Payment Service: Listen for `booking.created` to show payment options
- Notification Service: Listen for `booking.created` to send reminder before expiry
- Analytics Service (future): Track booking patterns

---

## 5. Summary - Verified Issues Only

### What IS Implemented (Verified) ✅
- Circuit Breaker (booking-service only)
- Distributed Lock (Redis SET NX)
- Idempotency (Payment service)
- Correlation ID propagation
- Prometheus metrics exposure
- RabbitMQ async (Payment → Notification only)

### What is NOT Implemented ❌
- Database per service (all share one DB)
- Event sourcing
- Saga pattern
- Outbox pattern
- Redis cluster/sentinel
- Service mesh
- API versioning

### Priority Actions by Severity

| Priority | Action | Effort | Impact |
|----------|--------|--------|--------|
| 🔴 P0 | Split database per service | High | Enables true microservices |
| 🔴 P0 | Implement Saga for payment | Medium | Prevents data inconsistency |
| 🔴 P1 | Local JWT validation | Low | Removes sync dependency |
| 🟠 P1 | Per-seat locking | Medium | 10x throughput improvement |
| 🟠 P2 | Event-driven booking | Medium | Better decoupling |
| 🟡 P2 | Redis Sentinel | Medium | HA for locks |
| 🟡 P3 | Outbox pattern | High | Guaranteed event delivery |

---

## 🎯 6. TARGET Architecture

### 6.1 Target Architecture Diagram

```mermaid
flowchart TB
    subgraph External["🌐 External"]
        Client["👤 Client"]
    end

    subgraph Gateway["🚪 API Gateway"]
        ING["NGINX Ingress<br/>:80/:443<br/>+ Rate Limiting<br/>+ TLS"]
    end

    subgraph AuthDomain["🔐 Auth Domain"]
        AS["Auth Service<br/>:8001<br/>JWT Issuer + JWKS"]
        AS_DB[("auth_db<br/>users<br/>refresh_tokens")]
        AS -.->|"owns"| AS_DB
    end

    subgraph MovieDomain["🎬 Movie Domain"]
        MS["Movie Service<br/>:8002<br/>Catalog Owner"]
        MS_DB[("movies_db<br/>movies, theaters<br/>showtimes, seats<br/>seat_availability")]
        MS_RD[("Redis<br/>Movie Cache")]
        MS -.->|"owns"| MS_DB
        MS -.->|"cache"| MS_RD
    end

    subgraph BookingDomain["🎫 Booking Domain"]
        BS["Booking Service<br/>:8003<br/>Saga Orchestrator"]
        BS_DB[("bookings_db<br/>bookings<br/>booking_seats<br/>saga_state")]
        BS_RD[("Redis<br/>Seat Locks")]
        BS -.->|"owns"| BS_DB
        BS -.->|"locks"| BS_RD
    end

    subgraph PaymentDomain["💳 Payment Domain"]
        PS["Payment Service<br/>:8004<br/>Idempotent"]
        PS_DB[("payments_db<br/>payments<br/>transactions")]
        PS_RD[("Redis<br/>Idempotency")]
        PS -.->|"owns"| PS_DB
        PS -.->|"idempotency"| PS_RD
    end

    subgraph NotificationDomain["📧 Notification Domain"]
        NS["Notification Service<br/>:8005<br/>Multi-channel"]
        NS_DB[("notifications_db<br/>notifications<br/>templates")]
        NS -.->|"owns"| NS_DB
    end

    subgraph EventBus["📬 Event Bus"]
        RMQ[("RabbitMQ Cluster<br/>Exchange: domain.events<br/>+ DLQ + Retry")]
    end

    subgraph Observability["📈 Observability"]
        PROM["Prometheus"]
        GRAF["Grafana"]
        JAEGER["Jaeger<br/>Tracing"]
        LOKI["Loki<br/>Logs"]
    end

    %% Client to Gateway
    Client --> ING

    %% Gateway routes (JWT validated at gateway or locally)
    ING --> AS & MS & BS & PS & NS

    %% JWKS distribution (startup only, cached)
    AS -.->|"JWKS<br/>(cached at startup)"| MS & BS & PS & NS

    %% Event-driven communication (replaces sync calls)
    MS -->|"seat.reserved<br/>seat.released"| RMQ
    BS -->|"booking.created<br/>booking.confirmed<br/>booking.cancelled"| RMQ
    PS -->|"payment.completed<br/>payment.failed"| RMQ

    RMQ -->|"booking.*"| PS & NS
    RMQ -->|"payment.*"| BS & NS
    RMQ -->|"seat.*"| BS

    %% Observability
    AS & MS & BS & PS & NS -.->|"metrics"| PROM
    AS & MS & BS & PS & NS -.->|"traces"| JAEGER
    AS & MS & BS & PS & NS -.->|"logs"| LOKI
    PROM --> GRAF
    LOKI --> GRAF

    classDef domain fill:#3498db,stroke:#2980b9,color:white
    classDef db fill:#27ae60,stroke:#1e8449,color:white
    classDef cache fill:#e67e22,stroke:#d35400,color:white
    classDef event fill:#9b59b6,stroke:#8e44ad,color:white
    classDef observability fill:#1abc9c,stroke:#16a085,color:white

    class AS,MS,BS,PS,NS domain
    class AS_DB,MS_DB,BS_DB,PS_DB,NS_DB db
    class MS_RD,BS_RD,PS_RD cache
    class RMQ event
    class PROM,GRAF,JAEGER,LOKI observability
```

---

### 6.2 Major Changes Explained

| # | Change | Current → Target | Rationale |
|---|--------|------------------|-----------|
| 1 | **Database Isolation** | 1 shared DB → 5 isolated DBs | Each service owns its data; independent deployment |
| 2 | **Auth Decoupling** | Sync HTTP `/verify` → Local JWT (JWKS) | Remove runtime dependency on Auth Service |
| 3 | **Booking→Movie** | Sync HTTP → Event + Cache | Movie data cached locally; events for seat changes |
| 4 | **Payment→Booking** | Direct DB query → Event-driven Saga | No cross-service DB access; eventual consistency |
| 5 | **Redis Isolation** | Shared Redis (DB 0-3) → Per-service Redis | Fault isolation; no cross-service impact |
| 6 | **Event Bus** | Payment→Notification only → Full event mesh | All state changes emit events |
| 7 | **Observability** | Prometheus only → + Jaeger + Loki | Distributed tracing + centralized logs |

---

### 6.3 Synchronous Calls: Removed & Replaced

| Current Sync Call | How Replaced | New Flow |
|-------------------|--------------|----------|
| **Booking → Auth** (`/verify`) | Local JWT validation using JWKS | Auth publishes public key; services cache and validate locally |
| **Payment → Auth** (`/verify`) | Local JWT validation using JWKS | Same as above |
| **Booking → Movie** (get showtime) | Event + Local Cache | Movie publishes `showtime.updated`; Booking caches locally |
| **Payment → Bookings table** (direct query) | Event snapshot + Local storage | Booking publishes `booking.created` with data; Payment stores snapshot |

**Before (Sync Chain):**
```
Client → Booking → Auth (sync) → Movie (sync) → Redis → PostgreSQL
         ↓
         Payment → Auth (sync) → Bookings table (direct DB)
```

**After (Event-Driven):**
```
Client → Booking → Local JWT → Local Cache → Own DB
         ↓ publish
         RabbitMQ → Payment (reacts to booking.created)
                  → Notification (reacts to all events)
```

---

### 6.4 Data Ownership Boundaries

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        DATA OWNERSHIP MAP                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐     │
│  │   AUTH DOMAIN   │    │  MOVIE DOMAIN   │    │ BOOKING DOMAIN  │     │
│  │                 │    │                 │    │                 │     │
│  │  ✅ users       │    │  ✅ movies      │    │  ✅ bookings    │     │
│  │  ✅ refresh_    │    │  ✅ theaters    │    │  ✅ booking_    │     │
│  │     tokens      │    │  ✅ showtimes   │    │     seats       │     │
│  │                 │    │  ✅ seats       │    │  ✅ saga_state  │     │
│  │  Publishes:     │    │  ✅ seat_       │    │                 │     │
│  │  • JWKS         │    │     availability│    │  Stores:        │     │
│  │  • user.created │    │                 │    │  • showtime     │     │
│  │                 │    │  Publishes:     │    │    snapshot     │     │
│  │                 │    │  • seat.reserved│    │  • user_id      │     │
│  │                 │    │  • seat.released│    │    (from JWT)   │     │
│  │                 │    │  • showtime.    │    │                 │     │
│  │                 │    │    updated      │    │  Publishes:     │     │
│  │                 │    │                 │    │  • booking.*    │     │
│  └─────────────────┘    └─────────────────┘    └─────────────────┘     │
│                                                                         │
│  ┌─────────────────┐    ┌─────────────────┐                            │
│  │ PAYMENT DOMAIN  │    │ NOTIFICATION    │                            │
│  │                 │    │    DOMAIN       │                            │
│  │  ✅ payments    │    │                 │                            │
│  │  ✅ transactions│    │  ✅ notifications│                           │
│  │                 │    │  ✅ templates   │                            │
│  │  Stores:        │    │  ✅ delivery_log│                            │
│  │  • booking      │    │                 │                            │
│  │    snapshot     │    │  Stores:        │                            │
│  │  • user_id      │    │  • user_email   │                            │
│  │                 │    │    (from event) │                            │
│  │  Publishes:     │    │                 │                            │
│  │  • payment.*    │    │  Consumes ALL   │                            │
│  │                 │    │  domain events  │                            │
│  └─────────────────┘    └─────────────────┘                            │
│                                                                         │
│  Legend:                                                                │
│  ✅ = Owns (source of truth)                                           │
│  snapshot = Denormalized copy from event                               │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Key Principle**: No service queries another service's database. Data is shared via:
1. **Events** (state changes)
2. **Snapshots** (denormalized copies embedded in events)
3. **Cached references** (immutable IDs only)

---

### 6.5 New Event Flows

#### Flow 1: Booking Creation (Happy Path)

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant BS as Booking
    participant RMQ as RabbitMQ
    participant MS as Movie
    participant PS as Payment
    participant NS as Notification

    C->>BS: POST /bookings {seat_ids}
    Note over BS: Validate JWT locally
    Note over BS: Check seat cache
    BS->>BS: Create booking (pending)
    BS->>RMQ: 📤 booking.created
    BS-->>C: 201 {booking_id, expires_at}

    par Parallel Event Processing
        RMQ->>MS: 📥 booking.created
        MS->>MS: Reserve seats
        MS->>RMQ: 📤 seat.reserved

        RMQ->>PS: 📥 booking.created
        PS->>PS: Store booking snapshot

        RMQ->>NS: 📥 booking.created
        NS->>NS: Queue "pending payment" email
    end
```

#### Flow 2: Payment Completion

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant PS as Payment
    participant RMQ as RabbitMQ
    participant BS as Booking
    participant MS as Movie
    participant NS as Notification

    C->>PS: POST /payments {booking_id}
    Note over PS: Validate JWT locally
    Note over PS: Check idempotency
    PS->>PS: Process payment
    PS->>RMQ: 📤 payment.completed
    PS-->>C: 200 {payment_id}

    par Saga Completion
        RMQ->>BS: 📥 payment.completed
        BS->>BS: Update booking → confirmed
        BS->>RMQ: 📤 booking.confirmed

        RMQ->>MS: 📥 booking.confirmed
        MS->>MS: Confirm seat reservation

        RMQ->>NS: 📥 payment.completed
        NS->>NS: Send receipt email
    end

    RMQ->>NS: 📥 booking.confirmed
    NS->>NS: Send tickets email
```

#### Flow 3: Payment Failure (Compensation)

```mermaid
sequenceDiagram
    autonumber
    participant PS as Payment
    participant RMQ as RabbitMQ
    participant BS as Booking
    participant MS as Movie
    participant NS as Notification

    PS->>PS: Payment fails
    PS->>RMQ: 📤 payment.failed

    par Compensation Saga
        RMQ->>BS: 📥 payment.failed
        BS->>BS: Update booking → cancelled
        BS->>RMQ: 📤 booking.cancelled

        RMQ->>MS: 📥 booking.cancelled
        MS->>MS: Release seats
        MS->>RMQ: 📤 seat.released

        RMQ->>NS: 📥 payment.failed
        NS->>NS: Send failure email
    end
```

#### Event Schema Summary

| Event | Publisher | Consumers | Payload |
|-------|-----------|-----------|---------|
| `booking.created` | Booking | Payment, Movie, Notification | booking_id, user_id, user_email, showtime_snapshot, seat_ids, total_price, expires_at |
| `booking.confirmed` | Booking | Movie, Notification | booking_id, user_id |
| `booking.cancelled` | Booking | Movie, Payment, Notification | booking_id, user_id, reason |
| `booking.expired` | Booking | Movie, Payment, Notification | booking_id, user_id |
| `payment.completed` | Payment | Booking, Notification | payment_id, booking_id, amount, transaction_id |
| `payment.failed` | Payment | Booking, Notification | payment_id, booking_id, reason |
| `seat.reserved` | Movie | Booking | showtime_id, seat_ids, booking_id, expires_at |
| `seat.released` | Movie | Booking | showtime_id, seat_ids, reason |

---

### 6.6 Redis Safety Improvements

| Current | Target | Benefit |
|---------|--------|---------|
| Single Redis, DB 0-3 | 3 Redis instances (one per critical service) | Fault isolation |
| No Sentinel | Redis Sentinel (3-node) | Automatic failover |
| `SET NX EX` only | Redlock algorithm for critical locks | Quorum-based safety |
| No TTL monitoring | Key expiry alerts in Prometheus | Prevent stale locks |

**Target Redis Topology:**
```
┌─────────────────────────────────────────────────┐
│              Redis Sentinel Cluster             │
├─────────────────────────────────────────────────┤
│  ┌─────────┐   ┌─────────┐   ┌─────────┐       │
│  │Sentinel │   │Sentinel │   │Sentinel │       │
│  │   #1    │   │   #2    │   │   #3    │       │
│  └────┬────┘   └────┬────┘   └────┬────┘       │
│       │             │             │             │
│  ┌────▼────┐   ┌────▼────┐   ┌────▼────┐       │
│  │ Redis   │   │ Redis   │   │ Redis   │       │
│  │ Master  │◄──│ Replica │   │ Replica │       │
│  │(Booking)│   │         │   │         │       │
│  └─────────┘   └─────────┘   └─────────┘       │
│                                                 │
│  Separate instances for:                        │
│  • Movie cache (read-heavy, can lose data)     │
│  • Payment idempotency (critical, needs HA)    │
└─────────────────────────────────────────────────┘
```

---

### 6.7 Kubernetes Readiness

| Aspect | Implementation |
|--------|----------------|
| **Deployments** | Stateless services as `Deployment` with 2-3 replicas |
| **StatefulSets** | PostgreSQL and Redis as `StatefulSet` with PVCs |
| **ConfigMaps** | Service configs, NGINX conf |
| **Secrets** | DB credentials, JWT keys, RabbitMQ creds |
| **HPA** | Auto-scale on CPU (70%) and memory (80%) |
| **PodDisruptionBudget** | Min 1 pod available during rollouts |
| **NetworkPolicy** | Restrict inter-service communication |
| **Ingress** | NGINX Ingress with TLS termination |
| **Health Probes** | `/health` (liveness), `/health/ready` (readiness) |

---

### 6.8 Summary: Current vs Target

| Aspect | Current ❌ | Target ✅ |
|--------|-----------|----------|
| Database | 1 shared | 5 isolated |
| Auth dependency | Sync HTTP every request | Local JWT (JWKS cached) |
| Inter-service data | Direct DB queries | Events + snapshots |
| Redis | Shared, no HA | Per-service, Sentinel |
| Event coverage | Payment→Notification only | All state changes |
| Observability | Prometheus only | + Jaeger + Loki |
| Deployment | Docker Compose | Kubernetes-ready |
| Sync calls | 4 critical | 0 |
| Fault isolation | Poor (cascade failures) | Good (isolated domains) |

---

## 🚀 7. Migration Roadmap

### 7.0 Migration Principles

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      MIGRATION GROUND RULES                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  1. NO BIG BANG          Each phase is a complete, deployable unit     │
│  2. STRANGLE FIG         New behavior wraps old; gradual replacement   │
│  3. FEATURE FLAGS        Toggle between old/new paths instantly        │
│  4. DUAL WRITE/READ      Write to both, read from new, verify match    │
│  5. ROLLBACK READY       Every step has a tested rollback procedure    │
│  6. ZERO DOWNTIME        Blue-green or rolling deployments only        │
│  7. DATA INTEGRITY       Checksums and reconciliation at every step    │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.1 Migration Timeline Overview

```mermaid
gantt
    title Migration Roadmap (10 Weeks)
    dateFormat  YYYY-MM-DD
    
    section Phase 1
    Infrastructure Setup        :p1a, 2026-02-01, 5d
    Feature Flags Framework     :p1b, 2026-02-01, 3d
    Observability Stack         :p1c, after p1b, 4d
    
    section Phase 2
    Auth JWKS Endpoint          :p2a, after p1a, 3d
    Local JWT in Booking        :p2b, after p2a, 3d
    Local JWT in Payment        :p2c, after p2b, 2d
    Remove /verify endpoint     :p2d, after p2c, 2d
    
    section Phase 3
    Event Infrastructure        :p3a, after p2d, 3d
    Booking Publishes Events    :p3b, after p3a, 4d
    Movie Consumes Events       :p3c, after p3b, 3d
    Payment Consumes Events     :p3d, after p3c, 3d
    
    section Phase 4
    Create Separate DBs         :p4a, after p3d, 2d
    Auth DB Migration           :p4b, after p4a, 3d
    Movie DB Migration          :p4c, after p4b, 4d
    Booking DB Migration        :p4d, after p4c, 5d
    Payment DB Migration        :p4e, after p4d, 4d
    Notification DB Migration   :p4f, after p4e, 3d
    
    section Phase 5
    Redis Sentinel Setup        :p5a, after p4f, 3d
    Per-Service Redis           :p5b, after p5a, 4d
    
    section Phase 6
    Remove Legacy Code          :p6a, after p5b, 3d
    Kubernetes Deployment       :p6b, after p6a, 5d
    Production Cutover          :p6c, after p6b, 2d
```

---

### Phase 1: Foundation (Week 1)

#### 1.1 Infrastructure Setup

**Objective**: Prepare infrastructure without touching application code.

```yaml
# New services to add to docker-compose.yml (additive only)
services:
  # RabbitMQ with management (already exists, add clustering config)
  rabbitmq:
    image: rabbitmq:3-management-alpine
    environment:
      RABBITMQ_DEFAULT_USER: admin
      RABBITMQ_DEFAULT_PASS: admin123
    volumes:
      - ./rabbitmq/rabbitmq.conf:/etc/rabbitmq/rabbitmq.conf
      - ./rabbitmq/definitions.json:/etc/rabbitmq/definitions.json

  # Jaeger for distributed tracing
  jaeger:
    image: jaegertracing/all-in-one:1.50
    ports:
      - "16686:16686"  # UI
      - "6831:6831/udp"  # Thrift
      - "4317:4317"  # OTLP gRPC
    environment:
      COLLECTOR_OTLP_ENABLED: true

  # Loki for centralized logging
  loki:
    image: grafana/loki:2.9.0
    ports:
      - "3100:3100"
    volumes:
      - ./loki/loki-config.yml:/etc/loki/local-config.yaml

  # Feature flag service (simple Redis-based)
  feature-flags:
    image: redis:7-alpine
    ports:
      - "6380:6379"
```

**Rollback**: Remove new containers; zero impact on existing system.

**Risk Level**: 🟢 LOW - Additive only, no existing code changes.

---

#### 1.2 Feature Flags Framework

**Objective**: Enable runtime toggling of migration features.

```python
# shared/feature_flags.py (new file, add to all services)
import redis
from functools import lru_cache

FEATURE_FLAGS_URL = os.getenv("FEATURE_FLAGS_URL", "redis://feature-flags:6379/0")

class FeatureFlags:
    def __init__(self):
        self.redis = redis.from_url(FEATURE_FLAGS_URL)
        self._cache = {}
        self._cache_ttl = 30  # seconds
    
    def is_enabled(self, flag_name: str, default: bool = False) -> bool:
        """Check if feature flag is enabled (with local cache)"""
        cached = self._cache.get(flag_name)
        if cached and cached['expires'] > time.time():
            return cached['value']
        
        try:
            value = self.redis.get(f"flag:{flag_name}")
            result = value == b"true" if value else default
            self._cache[flag_name] = {
                'value': result,
                'expires': time.time() + self._cache_ttl
            }
            return result
        except Exception:
            return default

# Usage in services
feature_flags = FeatureFlags()

# Example flags we'll use:
# - use_local_jwt: Enable local JWT validation
# - use_new_auth_db: Read from auth_db instead of shared DB
# - publish_booking_events: Enable event publishing
# - consume_booking_events: Enable event consumption
```

**Flag Management Script:**
```bash
# scripts/feature_flags.sh
#!/bin/bash
REDIS_CLI="docker exec feature-flags redis-cli"

enable_flag() { $REDIS_CLI SET "flag:$1" "true"; }
disable_flag() { $REDIS_CLI SET "flag:$1" "false"; }
check_flag() { $REDIS_CLI GET "flag:$1"; }

# Usage: ./feature_flags.sh enable use_local_jwt
case "$1" in
  enable) enable_flag "$2" ;;
  disable) disable_flag "$2" ;;
  check) check_flag "$2" ;;
esac
```

**Rollback**: Disable all flags → system uses old paths.

**Risk Level**: 🟢 LOW - Code paths unchanged until flags enabled.

---

#### 1.3 Add Observability (Non-Intrusive)

**Objective**: Add tracing without changing business logic.

```python
# Add to each service's main.py (middleware approach)
from opentelemetry import trace
from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
from opentelemetry.instrumentation.httpx import HTTPXClientInstrumentor
from opentelemetry.instrumentation.sqlalchemy import SQLAlchemyInstrumentor
from opentelemetry.exporter.otlp.proto.grpc.trace_exporter import OTLPSpanExporter
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor

def setup_tracing(service_name: str):
    if not feature_flags.is_enabled("enable_tracing"):
        return
    
    provider = TracerProvider()
    processor = BatchSpanProcessor(OTLPSpanExporter(
        endpoint=os.getenv("JAEGER_ENDPOINT", "http://jaeger:4317")
    ))
    provider.add_span_processor(processor)
    trace.set_tracer_provider(provider)

# In app startup
@app.on_event("startup")
async def startup():
    setup_tracing("booking-service")
    FastAPIInstrumentor.instrument_app(app)
    HTTPXClientInstrumentor().instrument()
    SQLAlchemyInstrumentor().instrument(engine=engine.sync_engine)
```

**Rollback**: Disable `enable_tracing` flag.

**Risk Level**: 🟢 LOW - Observability is passive.

---

### Phase 2: Auth Decoupling (Week 2-3)

#### 2.1 Add JWKS Endpoint to Auth Service

**Objective**: Auth publishes public keys; other services can validate locally.

```python
# auth-service/app/jwks.py (new file)
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.backends import default_backend
import base64
import os

# Generate or load RSA key pair
PRIVATE_KEY_PATH = "/secrets/jwt_private_key.pem"
PUBLIC_KEY_PATH = "/secrets/jwt_public_key.pem"

def load_or_generate_keys():
    if os.path.exists(PRIVATE_KEY_PATH):
        with open(PRIVATE_KEY_PATH, "rb") as f:
            private_key = serialization.load_pem_private_key(f.read(), password=None)
        with open(PUBLIC_KEY_PATH, "rb") as f:
            public_key = serialization.load_pem_public_key(f.read())
    else:
        private_key = rsa.generate_private_key(
            public_exponent=65537,
            key_size=2048,
            backend=default_backend()
        )
        public_key = private_key.public_key()
        # Save keys (in production, use Vault)
        # ...
    return private_key, public_key

PRIVATE_KEY, PUBLIC_KEY = load_or_generate_keys()

def get_jwks():
    """Return JWKS for public key distribution"""
    public_numbers = PUBLIC_KEY.public_numbers()
    
    def b64_encode(num, length):
        return base64.urlsafe_b64encode(
            num.to_bytes(length, byteorder='big')
        ).decode('utf-8').rstrip('=')
    
    return {
        "keys": [{
            "kty": "RSA",
            "use": "sig",
            "alg": "RS256",
            "kid": "auth-key-v1",
            "n": b64_encode(public_numbers.n, 256),
            "e": b64_encode(public_numbers.e, 3)
        }]
    }
```

```python
# auth-service/app/main.py - Add endpoint (ADDITIVE)
from .jwks import get_jwks, PRIVATE_KEY

@app.get("/.well-known/jwks.json")
async def jwks_endpoint():
    """Public keys for JWT verification - cached heavily by clients"""
    return get_jwks()

# Update token creation to use RS256 (BEHIND FLAG)
def create_access_token(data: dict, expires_delta: timedelta = None):
    to_encode = data.copy()
    expire = datetime.utcnow() + (expires_delta or timedelta(minutes=30))
    to_encode.update({"exp": expire, "iat": datetime.utcnow()})
    
    if feature_flags.is_enabled("use_rs256_tokens"):
        # New: RS256 with private key
        return jwt.encode(to_encode, PRIVATE_KEY, algorithm="RS256")
    else:
        # Old: HS256 with shared secret (backward compatible)
        return jwt.encode(to_encode, SECRET_KEY, algorithm="HS256")
```

**Migration Steps:**
1. Deploy Auth with JWKS endpoint (flag disabled)
2. Test JWKS endpoint manually
3. Enable `use_rs256_tokens` for 10% of traffic
4. Monitor for errors
5. Gradually increase to 100%

**Backward Compatibility**: Old HS256 tokens still work until flag is 100%.

**Rollback**: Disable `use_rs256_tokens` → back to HS256.

**Risk Level**: 🟡 MEDIUM - Token format change affects all services.

---

#### 2.2 Local JWT Validation in Booking Service

**Objective**: Booking validates JWT locally instead of calling Auth.

```python
# booking-service/app/auth.py (new file)
import httpx
from jose import jwt, jwk, JWTError
from datetime import datetime, timedelta
import asyncio

class LocalJWTValidator:
    def __init__(self):
        self._jwks = None
        self._jwks_expires = None
        self._jwks_url = os.getenv("JWKS_URL", "http://auth-service:8000/.well-known/jwks.json")
        self._old_secret = os.getenv("JWT_SECRET_KEY")  # For backward compat
    
    async def _fetch_jwks(self):
        """Fetch and cache JWKS (refresh every hour)"""
        if self._jwks and self._jwks_expires > datetime.utcnow():
            return self._jwks
        
        async with httpx.AsyncClient() as client:
            resp = await client.get(self._jwks_url, timeout=5.0)
            resp.raise_for_status()
            self._jwks = resp.json()
            self._jwks_expires = datetime.utcnow() + timedelta(hours=1)
        return self._jwks
    
    async def validate_token(self, token: str) -> dict:
        """Validate JWT - try RS256 first, fallback to HS256"""
        # Try RS256 (new tokens)
        try:
            jwks = await self._fetch_jwks()
            key = jwk.construct(jwks["keys"][0])
            payload = jwt.decode(token, key, algorithms=["RS256"])
            return payload
        except Exception as e:
            pass  # Try fallback
        
        # Fallback to HS256 (old tokens during migration)
        try:
            payload = jwt.decode(token, self._old_secret, algorithms=["HS256"])
            return payload
        except JWTError:
            raise HTTPException(status_code=401, detail="Invalid token")

jwt_validator = LocalJWTValidator()
```

```python
# booking-service/app/main.py - Update get_current_user
async def get_current_user(token: str = Depends(oauth2_scheme)):
    if feature_flags.is_enabled("use_local_jwt"):
        # NEW PATH: Local validation
        payload = await jwt_validator.validate_token(token)
        return {"user_id": payload["sub"], "email": payload.get("email")}
    else:
        # OLD PATH: Call Auth service (existing code)
        if not auth_circuit_breaker.can_execute():
            raise HTTPException(status_code=503)
        response = await client.get(f"{AUTH_SERVICE_URL}/verify", ...)
        return response.json()
```

**Migration Steps:**
1. Deploy Booking with dual-path code (flag disabled)
2. Enable `use_local_jwt` for 5% of requests
3. Compare latency and error rates
4. Gradually increase to 100%

**Verification Query:**
```sql
-- Compare old vs new path results (add logging)
-- Log: { path: "local|remote", user_id, token_hash, result: "success|fail" }
```

**Rollback**: Disable `use_local_jwt` flag → back to HTTP calls.

**Risk Level**: 🟡 MEDIUM - Auth path is critical.

---

#### 2.3 Local JWT in Payment Service

**Objective**: Same as 2.2, for Payment service.

```python
# payment-service/app/main.py - Same pattern as Booking
# Copy jwt_validator implementation
# Add feature flag check in get_current_user
```

**Risk Level**: 🟡 MEDIUM - Same as Booking.

---

#### 2.4 Remove /verify Endpoint

**Objective**: Clean up old endpoint after 100% migration.

```python
# auth-service/app/main.py
@app.get("/verify")
async def verify_token(...):
    if feature_flags.is_enabled("disable_verify_endpoint"):
        # Return deprecation warning for monitoring
        logger.warning("Deprecated /verify endpoint called", extra={
            "caller_ip": request.client.host,
            "user_agent": request.headers.get("user-agent")
        })
    # ... existing logic
```

**Migration Steps:**
1. Enable deprecation logging
2. Wait 1 week, monitor for calls
3. If zero calls, remove endpoint
4. If calls exist, trace and fix callers

**Risk Level**: 🟢 LOW - Only after 100% local JWT adoption.

---

### Phase 3: Event Infrastructure (Week 4-5)

#### 3.1 RabbitMQ Event Infrastructure

**Objective**: Set up exchanges, queues, DLQ without changing app logic.

```json
// rabbitmq/definitions.json
{
  "exchanges": [
    {
      "name": "domain.events",
      "type": "topic",
      "durable": true
    },
    {
      "name": "domain.events.dlx",
      "type": "topic",
      "durable": true
    }
  ],
  "queues": [
    {
      "name": "booking-service.events",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "domain.events.dlx",
        "x-dead-letter-routing-key": "booking-service.dlq"
      }
    },
    {
      "name": "payment-service.events",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "domain.events.dlx"
      }
    },
    {
      "name": "notification-service.events",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "domain.events.dlx"
      }
    },
    {
      "name": "movie-service.events",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "domain.events.dlx"
      }
    }
  ],
  "bindings": [
    {"source": "domain.events", "destination": "booking-service.events", "routing_key": "payment.*"},
    {"source": "domain.events", "destination": "booking-service.events", "routing_key": "seat.*"},
    {"source": "domain.events", "destination": "payment-service.events", "routing_key": "booking.*"},
    {"source": "domain.events", "destination": "notification-service.events", "routing_key": "#"},
    {"source": "domain.events", "destination": "movie-service.events", "routing_key": "booking.*"}
  ]
}
```

**Risk Level**: 🟢 LOW - Infrastructure only.

---

#### 3.2 Event Publisher Library

**Objective**: Shared event publishing with outbox pattern.

```python
# shared/events/publisher.py
import aio_pika
import json
from datetime import datetime
import uuid

class EventPublisher:
    def __init__(self, service_name: str):
        self.service_name = service_name
        self.connection = None
        self.channel = None
        self.exchange = None
    
    async def connect(self):
        self.connection = await aio_pika.connect_robust(
            os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")
        )
        self.channel = await self.connection.channel()
        self.exchange = await self.channel.declare_exchange(
            "domain.events",
            aio_pika.ExchangeType.TOPIC,
            durable=True
        )
    
    async def publish(self, event_type: str, payload: dict, correlation_id: str = None):
        """Publish event with guaranteed delivery"""
        if not feature_flags.is_enabled(f"publish_{event_type.split('.')[0]}_events"):
            return  # Skip if publishing disabled
        
        event = {
            "event_id": str(uuid.uuid4()),
            "event_type": event_type,
            "timestamp": datetime.utcnow().isoformat(),
            "correlation_id": correlation_id or str(uuid.uuid4()),
            "source": self.service_name,
            "payload": payload
        }
        
        message = aio_pika.Message(
            body=json.dumps(event).encode(),
            content_type="application/json",
            delivery_mode=aio_pika.DeliveryMode.PERSISTENT,
            message_id=event["event_id"]
        )
        
        await self.exchange.publish(message, routing_key=event_type)
        
        # Log for debugging during migration
        logger.info(f"Published event", extra={
            "event_type": event_type,
            "event_id": event["event_id"],
            "correlation_id": event["correlation_id"]
        })
```

---

#### 3.3 Booking Service Publishes Events

**Objective**: Booking emits events on state changes (dual-write).

```python
# booking-service/app/main.py

event_publisher = EventPublisher("booking-service")

@app.on_event("startup")
async def startup():
    await event_publisher.connect()

async def create_booking(booking_data: BookingCreate, ...):
    # ... existing booking creation logic ...
    
    # NEW: Publish event (behind flag)
    if feature_flags.is_enabled("publish_booking_events"):
        await event_publisher.publish(
            "booking.created",
            {
                "booking_id": str(new_booking.id),
                "user_id": str(new_booking.user_id),
                "user_email": current_user["email"],
                "showtime_id": str(new_booking.showtime_id),
                "showtime_snapshot": {
                    "movie_title": showtime.movie.title,
                    "theater_name": showtime.theater.name,
                    "start_time": showtime.start_time.isoformat(),
                    "price": float(showtime.price)
                },
                "seat_ids": [str(s) for s in booking_data.seat_ids],
                "total_price": float(new_booking.total_price),
                "expires_at": new_booking.expires_at.isoformat()
            },
            correlation_id=request.headers.get("X-Correlation-ID")
        )
    
    return new_booking
```

**Verification**: 
- Monitor RabbitMQ queue depths
- Check event schema matches consumers' expectations
- No impact on booking success rate

**Rollback**: Disable `publish_booking_events` flag.

**Risk Level**: 🟢 LOW - Additive, existing flow unchanged.

---

#### 3.4 Payment Service Consumes Booking Events

**Objective**: Payment stores booking snapshots from events (dual-read).

```python
# payment-service/app/consumers.py
from shared.events import EventConsumer

class PaymentEventConsumer(EventConsumer):
    def __init__(self, db_session_factory):
        super().__init__("payment-service")
        self.db = db_session_factory
    
    async def handle_booking_created(self, event: dict):
        """Store booking snapshot for payment reference"""
        if not feature_flags.is_enabled("consume_booking_events"):
            return
        
        payload = event["payload"]
        
        async with self.db() as session:
            # Store snapshot (new table)
            snapshot = BookingSnapshot(
                booking_id=uuid.UUID(payload["booking_id"]),
                user_id=uuid.UUID(payload["user_id"]),
                total_price=payload["total_price"],
                showtime_snapshot=payload["showtime_snapshot"],
                expires_at=datetime.fromisoformat(payload["expires_at"]),
                event_id=event["event_id"]
            )
            session.add(snapshot)
            await session.commit()
        
        logger.info(f"Stored booking snapshot", extra={
            "booking_id": payload["booking_id"],
            "event_id": event["event_id"]
        })

# payment-service/app/main.py - Update payment processing
async def process_payment(payment_data: PaymentCreate, ...):
    if feature_flags.is_enabled("use_booking_snapshot"):
        # NEW: Use local snapshot
        snapshot = await get_booking_snapshot(payment_data.booking_id)
        if not snapshot:
            raise HTTPException(404, "Booking not found")
        booking_price = snapshot.total_price
    else:
        # OLD: Direct DB query (existing code)
        bookings_table = Table('bookings', metadata, autoload_with=engine.sync_engine)
        result = await db.execute(...)
        booking_price = result.total_price
    
    # ... rest of payment logic ...
```

**Dual-Read Verification:**
```python
# During migration, compare both paths
if feature_flags.is_enabled("verify_booking_snapshot"):
    snapshot_price = snapshot.total_price
    direct_price = await get_price_from_shared_db(booking_id)
    if snapshot_price != direct_price:
        logger.error("Snapshot mismatch!", extra={
            "booking_id": str(booking_id),
            "snapshot_price": snapshot_price,
            "direct_price": direct_price
        })
        # Use direct query as fallback
        booking_price = direct_price
```

**Migration Steps:**
1. Deploy consumer (flag disabled)
2. Enable `consume_booking_events` 
3. Wait for snapshots to accumulate
4. Enable `verify_booking_snapshot` (dual-read with comparison)
5. Monitor for mismatches
6. Enable `use_booking_snapshot` (read from snapshot)
7. Disable verification after stable period

**Rollback**: Disable `use_booking_snapshot` → back to direct DB query.

**Risk Level**: 🟠 HIGH - Data consistency critical for payments.

---

### Phase 4: Database Separation (Week 6-8)

#### 4.0 Database Migration Strategy

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    DATABASE MIGRATION PATTERN                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   Phase A: Setup         Phase B: Dual-Write    Phase C: Cutover       │
│   ┌─────────┐           ┌─────────┐            ┌─────────┐             │
│   │ Create  │           │ Write   │            │ Read    │             │
│   │ new DB  │     →     │ to both │     →      │ from    │             │
│   │ + schema│           │ DBs     │            │ new DB  │             │
│   └─────────┘           └─────────┘            └─────────┘             │
│                               │                      │                  │
│                               ▼                      ▼                  │
│                         ┌─────────┐            ┌─────────┐             │
│                         │ Sync    │            │ Remove  │             │
│                         │ checker │            │ old     │             │
│                         │ (cron)  │            │ writes  │             │
│                         └─────────┘            └─────────┘             │
│                                                                         │
│   Rollback: Disable flag → reads from old DB                           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

#### 4.1 Auth Database Migration

**Objective**: Move `users` and `refresh_tokens` to dedicated `auth_db`.

**Step 1: Create New Database**
```sql
-- Run on PostgreSQL server
CREATE DATABASE auth_db;
\c auth_db

-- Create schema (copy from init.sql, users table only)
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255),
    hashed_password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE refresh_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(500) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Copy existing data
INSERT INTO users SELECT * FROM movie_booking.users;
-- Note: refresh_tokens table may not exist in current schema
```

**Step 2: Dual-Write in Auth Service**
```python
# auth-service/app/database.py
class DualDatabaseSession:
    def __init__(self):
        self.old_engine = create_async_engine(OLD_DATABASE_URL)
        self.new_engine = create_async_engine(NEW_DATABASE_URL)
    
    async def add_user(self, user: User):
        # Always write to old DB
        async with AsyncSession(self.old_engine) as old_session:
            old_session.add(user)
            await old_session.commit()
        
        # Write to new DB if flag enabled
        if feature_flags.is_enabled("dual_write_auth_db"):
            try:
                async with AsyncSession(self.new_engine) as new_session:
                    new_session.add(user)
                    await new_session.commit()
            except Exception as e:
                logger.error(f"Dual-write failed: {e}")
                # Don't fail the request - old DB is source of truth
    
    async def get_user(self, user_id: UUID) -> User:
        if feature_flags.is_enabled("read_from_new_auth_db"):
            engine = self.new_engine
        else:
            engine = self.old_engine
        
        async with AsyncSession(engine) as session:
            return await session.get(User, user_id)
```

**Step 3: Data Sync Verification**
```python
# scripts/verify_auth_db_sync.py
async def verify_sync():
    """Run periodically to ensure DBs are in sync"""
    old_users = await get_all_users(old_engine)
    new_users = await get_all_users(new_engine)
    
    old_ids = {u.id for u in old_users}
    new_ids = {u.id for u in new_users}
    
    missing_in_new = old_ids - new_ids
    if missing_in_new:
        logger.error(f"Missing users in new DB: {missing_in_new}")
        # Sync missing users
        for user_id in missing_in_new:
            user = next(u for u in old_users if u.id == user_id)
            await copy_user_to_new_db(user)
    
    # Verify data integrity
    for user_id in old_ids & new_ids:
        old_user = next(u for u in old_users if u.id == user_id)
        new_user = next(u for u in new_users if u.id == user_id)
        if old_user.updated_at != new_user.updated_at:
            logger.warning(f"User {user_id} out of sync")
            await sync_user(old_user, new_user)
```

**Migration Steps:**
1. Create `auth_db` with schema
2. Copy existing data (one-time)
3. Deploy Auth with dual-write (flag disabled)
4. Enable `dual_write_auth_db`
5. Run sync verification for 48 hours
6. Enable `read_from_new_auth_db` for 10% traffic
7. Compare read results, monitor errors
8. Gradually increase to 100%
9. Disable writes to old DB
10. Remove old `users` table from shared DB

**Rollback**: Disable `read_from_new_auth_db` → reads from old DB.

**Risk Level**: 🟠 HIGH - Auth is critical path.

**Estimated Downtime**: ZERO (rolling deployment).

---

#### 4.2 Movie Database Migration

Same pattern as Auth. Tables: `movies`, `theaters`, `showtimes`, `seats`.

**Additional Complexity**: 
- Larger data volume
- Read-heavy workload
- Need to maintain FK integrity during migration

```sql
-- movies_db schema
CREATE DATABASE movies_db;
\c movies_db

CREATE TABLE movies (...);
CREATE TABLE theaters (...);
CREATE TABLE showtimes (...);
CREATE TABLE seats (...);

-- New table for event-driven seat management
CREATE TABLE seat_availability (
    showtime_id UUID NOT NULL,
    seat_id UUID NOT NULL,
    status VARCHAR(20) DEFAULT 'available',
    booking_id UUID,
    reserved_until TIMESTAMP,
    version INT DEFAULT 1,
    PRIMARY KEY (showtime_id, seat_id)
);
```

**Risk Level**: 🟡 MEDIUM - Read-heavy, less critical than Auth/Booking.

---

#### 4.3 Booking Database Migration

**Complexity**: Highest risk due to FK dependencies.

```sql
-- bookings_db schema
CREATE DATABASE bookings_db;
\c bookings_db

CREATE TABLE bookings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,  -- No FK to users (denormalized)
    showtime_id UUID NOT NULL,  -- No FK to showtimes (denormalized)
    showtime_snapshot JSONB NOT NULL,  -- Denormalized data
    status VARCHAR(50) DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE booking_seats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID REFERENCES bookings(id) ON DELETE CASCADE,
    seat_id UUID NOT NULL,  -- No FK to seats
    seat_snapshot JSONB NOT NULL,  -- Row, number, type
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Saga state tracking (new)
CREATE TABLE saga_state (
    booking_id UUID PRIMARY KEY REFERENCES bookings(id),
    current_step VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    compensations JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Data Migration Script:**
```python
# scripts/migrate_bookings.py
async def migrate_bookings():
    """One-time migration with snapshot creation"""
    old_bookings = await get_all_bookings(old_engine)
    
    for booking in old_bookings:
        # Fetch related data for snapshot
        showtime = await get_showtime(booking.showtime_id)
        movie = await get_movie(showtime.movie_id)
        theater = await get_theater(showtime.theater_id)
        
        # Create snapshot
        showtime_snapshot = {
            "movie_title": movie.title,
            "theater_name": theater.name,
            "start_time": showtime.start_time.isoformat(),
            "price": float(showtime.price)
        }
        
        # Insert into new DB with snapshot
        new_booking = BookingNew(
            id=booking.id,
            user_id=booking.user_id,
            showtime_id=booking.showtime_id,
            showtime_snapshot=showtime_snapshot,
            status=booking.status,
            total_price=booking.total_price,
            expires_at=booking.expires_at,
            created_at=booking.created_at
        )
        await insert_booking(new_engine, new_booking)
    
    logger.info(f"Migrated {len(old_bookings)} bookings")
```

**Risk Level**: 🔴 CRITICAL - Booking is core business flow.

---

#### 4.4 Payment Database Migration

```sql
CREATE DATABASE payments_db;
\c payments_db

CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL,  -- No FK
    booking_snapshot JSONB NOT NULL,  -- From booking.created event
    user_id UUID NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    payment_method VARCHAR(50),
    idempotency_key VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id UUID REFERENCES payments(id),
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    gateway_reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Booking snapshots received from events
CREATE TABLE booking_snapshots (
    booking_id UUID PRIMARY KEY,
    user_id UUID NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    showtime_snapshot JSONB NOT NULL,
    expires_at TIMESTAMP,
    event_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Risk Level**: 🔴 CRITICAL - Payment integrity is paramount.

---

#### 4.5 Notification Database Migration

```sql
CREATE DATABASE notifications_db;
\c notifications_db

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    user_email VARCHAR(255) NOT NULL,  -- Denormalized
    type VARCHAR(100) NOT NULL,
    channel VARCHAR(50) DEFAULT 'email',
    subject VARCHAR(500),
    content TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    sent_at TIMESTAMP,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type VARCHAR(100) UNIQUE NOT NULL,
    channel VARCHAR(50) NOT NULL,
    subject_template VARCHAR(500),
    body_template TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Risk Level**: 🟢 LOW - Non-critical, can tolerate some data loss.

---

### Phase 5: Redis Improvements (Week 9)

#### 5.1 Redis Sentinel Setup

```yaml
# docker-compose additions
services:
  redis-master:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_master_data:/data

  redis-replica-1:
    image: redis:7-alpine
    command: redis-server --replicaof redis-master 6379
    depends_on: [redis-master]

  redis-replica-2:
    image: redis:7-alpine
    command: redis-server --replicaof redis-master 6379
    depends_on: [redis-master]

  redis-sentinel-1:
    image: redis:7-alpine
    command: redis-sentinel /etc/redis/sentinel.conf
    volumes:
      - ./redis/sentinel.conf:/etc/redis/sentinel.conf
    depends_on: [redis-master, redis-replica-1, redis-replica-2]

  redis-sentinel-2:
    image: redis:7-alpine
    command: redis-sentinel /etc/redis/sentinel.conf
    volumes:
      - ./redis/sentinel.conf:/etc/redis/sentinel.conf
    depends_on: [redis-master]

  redis-sentinel-3:
    image: redis:7-alpine
    command: redis-sentinel /etc/redis/sentinel.conf
    volumes:
      - ./redis/sentinel.conf:/etc/redis/sentinel.conf
    depends_on: [redis-master]
```

```conf
# redis/sentinel.conf
sentinel monitor mymaster redis-master 6379 2
sentinel down-after-milliseconds mymaster 5000
sentinel failover-timeout mymaster 60000
sentinel parallel-syncs mymaster 1
```

**Migration Steps:**
1. Deploy Sentinel cluster alongside existing Redis
2. Update services to use Sentinel connection string
3. Test failover manually
4. Switch traffic to Sentinel cluster
5. Decommission old single Redis

**Risk Level**: 🟡 MEDIUM - Redis failure = booking/payment issues.

---

#### 5.2 Per-Service Redis Isolation

```python
# booking-service/app/config.py
REDIS_CONFIG = {
    "url": os.getenv("REDIS_URL", "redis://redis-booking-master:6379/0"),
    "sentinel": os.getenv("REDIS_SENTINEL", "redis-sentinel-1:26379,redis-sentinel-2:26379"),
    "sentinel_master": "booking-master"
}

# If using Sentinel
from redis.sentinel import Sentinel
sentinel = Sentinel([
    ('redis-sentinel-1', 26379),
    ('redis-sentinel-2', 26379),
    ('redis-sentinel-3', 26379)
])
redis_master = sentinel.master_for('booking-master')
```

---

### Phase 6: Cleanup & Kubernetes (Week 10)

#### 6.1 Remove Legacy Code

**Checklist:**
- [ ] Remove `/verify` endpoint from Auth
- [ ] Remove direct DB queries from Payment
- [ ] Remove HS256 token support (after all tokens expired)
- [ ] Remove dual-write code paths
- [ ] Remove old database connection strings
- [ ] Clean up feature flags

**Safe Removal Process:**
```python
# Before removing code, ensure flags show 100% new path
async def log_old_path_usage():
    # Log every time old path is hit
    logger.warning("OLD PATH STILL IN USE", extra={
        "path": "payment_direct_db_query",
        "request_id": request_id
    })

# After 7 days of zero logs, safe to remove
```

---

#### 6.2 Kubernetes Deployment

```yaml
# Final K8s manifest structure
kubernetes/
├── namespace.yaml
├── configmaps/
│   ├── auth-config.yaml
│   ├── movie-config.yaml
│   ├── booking-config.yaml
│   ├── payment-config.yaml
│   └── notification-config.yaml
├── secrets/
│   ├── db-credentials.yaml
│   ├── jwt-keys.yaml
│   └── rabbitmq-credentials.yaml
├── databases/
│   ├── auth-db-statefulset.yaml
│   ├── movies-db-statefulset.yaml
│   ├── bookings-db-statefulset.yaml
│   ├── payments-db-statefulset.yaml
│   └── notifications-db-statefulset.yaml
├── services/
│   ├── auth-service-deployment.yaml
│   ├── movie-service-deployment.yaml
│   ├── booking-service-deployment.yaml
│   ├── payment-service-deployment.yaml
│   └── notification-service-deployment.yaml
├── redis/
│   ├── redis-sentinel-statefulset.yaml
│   └── redis-services.yaml
├── rabbitmq/
│   └── rabbitmq-statefulset.yaml
├── ingress/
│   └── nginx-ingress.yaml
├── hpa/
│   └── autoscaling.yaml
└── network-policies/
    └── isolation.yaml
```

---

### 7.2 Risk Matrix

| Phase | Risk | Impact | Likelihood | Mitigation |
|-------|------|--------|------------|------------|
| P1 | Infra setup fails | Low | Low | Standard containers, well-tested |
| P2 | JWT validation bugs | High | Medium | Dual-path with fallback, gradual rollout |
| P2 | JWKS fetch failures | Medium | Low | Aggressive caching, retry logic |
| P3 | Event loss | Medium | Medium | DLQ, persistence, monitoring |
| P3 | Event schema mismatch | High | Medium | Schema registry, versioning |
| P4 | Data sync issues | Critical | Medium | Dual-read verification, checksums |
| P4 | Missing data in new DB | Critical | Low | One-time migration + ongoing sync |
| P4 | FK constraint violations | High | Medium | Remove FKs before migration |
| P5 | Redis failover issues | High | Low | Test failover in staging |
| P6 | K8s deployment issues | Medium | Medium | Blue-green, instant rollback |

---

### 7.3 Rollback Procedures

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      ROLLBACK DECISION TREE                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│                    Error Rate > 1% ?                                    │
│                          │                                              │
│                    ┌─────┴─────┐                                        │
│                    │           │                                        │
│                   YES         NO                                        │
│                    │           │                                        │
│                    ▼           ▼                                        │
│              Latency p99      Continue                                  │
│              > 500ms ?        Monitoring                                │
│                    │                                                    │
│              ┌─────┴─────┐                                              │
│              │           │                                              │
│             YES         NO                                              │
│              │           │                                              │
│              ▼           ▼                                              │
│         ROLLBACK     Business                                           │
│         IMMEDIATELY   Impact?                                           │
│              │           │                                              │
│              │     ┌─────┴─────┐                                        │
│              │     │           │                                        │
│              │   HIGH        LOW                                        │
│              │     │           │                                        │
│              │     ▼           ▼                                        │
│              │  ROLLBACK   Continue                                     │
│              │     │       + Alert                                      │
│              ▼     ▼                                                    │
│         ┌─────────────────────┐                                        │
│         │  ROLLBACK STEPS:    │                                        │
│         │  1. Disable flag    │ ← Instant (< 30s)                      │
│         │  2. Verify traffic  │                                        │
│         │     on old path     │                                        │
│         │  3. Investigate     │                                        │
│         │  4. Fix & retry     │                                        │
│         └─────────────────────┘                                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Rollback Commands:**
```bash
# Instant rollback via feature flags
./scripts/feature_flags.sh disable use_local_jwt
./scripts/feature_flags.sh disable read_from_new_auth_db
./scripts/feature_flags.sh disable use_booking_snapshot

# Verify rollback
curl -s http://localhost:8003/health | jq '.dependencies.auth_method'
# Should return: "http" (not "local_jwt")
```

---

### 7.4 Success Metrics

| Metric | Before Migration | Target | Measurement |
|--------|------------------|--------|-------------|
| Sync inter-service calls | 4 | 0 | Jaeger traces |
| Shared DB tables | 9 | 0 | Schema count |
| Mean booking latency | ~200ms | ~100ms | Prometheus |
| Auth service coupling | 100% | 0% | Circuit breaker trips |
| Event coverage | 1 flow | 5 flows | RabbitMQ metrics |
| Recovery time (Auth down) | Outage | 0 impact | Chaos testing |
| Data consistency errors | Unknown | 0 | Reconciliation jobs |

---

## 🎯 8. FINAL TARGET ARCHITECTURE (Production-Grade)

> **Purpose**: Definitive architecture specification for production deployment.  
> **Status**: APPROVED for implementation.

---

### 8.1 Target Architecture Diagram (Complete)

```mermaid
flowchart TB
    subgraph Internet["🌐 Internet"]
        Client["👤 Clients<br/>(Web/Mobile/API)"]
    end

    subgraph Edge["🛡️ Edge Layer"]
        CDN["CloudFlare CDN<br/>DDoS Protection"]
        WAF["WAF Rules"]
    end

    subgraph Gateway["🚪 API Gateway Layer"]
        subgraph LB["Load Balancer (HA)"]
            LB1["NGINX #1"]
            LB2["NGINX #2"]
        end
        RATE["Rate Limiter<br/>(Redis-backed)"]
    end

    subgraph Auth["🔐 AUTH DOMAIN"]
        direction TB
        AS1["Auth Service #1"]
        AS2["Auth Service #2"]
        AS_DB[("auth_db<br/>├─ users<br/>├─ refresh_tokens<br/>└─ audit_log")]
        JWKS["JWKS Endpoint<br/>(Public Keys)"]
        
        AS1 & AS2 -.->|"owns"| AS_DB
        AS1 & AS2 -->|"serves"| JWKS
    end

    subgraph Movie["🎬 MOVIE DOMAIN"]
        direction TB
        MS1["Movie Service #1"]
        MS2["Movie Service #2"]
        MS_DB[("movies_db<br/>├─ movies<br/>├─ theaters<br/>├─ showtimes<br/>├─ seats<br/>└─ seat_availability")]
        MS_RD[("Redis Cluster<br/>Movie Cache<br/>TTL: 5min")]
        
        MS1 & MS2 -.->|"owns"| MS_DB
        MS1 & MS2 -.->|"cache"| MS_RD
    end

    subgraph Booking["🎫 BOOKING DOMAIN"]
        direction TB
        BS1["Booking Service #1"]
        BS2["Booking Service #2"]
        BS3["Booking Service #3"]
        BS_DB[("bookings_db<br/>├─ bookings<br/>├─ booking_seats<br/>├─ saga_state<br/>└─ outbox")]
        BS_RD[("Redis Sentinel<br/>Distributed Locks<br/>TTL: 30s")]
        
        BS1 & BS2 & BS3 -.->|"owns"| BS_DB
        BS1 & BS2 & BS3 -->|"locks"| BS_RD
    end

    subgraph Payment["💳 PAYMENT DOMAIN"]
        direction TB
        PS1["Payment Service #1"]
        PS2["Payment Service #2"]
        PS_DB[("payments_db<br/>├─ payments<br/>├─ transactions<br/>├─ booking_snapshots<br/>└─ outbox")]
        PS_RD[("Redis Sentinel<br/>Idempotency Keys<br/>TTL: 24h")]
        
        PS1 & PS2 -.->|"owns"| PS_DB
        PS1 & PS2 -->|"idempotency"| PS_RD
    end

    subgraph Notification["📧 NOTIFICATION DOMAIN"]
        direction TB
        NS1["Notification Service #1"]
        NS2["Notification Service #2"]
        NS_DB[("notifications_db<br/>├─ notifications<br/>├─ templates<br/>└─ delivery_log")]
        
        NS1 & NS2 -.->|"owns"| NS_DB
    end

    subgraph EventBus["📬 EVENT BUS (HA)"]
        direction LR
        RMQ1["RabbitMQ #1"]
        RMQ2["RabbitMQ #2"]
        RMQ3["RabbitMQ #3"]
        
        RMQ1 <-->|"cluster"| RMQ2 <-->|"cluster"| RMQ3
    end

    subgraph Observability["📈 OBSERVABILITY STACK"]
        PROM["Prometheus<br/>(Metrics)"]
        GRAF["Grafana<br/>(Dashboards)"]
        JAEGER["Jaeger<br/>(Traces)"]
        LOKI["Loki<br/>(Logs)"]
        ALERT["Alertmanager<br/>(PagerDuty)"]
    end

    %% External flow
    Client --> CDN --> WAF --> LB
    LB1 & LB2 --> RATE

    %% Gateway to services (JWT validated locally)
    RATE --> AS1 & AS2
    RATE --> MS1 & MS2
    RATE --> BS1 & BS2 & BS3
    RATE --> PS1 & PS2
    RATE --> NS1 & NS2

    %% JWKS distribution (startup cache only)
    JWKS -.->|"cached<br/>on startup"| MS1 & MS2 & BS1 & BS2 & BS3 & PS1 & PS2 & NS1 & NS2

    %% Event-driven communication
    MS1 & MS2 -->|"seat.*"| RMQ1
    BS1 & BS2 & BS3 -->|"booking.*"| RMQ1
    PS1 & PS2 -->|"payment.*"| RMQ1

    RMQ1 -->|"booking.*"| PS1 & PS2 & NS1 & NS2
    RMQ1 -->|"payment.*"| BS1 & BS2 & BS3 & NS1 & NS2
    RMQ1 -->|"seat.*"| BS1 & BS2 & BS3

    %% Observability connections
    AS1 & AS2 & MS1 & MS2 & BS1 & BS2 & BS3 & PS1 & PS2 & NS1 & NS2 -.->|"metrics"| PROM
    AS1 & AS2 & MS1 & MS2 & BS1 & BS2 & BS3 & PS1 & PS2 & NS1 & NS2 -.->|"traces"| JAEGER
    AS1 & AS2 & MS1 & MS2 & BS1 & BS2 & BS3 & PS1 & PS2 & NS1 & NS2 -.->|"logs"| LOKI
    PROM --> GRAF
    PROM --> ALERT
    LOKI --> GRAF

    %% Styling
    classDef domain fill:#3498db,stroke:#2980b9,color:white
    classDef db fill:#27ae60,stroke:#1e8449,color:white
    classDef cache fill:#e67e22,stroke:#d35400,color:white
    classDef event fill:#9b59b6,stroke:#8e44ad,color:white
    classDef edge fill:#e74c3c,stroke:#c0392b,color:white
    classDef obs fill:#1abc9c,stroke:#16a085,color:white

    class AS1,AS2,MS1,MS2,BS1,BS2,BS3,PS1,PS2,NS1,NS2 domain
    class AS_DB,MS_DB,BS_DB,PS_DB,NS_DB db
    class MS_RD,BS_RD,PS_RD,RATE cache
    class RMQ1,RMQ2,RMQ3 event
    class CDN,WAF,LB1,LB2 edge
    class PROM,GRAF,JAEGER,LOKI,ALERT obs
```

---

### 8.2 Data Ownership Table

| Domain | Service | Owned Tables | Data Type | Storage | Backup |
|--------|---------|--------------|-----------|---------|--------|
| **Auth** | Auth Service | `users`, `refresh_tokens`, `audit_log` | Source of Truth | PostgreSQL `auth_db` | Daily + WAL |
| **Movie** | Movie Service | `movies`, `theaters`, `showtimes`, `seats`, `seat_availability` | Source of Truth | PostgreSQL `movies_db` | Daily + WAL |
| **Booking** | Booking Service | `bookings`, `booking_seats`, `saga_state`, `outbox` | Source of Truth | PostgreSQL `bookings_db` | Hourly + WAL |
| **Payment** | Payment Service | `payments`, `transactions`, `booking_snapshots`, `outbox` | Source of Truth + Snapshots | PostgreSQL `payments_db` | Hourly + WAL |
| **Notification** | Notification Service | `notifications`, `templates`, `delivery_log` | Source of Truth | PostgreSQL `notifications_db` | Daily |

#### Denormalized Data (Event-Sourced Snapshots)

| Service | Snapshot Table | Source Event | Data Included | Update Trigger |
|---------|----------------|--------------|---------------|----------------|
| Payment | `booking_snapshots` | `booking.created` | booking_id, user_id, user_email, showtime_snapshot, total_price, expires_at | Event consumption |
| Booking | `showtime_cache` | `showtime.updated` | showtime_id, movie_title, theater_name, start_time, price, available_seats | Event + TTL refresh |
| Notification | `user_preferences` | `user.created`, `user.updated` | user_id, email, phone, notification_settings | Event consumption |

---

### 8.3 Service Responsibility Matrix

| Responsibility | Auth | Movie | Booking | Payment | Notification |
|----------------|:----:|:-----:|:-------:|:-------:|:------------:|
| **User Registration** | ✅ | | | | |
| **User Authentication** | ✅ | | | | |
| **JWT Token Issuance** | ✅ | | | | |
| **JWKS Public Key Distribution** | ✅ | | | | |
| **JWT Token Validation** | | ✅ | ✅ | ✅ | ✅ |
| **Movie CRUD** | | ✅ | | | |
| **Theater Management** | | ✅ | | | |
| **Showtime Management** | | ✅ | | | |
| **Seat Layout Definition** | | ✅ | | | |
| **Seat Availability Tracking** | | ✅ | | | |
| **Booking Creation** | | | ✅ | | |
| **Seat Locking (Distributed)** | | | ✅ | | |
| **Booking Confirmation** | | | ✅ | | |
| **Booking Cancellation** | | | ✅ | | |
| **Saga Orchestration** | | | ✅ | | |
| **Payment Processing** | | | | ✅ | |
| **Idempotency Enforcement** | | | | ✅ | |
| **Refund Processing** | | | | ✅ | |
| **Transaction Logging** | | | | ✅ | |
| **Email Notifications** | | | | | ✅ |
| **SMS Notifications** | | | | | ✅ |
| **Push Notifications** | | | | | ✅ |
| **Notification Templating** | | | | | ✅ |

#### Cross-Cutting Concerns

| Concern | Implementation | Owner |
|---------|----------------|-------|
| **Rate Limiting** | Redis + NGINX | Gateway |
| **Circuit Breaking** | Per-service Resilience4j pattern | Each Service |
| **Distributed Tracing** | OpenTelemetry → Jaeger | Platform Team |
| **Structured Logging** | JSON logs → Loki | Platform Team |
| **Health Checks** | `/health`, `/health/ready` | Each Service |
| **Metrics Export** | Prometheus `/metrics` | Each Service |

---

### 8.4 Event Catalog (Complete)

#### 8.4.1 Event Schema Standard

```json
{
  "event_id": "uuid-v4",
  "event_type": "domain.action.status",
  "event_version": "1.0",
  "timestamp": "ISO-8601",
  "correlation_id": "uuid-v4",
  "causation_id": "uuid-v4 (parent event)",
  "source": "service-name",
  "payload": { /* event-specific data */ },
  "metadata": {
    "user_id": "uuid (if applicable)",
    "trace_id": "jaeger-trace-id"
  }
}
```

#### 8.4.2 Complete Event Definitions

| Event Type | Producer | Consumers | Trigger | Payload Schema |
|------------|----------|-----------|---------|----------------|
| **`user.created`** | Auth | Notification | User registration | `{ user_id, email, full_name, created_at }` |
| **`user.updated`** | Auth | Notification | Profile update | `{ user_id, changes: { field: new_value } }` |
| **`user.deleted`** | Auth | All Services | Account deletion | `{ user_id, deleted_at, anonymized: true }` |
| **`showtime.created`** | Movie | Booking | New showtime added | `{ showtime_id, movie_id, theater_id, start_time, end_time, price, total_seats }` |
| **`showtime.updated`** | Movie | Booking | Showtime modified | `{ showtime_id, changes: { field: new_value } }` |
| **`showtime.cancelled`** | Movie | Booking, Payment, Notification | Showtime cancelled | `{ showtime_id, reason, affected_bookings: [] }` |
| **`seat.reserved`** | Movie | Booking | Seats temporarily held | `{ showtime_id, seat_ids: [], booking_id, expires_at }` |
| **`seat.released`** | Movie | Booking | Seats made available | `{ showtime_id, seat_ids: [], reason: "expired\|cancelled" }` |
| **`seat.confirmed`** | Movie | — | Seats permanently booked | `{ showtime_id, seat_ids: [], booking_id }` |
| **`booking.created`** | Booking | Payment, Movie, Notification | New booking initiated | See detailed schema below |
| **`booking.confirmed`** | Booking | Movie, Notification | Payment successful | `{ booking_id, user_id, confirmed_at }` |
| **`booking.cancelled`** | Booking | Movie, Payment, Notification | User/system cancellation | `{ booking_id, user_id, reason, cancelled_at, refund_required: bool }` |
| **`booking.expired`** | Booking | Movie, Payment, Notification | Payment timeout | `{ booking_id, user_id, expired_at }` |
| **`payment.initiated`** | Payment | — | Payment started | `{ payment_id, booking_id, amount, method }` |
| **`payment.completed`** | Payment | Booking, Notification | Payment successful | See detailed schema below |
| **`payment.failed`** | Payment | Booking, Notification | Payment declined | `{ payment_id, booking_id, reason, failed_at }` |
| **`payment.refunded`** | Payment | Notification | Refund processed | `{ payment_id, booking_id, refund_amount, refunded_at }` |
| **`notification.sent`** | Notification | — | Notification delivered | `{ notification_id, user_id, channel, status }` |
| **`notification.failed`** | Notification | — | Delivery failed | `{ notification_id, user_id, channel, error, retry_count }` |

#### 8.4.3 Critical Event Payloads (Detailed)

**`booking.created`** (Most important event - triggers saga)
```json
{
  "event_id": "evt-123",
  "event_type": "booking.created",
  "event_version": "1.0",
  "timestamp": "2026-01-23T10:30:00Z",
  "correlation_id": "corr-456",
  "source": "booking-service",
  "payload": {
    "booking_id": "book-789",
    "user_id": "user-012",
    "user_email": "user@example.com",
    "showtime_id": "show-345",
    "showtime_snapshot": {
      "movie_id": "mov-111",
      "movie_title": "Dune: Part Three",
      "theater_id": "th-222",
      "theater_name": "CGV Vincom",
      "screen": "Screen 5",
      "start_time": "2026-01-24T19:00:00Z",
      "end_time": "2026-01-24T22:00:00Z",
      "base_price": 120000
    },
    "seats": [
      { "seat_id": "seat-a1", "row": "A", "number": 1, "price": 120000 },
      { "seat_id": "seat-a2", "row": "A", "number": 2, "price": 120000 }
    ],
    "total_price": 240000,
    "currency": "VND",
    "status": "pending_payment",
    "expires_at": "2026-01-23T10:45:00Z",
    "created_at": "2026-01-23T10:30:00Z"
  },
  "metadata": {
    "trace_id": "trace-abc",
    "client_ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0..."
  }
}
```

**`payment.completed`** (Triggers saga completion)
```json
{
  "event_id": "evt-456",
  "event_type": "payment.completed",
  "event_version": "1.0",
  "timestamp": "2026-01-23T10:32:00Z",
  "correlation_id": "corr-456",
  "causation_id": "evt-123",
  "source": "payment-service",
  "payload": {
    "payment_id": "pay-789",
    "booking_id": "book-789",
    "user_id": "user-012",
    "amount": 240000,
    "currency": "VND",
    "payment_method": "credit_card",
    "card_last_four": "4242",
    "transaction_id": "txn-gateway-123",
    "gateway": "stripe",
    "idempotency_key": "idem-key-abc",
    "completed_at": "2026-01-23T10:32:00Z"
  },
  "metadata": {
    "trace_id": "trace-abc",
    "processing_time_ms": 1250
  }
}
```

#### 8.4.4 Event Flow Diagram (Saga)

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant BS as Booking
    participant RMQ as RabbitMQ
    participant MS as Movie
    participant PS as Payment
    participant NS as Notification

    rect rgb(230, 245, 255)
        Note over C,NS: HAPPY PATH - Booking Success
        C->>BS: POST /bookings
        BS->>BS: Validate JWT (local)
        BS->>BS: Acquire seat locks (Redis)
        BS->>BS: Create booking (pending)
        BS->>RMQ: 📤 booking.created
        BS-->>C: 201 {booking_id, expires_at}
        
        par Parallel Processing
            RMQ->>MS: 📥 booking.created
            MS->>MS: Reserve seats in DB
            MS->>RMQ: 📤 seat.reserved
            
            RMQ->>PS: 📥 booking.created
            PS->>PS: Store booking snapshot
            
            RMQ->>NS: 📥 booking.created
            NS->>NS: Queue "awaiting payment" email
        end
        
        C->>PS: POST /payments
        PS->>PS: Check idempotency
        PS->>PS: Process payment
        PS->>RMQ: 📤 payment.completed
        PS-->>C: 200 {payment_id}
        
        par Saga Completion
            RMQ->>BS: 📥 payment.completed
            BS->>BS: Update booking → confirmed
            BS->>BS: Release seat locks
            BS->>RMQ: 📤 booking.confirmed
            
            RMQ->>MS: 📥 booking.confirmed
            MS->>MS: Confirm seat reservation
            
            RMQ->>NS: 📥 payment.completed
            NS->>NS: Send receipt email
        end
        
        RMQ->>NS: 📥 booking.confirmed
        NS->>NS: Send e-tickets
    end

    rect rgb(255, 230, 230)
        Note over C,NS: COMPENSATION - Payment Failure
        PS->>PS: Payment fails
        PS->>RMQ: 📤 payment.failed
        
        par Compensation Saga
            RMQ->>BS: 📥 payment.failed
            BS->>BS: Update booking → cancelled
            BS->>BS: Release seat locks
            BS->>RMQ: 📤 booking.cancelled
            
            RMQ->>MS: 📥 booking.cancelled
            MS->>MS: Release seats
            MS->>RMQ: 📤 seat.released
            
            RMQ->>NS: 📥 payment.failed
            NS->>NS: Send failure notification
        end
    end
```

---

### 8.5 Remaining Synchronous APIs (Justified)

| API Call | From → To | Justification | Mitigation |
|----------|-----------|---------------|------------|
| **JWKS Fetch** | All Services → Auth | One-time on startup, cached 1 hour | Local cache, retry with backoff, fallback to embedded key |
| **Health Check** | K8s → All Services | Required for orchestration | Stateless, fast (<100ms) |
| **Metrics Scrape** | Prometheus → All Services | Pull-based monitoring standard | Non-blocking, separate endpoint |

**Eliminated Synchronous Calls:**

| Previous Call | Replacement | Benefit |
|---------------|-------------|---------|
| Booking → Auth `/verify` | Local JWT validation | -50ms latency, no SPOF |
| Payment → Auth `/verify` | Local JWT validation | -50ms latency, no SPOF |
| Payment → Bookings DB | Event snapshot | No cross-service DB |
| Booking → Movie (showtimes) | Cached + Event updates | -30ms latency, eventual consistency |

---

### 8.6 High Availability Strategy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        HIGH AVAILABILITY MATRIX                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Component          Replicas    HA Strategy              RTO      RPO      │
│  ─────────────────────────────────────────────────────────────────────────  │
│  API Gateway        2+          Active-Active LB         0        N/A      │
│  Auth Service       2+          Stateless, LB            0        N/A      │
│  Movie Service      2+          Stateless, LB            0        N/A      │
│  Booking Service    3+          Stateless, LB            0        N/A      │
│  Payment Service    2+          Stateless, LB            0        N/A      │
│  Notification Svc   2+          Stateless, LB            0        N/A      │
│  PostgreSQL         1+1         Primary-Replica          <1min    0        │
│  Redis Sentinel     3           Sentinel + Replicas      <30s     ~1s      │
│  RabbitMQ           3           Quorum Queues            0        0        │
│  Prometheus         2           Thanos/HA pair           <1min    N/A      │
│  Grafana            2           Shared DB backend        <1min    N/A      │
│                                                                             │
│  Legend:                                                                    │
│  RTO = Recovery Time Objective (time to restore service)                   │
│  RPO = Recovery Point Objective (acceptable data loss)                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Database HA (PostgreSQL)

```yaml
# Each service's database
Primary (Read/Write)
    │
    ├── Synchronous Replica (Hot Standby, same AZ)
    │       └── Automatic failover via Patroni
    │
    └── Async Replica (Disaster Recovery, different AZ)
            └── Manual promotion if primary AZ fails

# Connection string with failover
postgresql://user:pass@pgbouncer:5432/db?target_session_attrs=read-write
```

#### Redis HA (Sentinel)

```
┌─────────────────────────────────────────┐
│           Redis Sentinel (3 nodes)      │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│  │Sentinel1│ │Sentinel2│ │Sentinel3│   │
│  └────┬────┘ └────┬────┘ └────┬────┘   │
│       │          │          │          │
│       └──────────┼──────────┘          │
│                  ▼                      │
│       ┌─────────────────────┐          │
│       │    Redis Master     │          │
│       │  (Booking Locks)    │          │
│       └──────────┬──────────┘          │
│                  │                      │
│       ┌──────────┼──────────┐          │
│       ▼          ▼          ▼          │
│  ┌─────────┐ ┌─────────┐               │
│  │Replica 1│ │Replica 2│               │
│  └─────────┘ └─────────┘               │
└─────────────────────────────────────────┘
```

#### RabbitMQ HA (Quorum Queues)

```yaml
# Quorum queue configuration
queues:
  booking-service.events:
    type: quorum
    arguments:
      x-quorum-initial-group-size: 3
      x-dead-letter-exchange: domain.events.dlx
      x-message-ttl: 86400000  # 24h
```

---

### 8.7 Security Boundaries

```mermaid
flowchart TB
    subgraph Public["🌐 PUBLIC ZONE (Untrusted)"]
        Internet["Internet Traffic"]
    end

    subgraph DMZ["🛡️ DMZ (Semi-Trusted)"]
        WAF["WAF"]
        LB["Load Balancer"]
    end

    subgraph App["🔒 APPLICATION ZONE (Trusted)"]
        subgraph Services["Service Mesh"]
            AS["Auth"]
            MS["Movie"]
            BS["Booking"]
            PS["Payment"]
            NS["Notification"]
        end
    end

    subgraph Data["🔐 DATA ZONE (Restricted)"]
        DB["Databases"]
        Redis["Redis"]
        MQ["RabbitMQ"]
    end

    subgraph Mgmt["⚙️ MANAGEMENT ZONE (Admin Only)"]
        Vault["HashiCorp Vault"]
        K8s["K8s API"]
        CI["CI/CD"]
    end

    Internet -->|"HTTPS only"| WAF
    WAF -->|"Filtered"| LB
    LB -->|"mTLS"| Services
    Services -->|"mTLS"| Data
    Mgmt -.->|"Admin VPN"| Services & Data

    style Public fill:#e74c3c
    style DMZ fill:#f39c12
    style App fill:#3498db
    style Data fill:#27ae60
    style Mgmt fill:#9b59b6
```

#### Security Controls by Zone

| Zone | Controls | Authentication | Encryption |
|------|----------|----------------|------------|
| **Public** | DDoS protection, Rate limiting | N/A | TLS 1.3 |
| **DMZ** | WAF rules, IP filtering | API Keys (optional) | TLS 1.3 |
| **Application** | Network policies, RBAC | JWT (RS256) | mTLS |
| **Data** | Firewall, ACLs | Service accounts | TLS + Encryption at rest |
| **Management** | VPN, MFA, Audit logs | SSO + MFA | mTLS + SSH |

#### JWT Security

```python
# Token configuration
JWT_CONFIG = {
    "algorithm": "RS256",
    "access_token_ttl": 900,      # 15 minutes
    "refresh_token_ttl": 604800,  # 7 days
    "issuer": "auth-service",
    "audience": ["movie-booking-api"],
    "key_rotation": "monthly",
}

# Required claims
REQUIRED_CLAIMS = ["sub", "iat", "exp", "iss", "aud", "roles"]
```

---

### 8.8 Kubernetes Architecture

```yaml
# Namespace structure
namespaces:
  - movie-booking-prod     # Application workloads
  - movie-booking-data     # StatefulSets (DB, Redis, MQ)
  - movie-booking-monitor  # Observability stack
  - movie-booking-ingress  # Ingress controllers

# Resource allocation per service
resources:
  auth-service:
    replicas: 2
    requests: { cpu: 100m, memory: 256Mi }
    limits: { cpu: 500m, memory: 512Mi }
    hpa: { min: 2, max: 5, cpu: 70% }
    
  booking-service:
    replicas: 3
    requests: { cpu: 200m, memory: 512Mi }
    limits: { cpu: 1000m, memory: 1Gi }
    hpa: { min: 3, max: 10, cpu: 70% }
    
  payment-service:
    replicas: 2
    requests: { cpu: 200m, memory: 512Mi }
    limits: { cpu: 1000m, memory: 1Gi }
    hpa: { min: 2, max: 8, cpu: 70% }
```

#### Deployment Strategy

| Service | Strategy | Max Surge | Max Unavailable | PDB Min Available |
|---------|----------|-----------|-----------------|-------------------|
| Auth | RollingUpdate | 1 | 0 | 1 |
| Movie | RollingUpdate | 25% | 0 | 1 |
| Booking | RollingUpdate | 1 | 0 | 2 |
| Payment | RollingUpdate | 1 | 0 | 1 |
| Notification | RollingUpdate | 50% | 0 | 1 |

---

### 8.9 Observability Stack

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         OBSERVABILITY ARCHITECTURE                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                   │
│  │   METRICS   │     │    LOGS     │     │   TRACES    │                   │
│  │             │     │             │     │             │                   │
│  │ Prometheus  │     │    Loki     │     │   Jaeger    │                   │
│  │    ▲        │     │    ▲        │     │    ▲        │                   │
│  │    │        │     │    │        │     │    │        │                   │
│  │  scrape     │     │  push       │     │  OTLP       │                   │
│  └────┼────────┘     └────┼────────┘     └────┼────────┘                   │
│       │                   │                   │                             │
│       └───────────────────┼───────────────────┘                             │
│                           │                                                 │
│              ┌────────────┴────────────┐                                   │
│              │    OpenTelemetry SDK    │                                   │
│              │   (All Services)        │                                   │
│              └────────────┬────────────┘                                   │
│                           │                                                 │
│              ┌────────────┴────────────┐                                   │
│              │        Grafana          │◄──── Alertmanager ──► PagerDuty  │
│              │   (Unified Dashboard)   │                                   │
│              └─────────────────────────┘                                   │
│                                                                             │
│  Key Dashboards:                                                           │
│  ├─ Service Health Overview (all services)                                 │
│  ├─ Booking Flow (end-to-end latency)                                     │
│  ├─ Payment Metrics (success rate, latency)                               │
│  ├─ Event Bus Health (queue depths, DLQ)                                  │
│  ├─ Database Performance (connections, queries)                           │
│  └─ Redis Operations (locks, cache hits)                                  │
│                                                                             │
│  Key Alerts:                                                               │
│  ├─ Error rate > 1% for 5 minutes                                         │
│  ├─ P99 latency > 500ms for 5 minutes                                     │
│  ├─ Payment success rate < 99% for 2 minutes                              │
│  ├─ DLQ messages > 0                                                      │
│  ├─ Database connection pool > 80%                                        │
│  └─ Redis master unavailable                                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### 8.10 Architecture Decision Records (ADR) Summary

| ADR | Decision | Rationale | Trade-off |
|-----|----------|-----------|-----------|
| **ADR-001** | Database per service | True microservices, independent deployment | Data duplication, eventual consistency |
| **ADR-002** | Event-driven Saga (Choreography) | Loose coupling, no central coordinator | Complex debugging, eventual consistency |
| **ADR-003** | Local JWT validation | Remove sync dependency on Auth | Cannot instant-revoke tokens |
| **ADR-004** | Redis Sentinel for locks | HA for critical booking locks | More infrastructure complexity |
| **ADR-005** | RabbitMQ Quorum Queues | Message durability guarantee | Higher latency than mirrored queues |
| **ADR-006** | Outbox pattern for events | Guaranteed event delivery | Additional table, background worker |
| **ADR-007** | Per-seat locking | Concurrent bookings for same showtime | More Redis operations |
| **ADR-008** | Denormalized snapshots | No cross-service queries | Data staleness possible |

---

### 8.11 Final Checklist

```
✅ ARCHITECTURE REQUIREMENTS

Database Isolation
├── [x] Auth owns auth_db (users, tokens)
├── [x] Movie owns movies_db (catalog, availability)
├── [x] Booking owns bookings_db (bookings, saga state)
├── [x] Payment owns payments_db (payments, snapshots)
└── [x] Notification owns notifications_db (notifications)

Event-Driven Communication
├── [x] All state changes emit events
├── [x] Event schema standardized
├── [x] DLQ for failed messages
├── [x] Idempotent consumers
└── [x] Saga pattern for distributed transactions

Minimal Sync Dependencies
├── [x] Auth: JWKS only (cached)
├── [x] No runtime HTTP between services
└── [x] Health checks only for orchestration

Redis Isolation
├── [x] Booking: Sentinel for locks
├── [x] Payment: Sentinel for idempotency
├── [x] Movie: Cluster for cache
└── [x] No shared Redis between services

High Availability
├── [x] All services: 2+ replicas
├── [x] PostgreSQL: Primary + Replica
├── [x] Redis: Sentinel (3 nodes)
├── [x] RabbitMQ: Cluster (3 nodes)
└── [x] Gateway: Active-Active

Observability
├── [x] Metrics: Prometheus + Grafana
├── [x] Logs: Loki (structured JSON)
├── [x] Traces: Jaeger (OpenTelemetry)
├── [x] Alerts: Alertmanager → PagerDuty
└── [x] Dashboards: Service health, Booking flow, Payments

Kubernetes Readiness
├── [x] Stateless services as Deployments
├── [x] HPA for auto-scaling
├── [x] PDB for availability during updates
├── [x] Network policies for isolation
├── [x] Health probes (liveness + readiness)
└── [x] Resource limits defined

Security Boundaries
├── [x] JWT RS256 with JWKS rotation
├── [x] mTLS between services (K8s)
├── [x] Network policies per namespace
├── [x] Secrets in Vault/K8s secrets
├── [x] TLS everywhere
└── [x] Rate limiting at gateway
```

---

*Final Architecture v2.0 - Movie Booking Distributed System*  
*CSE702063 - January 2026*  
*Approved for Implementation*
