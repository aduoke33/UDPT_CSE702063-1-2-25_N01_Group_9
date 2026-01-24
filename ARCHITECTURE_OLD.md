# Architecture Documentation

## Movie Booking Distributed System

**Version**: 2.0  
**Last Updated**: January 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [Service Architecture](#3-service-architecture)
4. [Data Architecture](#4-data-architecture)
5. [Communication Patterns](#5-communication-patterns)
6. [Infrastructure](#6-infrastructure)

---

## 1. Introduction

### 1.1 Purpose

This document describes the architecture of the Movie Booking System - a distributed application for online movie ticket reservations.

### 1.2 System Goals

| Goal           | Target          | Description                           |
|----------------|-----------------|---------------------------------------|
| Availability   | 99.9% uptime    | Users can book tickets anytime        |
| Scalability    | 10,000 users    | Handle peak hours (movie releases)    |
| Resilience     | Zero data loss  | No lost bookings or payments          |
| Performance    | < 200ms         | Fast response times                   |

### 1.3 Why Microservices?

```
MONOLITH                          MICROSERVICES (Our Approach)
+---------------------+           +-----+ +-----+ +-----+
|                     |           |Auth | |Movie| |Book |
|   ALL CODE IN ONE   |           +--+--+ +--+--+ +--+--+
|    APPLICATION      |   -->        |      |      |
|                     |           +--+--+ +--+--+
+---------------------+           | Pay | |Notif|
                                  +-----+ +-----+

Monolith Issues:                  Microservices Benefits:
- One bug crashes all             - Failures are isolated
- Deploy all at once              - Deploy independently
- Hard to scale parts             - Scale what's needed
- Single point of failure         - Better fault tolerance
```

---

## 2. System Overview

### 2.1 High-Level Architecture

```
                                 +------------------+
                                 |     CLIENTS      |
                                 | (Web/Mobile/API) |
                                 +--------+---------+
                                          |
                                          | HTTPS
                                          v
+-------------------------------------------------------------------------+
|                           API GATEWAY (NGINX)                           |
|                                                                         |
|   - Routes requests to correct service                                  |
|   - SSL/TLS termination                                                 |
|   - Rate limiting                                                       |
|   - Load balancing                                                      |
+-------------------------------------------------------------------------+
                                          |
         +----------------+---------------+---------------+----------------+
         |                |               |               |                |
         v                v               v               v                v
+--------+------+ +-------+------+ +------+-------+ +-----+-------+ +------+-------+
|     AUTH      | |    MOVIE     | |   BOOKING    | |   PAYMENT   | | NOTIFICATION |
|    SERVICE    | |   SERVICE    | |   SERVICE    | |   SERVICE   | |    SERVICE   |
|               | |              | |              | |             | |              |
| - Register    | | - Movies     | | - Reserve    | | - Charge    | | - Email      |
| - Login       | | - Showtimes  | | - Lock seats | | - Refund    | | - SMS        |
| - JWT tokens  | | - Theaters   | | - Cancel     | | - History   | | - Push       |
+-------+-------+ +------+-------+ +------+-------+ +------+------+ +-------+------+
        |                |               |               |                 |
        +----------------+-------+-------+---------------+-----------------+
                                 |
        +------------------------+------------------------+
        |                        |                        |
        v                        v                        v
+-------+--------+      +--------+-------+       +--------+-------+
|   POSTGRESQL   |      |     REDIS      |       |   RABBITMQ     |
|   (Database)   |      |    (Cache)     |       |   (Messages)   |
|                |      |                |       |                |
| - Users        |      | - Seat locks   |       | - Events       |
| - Movies       |      | - Sessions     |       | - Async tasks  |
| - Bookings     |      | - Rate limits  |       | - Notifications|
| - Payments     |      |                |       |                |
+----------------+      +----------------+       +----------------+
```

### 2.2 Request Flow: Booking Tickets

```
User                Gateway        Auth        Booking       Redis       Payment      Notify
 |                    |             |            |            |            |            |
 |  1. Login          |             |            |            |            |            |
 |------------------->|------------>|            |            |            |            |
 |                    |<------------|            |            |            |            |
 |<---JWT Token-------|             |            |            |            |            |
 |                    |             |            |            |            |            |
 |  2. Book seats     |             |            |            |            |            |
 |------------------->|------------------------->|            |            |            |
 |                    |             |            |            |            |            |
 |                    |             |    3. Lock seats        |            |            |
 |                    |             |            |----------->|            |            |
 |                    |             |            |<--Locked---|            |            |
 |                    |             |            |            |            |            |
 |<--Booking Created--|--------------------------|            |            |            |
 |                    |             |            |            |            |            |
 |  4. Pay            |             |            |            |            |            |
 |------------------------------------------------------------------------>|            |
 |                    |             |            |            |            |            |
 |                    |             |            |   5. Confirm            |            |
 |                    |             |            |<------------------------|            |
 |                    |             |            |            |            |            |
 |                    |             |            |   6. Send email         |            |
 |                    |             |            |------------------------------------->|
 |                    |             |            |            |            |            |
 |<------------------------------------------------Email Sent---------------------------|
```

---

## 3. Service Architecture

### 3.1 Auth Service

**Responsibility**: User identity management

| Function           | Description                    |
|--------------------|--------------------------------|
| User registration  | Create new accounts            |
| User login         | Authenticate credentials       |
| JWT token issuance | Generate access tokens         |
| Token verification | Validate token signatures      |

**Technology**: FastAPI + PostgreSQL + Argon2 (password hashing)

### 3.2 Movie Service

**Responsibility**: Movie catalog management

| Function          | Description                     |
|-------------------|---------------------------------|
| List movies       | Browse available movies         |
| Movie details     | Title, description, duration    |
| Showtimes         | Schedule and availability       |
| Theaters          | Venue and seat layout           |

**Technology**: FastAPI + PostgreSQL + Redis (caching)

### 3.3 Booking Service

**Responsibility**: Seat reservation

| Function          | Description                     |
|-------------------|---------------------------------|
| Create booking    | Reserve selected seats          |
| Lock seats        | Distributed locking via Redis   |
| Cancel booking    | Release seats                   |
| Booking history   | User's past bookings            |

**Technology**: FastAPI + PostgreSQL + Redis (locks) + RabbitMQ

### 3.4 Payment Service

**Responsibility**: Financial transactions

| Function          | Description                     |
|-------------------|---------------------------------|
| Process payment   | Charge for booking              |
| Idempotency       | Prevent duplicate charges       |
| Refunds           | Return money for cancellations  |
| Transaction log   | Payment history                 |

**Technology**: FastAPI + PostgreSQL + Redis (idempotency)

### 3.5 Notification Service

**Responsibility**: User communication

| Function          | Description                     |
|-------------------|---------------------------------|
| Email sending     | Booking confirmations           |
| SMS (future)      | Mobile notifications            |
| Push (future)     | Real-time alerts                |

**Technology**: FastAPI + RabbitMQ (async processing)

---

## 4. Data Architecture

### 4.1 Database Per Service

Each service owns its database. No direct cross-service database access.

```
+-------------+     +-------------+     +-------------+
| Auth Service|     |Movie Service|     |Book Service |
+------+------+     +------+------+     +------+------+
       |                  |                   |
       v                  v                   v
+------+------+    +------+------+    +------+------+
| postgres_   |    | postgres_   |    | postgres_   |
| auth        |    | movie       |    | booking     |
+-------------+    +-------------+    +-------------+
   - users            - movies          - bookings
   - roles            - theaters        - seats
                      - showtimes       - locks
```

### 4.2 Entity Relationships

**Auth Database**:
- users (id, email, username, hashed_password, role, created_at)

**Movie Database**:
- movies (id, title, description, duration, genre, release_date)
- theaters (id, name, location, seat_layout)
- showtimes (id, movie_id, theater_id, start_time, price)
- seats (id, theater_id, row, number, type)

**Booking Database**:
- bookings (id, user_id, showtime_id, status, created_at)
- booked_seats (id, booking_id, seat_id)

**Payment Database**:
- payments (id, booking_id, amount, status, idempotency_key)
- transactions (id, payment_id, type, timestamp)

---

## 5. Communication Patterns

### 5.1 Synchronous (REST API)

Used when client needs immediate response.

```
Client --> Gateway --> Service --> Response --> Client
```

Examples:
- GET /api/movies/movies (browse movies)
- POST /api/auth/token (login)
- POST /api/bookings/book (create booking)

### 5.2 Asynchronous (Events via RabbitMQ)

Used for background processing and service decoupling.

```
Service A --> RabbitMQ --> Service B
           (publish)     (subscribe)
```

**Event Types**:

| Event               | Publisher       | Subscriber        |
|---------------------|-----------------|-------------------|
| booking.created     | Booking Service | Notification      |
| booking.confirmed   | Booking Service | Movie, Notify     |
| booking.cancelled   | Booking Service | Payment, Notify   |
| payment.completed   | Payment Service | Booking, Notify   |
| payment.failed      | Payment Service | Booking, Notify   |

### 5.3 Event Flow Diagram

```
+----------------+                              +------------------+
| Booking Service|                              | Notification Svc |
+-------+--------+                              +---------+--------+
        |                                                 ^
        | booking.created                                 |
        v                                                 |
+-------+------------------------------------------------+--------+
|                         RABBITMQ                                |
|                                                                 |
|   Exchange: booking_events                                      |
|   Queues:                                                       |
|   - notification_queue --> Notification Service                 |
|   - movie_queue --> Movie Service (update availability)         |
+-----------------------------------------------------------------+
```

---

## 6. Infrastructure

### 6.1 Local Development (Docker Compose)

```
docker-compose.yml
|
+-- auth-service (port 8001)
+-- movie-service (port 8002)
+-- booking-service (port 8003)
+-- payment-service (port 8004)
+-- notification-service (port 8005)
+-- postgres (port 5432)
+-- redis (port 6379)
+-- rabbitmq (port 5672, 15672)
+-- nginx (port 80)
+-- prometheus (port 9090)
+-- grafana (port 3000)
```

### 6.2 Production (Kubernetes)

```
k8s/
|
+-- namespaces/
|   +-- movie-booking-ns.yaml
|
+-- services/
|   +-- auth-deployment.yaml
|   +-- movie-deployment.yaml
|   +-- booking-deployment.yaml
|   +-- payment-deployment.yaml
|   +-- notification-deployment.yaml
|
+-- infrastructure/
|   +-- postgres-statefulset.yaml
|   +-- redis-deployment.yaml
|   +-- rabbitmq-statefulset.yaml
|
+-- ingress/
    +-- nginx-ingress.yaml
```

### 6.3 Monitoring Stack

| Component   | Purpose                    | Port  |
|-------------|----------------------------|-------|
| Prometheus  | Metrics collection         | 9090  |
| Grafana     | Dashboards and alerts      | 3000  |
| Jaeger      | Distributed tracing        | 16686 |

### 6.4 Key Metrics

| Metric                    | Target    | Alert Threshold |
|---------------------------|-----------|-----------------|
| Request latency (P95)     | < 200ms   | > 500ms         |
| Error rate                | < 0.1%    | > 1%            |
| CPU usage                 | < 70%     | > 90%           |
| Memory usage              | < 80%     | > 95%           |
| Booking success rate      | > 99%     | < 95%           |

---

## Appendix: Key Design Decisions

### A.1 Why PostgreSQL?

- ACID compliance for financial transactions
- JSON support for flexible schemas
- Mature ecosystem and tooling
- Good performance for read/write workloads

### A.2 Why Redis for Locking?

- Sub-millisecond latency
- SETNX for atomic lock acquisition
- TTL for automatic lock expiration
- Cluster mode for high availability

### A.3 Why RabbitMQ?

- Reliable message delivery
- Flexible routing (topics, fanout)
- Dead letter queues for failed messages
- Management UI for monitoring

### A.4 Why NGINX as Gateway?

- High performance reverse proxy
- Built-in rate limiting
- SSL/TLS termination
- Load balancing support

---

**CSE702063 - Distributed Applications**  
**N01 - Group 9 | Semester 1-2-25**
