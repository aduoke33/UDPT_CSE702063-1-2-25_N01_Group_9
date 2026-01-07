# Distributed Online Movie Ticket Booking System

**Course:** CSE702063 - Distributed Applications  
**Group:** N01_Group_9

## Team Members

- **Member 1:** Frontend (Laravel) & Integration
- **Member 2:** Backend (FastAPI), PostgreSQL, Redis
- **Member 3:** DevOps (Docker/K8s), Nginx, Auth Service

## Architecture

- **Pattern:** Microservices
- **Tech Stack:** Laravel, FastAPI, PostgreSQL, Redis, RabbitMQ, Nginx, Docker

### Services

1. **Auth Service** - JWT authentication
2. **Movie Service** - Movie catalog, showtimes
3. **Booking Service** - Seat booking with Redis distributed locking
4. **Payment Service** - Payment processing
5. **Notification Service** - Email/SMS via RabbitMQ

## Quick Start

```bash
# Setup
cp .env.example .env

# Run all services
docker-compose up -d

# Test auth
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","username":"test","password":"test123"}'
```

## Access Points

- API Gateway: http://localhost
- Auth Service: http://localhost:8001
- RabbitMQ UI: http://localhost:15672 (admin/admin123)
- Database: localhost:5432 (admin/admin123)

## Project Structure

```
├── services/           # FastAPI microservices
├── frontend/           # Laravel UI
├── gateway/            # Nginx config
├── database/           # PostgreSQL init scripts
└── docker-compose.yml
```
