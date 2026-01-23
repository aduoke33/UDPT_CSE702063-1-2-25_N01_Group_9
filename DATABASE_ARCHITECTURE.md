# Database Per Service Architecture

## Overview

This project implements the **Database Per Service** pattern, a key microservices principle where each service has its own dedicated database.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     MICROSERVICES ARCHITECTURE                  │
│                    (Database Per Service Pattern)               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────────┐      ┌─────────────┐      ┌─────────────┐   │
│   │   Auth      │      │   Movie     │      │  Booking    │   │
│   │  Service    │      │  Service    │      │  Service    │   │
│   │   :8001     │      │   :8002     │      │   :8003     │   │
│   └──────┬──────┘      └──────┬──────┘      └──────┬──────┘   │
│          │                    │                    │          │
│          ▼                    ▼                    ▼          │
│   ┌─────────────┐      ┌─────────────┐      ┌─────────────┐   │
│   │  auth_db    │      │ movies_db   │      │bookings_db  │   │
│   │ (auth_user) │      │(movie_user) │      │(booking_user│   │
│   │   :5433     │      │   :5434     │      │   :5435     │   │
│   └─────────────┘      └─────────────┘      └─────────────┘   │
│                                                                 │
│   ┌─────────────┐      ┌─────────────┐                        │
│   │  Payment    │      │Notification │                        │
│   │  Service    │      │  Service    │                        │
│   │   :8004     │      │   :8005     │                        │
│   └──────┬──────┘      └──────┬──────┘                        │
│          │                    │                                │
│          ▼                    ▼                                │
│   ┌─────────────┐      ┌─────────────┐                        │
│   │ payments_db │      │notifications│                        │
│   │(payment_user│      │     _db     │                        │
│   │   :5436     │      │(notif_user) │                        │
│   └─────────────┘      │   :5437     │                        │
│                        └─────────────┘                        │
│                                                                 │
│   Shared Infrastructure:                                       │
│   ┌────────┐  ┌──────────┐  ┌──────────┐                     │
│   │ Redis  │  │ RabbitMQ │  │  NGINX   │                     │
│   │ :6379  │  │ :5672    │  │  :80     │                     │
│   └────────┘  └──────────┘  └──────────┘                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Database Configuration

### Development (Docker Compose)

| Service | Database | User | Password | Port | Volume |
|---------|----------|------|----------|------|--------|
| **Auth** | `auth_db` | `auth_user` | `auth_password123` | 5433 | `postgres_auth_data` |
| **Movie** | `movies_db` | `movie_user` | `movie_password123` | 5434 | `postgres_movie_data` |
| **Booking** | `bookings_db` | `booking_user` | `booking_password123` | 5435 | `postgres_booking_data` |
| **Payment** | `payments_db` | `payment_user` | `payment_password123` | 5436 | `postgres_payment_data` |
| **Notification** | `notifications_db` | `notification_user` | `notification_password123` | 5437 | `postgres_notification_data` |

### Kubernetes (Production)

Each service connects to its dedicated StatefulSet:

| Service | K8s Service Name | Internal Port |
|---------|------------------|---------------|
| **Auth** | `postgres-auth-service` | 5432 |
| **Movie** | `postgres-movie-service` | 5432 |
| **Booking** | `postgres-booking-service` | 5432 |
| **Payment** | `postgres-payment-service` | 5432 |
| **Notification** | `postgres-notification-service` | 5432 |

## Connection Strings

### Docker Compose Environment Variables

```yaml
# Auth Service
DATABASE_URL: postgresql+asyncpg://auth_user:auth_password123@postgres-auth:5432/auth_db

# Movie Service
DATABASE_URL: postgresql+asyncpg://movie_user:movie_password123@postgres-movie:5432/movies_db

# Booking Service
DATABASE_URL: postgresql+asyncpg://booking_user:booking_password123@postgres-booking:5432/bookings_db

# Payment Service
DATABASE_URL: postgresql+asyncpg://payment_user:payment_password123@postgres-payment:5432/payments_db

# Notification Service
DATABASE_URL: postgresql+asyncpg://notification_user:notification_password123@postgres-notification:5432/notifications_db
```

### Kubernetes Secrets

Defined in `k8s/secrets/app-secrets.yaml`:

```yaml
AUTH_DATABASE_URL: "postgresql+asyncpg://auth_user:auth_password123@postgres-auth-service:5432/auth_db"
MOVIE_DATABASE_URL: "postgresql+asyncpg://movie_user:movie_password123@postgres-movie-service:5432/movies_db"
# ... (see file for full config)
```

## Benefits of Database Per Service

### ✅ Advantages

1. **Isolation**
   - Each service owns its data
   - Schema changes don't affect other services
   - Independent scaling

2. **Technology Freedom**
   - Can use different database types per service
   - Auth could use PostgreSQL, Movie could use MongoDB (if needed)

3. **Independent Deployment**
   - Update one service without touching others
   - Database migrations are isolated

4. **Resilience**
   - One database failure doesn't crash all services
   - Fault isolation

### ⚠️ Trade-offs

1. **No Foreign Keys Across Services**
   - Cannot use `JOIN` across services
   - Solution: Store denormalized data or use API calls

2. **Distributed Transactions**
   - Cannot use traditional ACID transactions
   - Solution: Saga pattern (implemented via RabbitMQ events)

3. **Data Duplication**
   - Some data is duplicated (e.g., user_id in multiple DBs)
   - Solution: Eventual consistency via events

4. **Operational Complexity**
   - More databases to manage
   - Solution: Automation (Kubernetes StatefulSets, Helm charts)

## Running the System

### Local Development (Docker Compose)

```bash
# Start all services with separate databases
docker-compose up -d

# Check database status
docker-compose ps | grep postgres

# Access a specific database
docker exec -it postgres_auth psql -U auth_user -d auth_db
```

### Kubernetes Deployment

```bash
# Deploy all databases
kubectl apply -f k8s/database/postgres-databases.yaml

# Deploy services
kubectl apply -f k8s/services/

# Check database pods
kubectl get pods -n movie-booking | grep postgres

# Port-forward to access a database
kubectl port-forward -n movie-booking postgres-auth-0 5432:5432
```

## Database Initialization

Each service is responsible for creating its own schema using SQLAlchemy migrations:

```python
# In each service's main.py
async def init_db():
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
```

## Backup Strategy

### Docker Compose

```bash
# Backup all databases
./scripts/backup-databases.sh

# Restore a specific database
docker exec -i postgres_auth psql -U auth_user -d auth_db < backup_auth_db.sql
```

### Kubernetes

```bash
# Use pgBackRest or Velero for automated backups
kubectl apply -f k8s/backup/pgbackrest-config.yaml
```

## Monitoring

- Each database exports metrics to Prometheus
- Grafana dashboards show per-service database metrics:
  - Connection pool usage
  - Query performance
  - Storage usage

## Security

- **Principle of Least Privilege**: Each service has its own user with limited permissions
- **Network Isolation**: Kubernetes NetworkPolicies restrict inter-service database access
- **Encryption**:
  - TLS for connections (production)
  - Transparent Data Encryption at rest

## Migration from Shared Database

If migrating from a shared database:

1. Export data by service domain
2. Create separate databases
3. Import data into respective databases
4. Update service configurations
5. Deploy and test
6. Decommission old shared database

---

**Last Updated**: January 2026  
**Architecture Version**: 2.0 (Database Per Service)
