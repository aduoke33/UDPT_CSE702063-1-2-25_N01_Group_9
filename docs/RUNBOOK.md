# 📖 Operations Runbook

## Movie Booking System - Incident Response & Operations Guide

---

## 📋 Table of Contents

- [Service Overview](#service-overview)
- [Health Checks](#health-checks)
- [Common Issues & Solutions](#common-issues--solutions)
- [Incident Response](#incident-response)
- [Monitoring & Alerts](#monitoring--alerts)
- [Maintenance Procedures](#maintenance-procedures)

---

## Service Overview

| Service | Port | Database | Dependencies |
|---------|------|----------|--------------|
| Auth Service | 8001 | postgres-auth:5432 | Redis |
| Movie Service | 8002 | postgres-movie:5432 | Redis, Auth |
| Booking Service | 8003 | postgres-booking:5432 | Redis, RabbitMQ, Auth, Movie |
| Payment Service | 8004 | postgres-payment:5432 | RabbitMQ, Auth, Booking |
| Notification Service | 8005 | postgres-notification:5432 | RabbitMQ |

### Service URLs (Local)

- Gateway: http://localhost:80
- RabbitMQ Management: http://localhost:15672
- Prometheus: http://localhost:9090
- Grafana: http://localhost:3000

---

## Health Checks

### Quick Health Check

```bash
# All services
./scripts/e2e_test.sh

# Individual service
curl http://localhost:8001/health  # Auth
curl http://localhost:8002/health  # Movie
curl http://localhost:8003/health  # Booking
curl http://localhost:8004/health  # Payment
curl http://localhost:8005/health  # Notification
```

### Docker Health

```bash
# Check container status
docker-compose ps

# Check specific container
docker inspect --format='{{.State.Health.Status}}' auth_service
```

---

## Common Issues & Solutions

### 🔴 Issue: Service Won't Start

**Symptoms:** Container exits immediately, health check fails

**Diagnosis:**
```bash
docker-compose logs <service-name>
docker inspect <container-name>
```

**Solutions:**
1. Check database connection string
2. Verify dependent services are running
3. Check for port conflicts
4. Review environment variables

---

### 🔴 Issue: Database Connection Failed

**Symptoms:** `Connection refused` or `timeout` errors

**Diagnosis:**
```bash
# Check database container
docker-compose logs postgres-auth

# Test connection
docker exec -it postgres_auth psql -U auth_user -d auth_db -c "SELECT 1"
```

**Solutions:**
1. Wait for database to be ready (healthcheck)
2. Verify credentials in environment
3. Check network connectivity
4. Restart database container

---

### 🔴 Issue: RabbitMQ Queue Backlog

**Symptoms:** Messages not being processed, high queue depth

**Diagnosis:**
```bash
# Check RabbitMQ management
curl -u admin:admin123 http://localhost:15672/api/queues

# Or open UI: http://localhost:15672
```

**Solutions:**
1. Check consumer services (payment, notification)
2. Scale consumers if needed
3. Check for poison messages
4. Purge queue if necessary (data loss!)

---

### 🔴 Issue: Redis Connection Issues

**Symptoms:** Session/cache failures, slow responses

**Diagnosis:**
```bash
# Check Redis
docker exec -it movie_booking_redis redis-cli -a redis123 ping

# Check memory
docker exec -it movie_booking_redis redis-cli -a redis123 info memory
```

**Solutions:**
1. Verify Redis password
2. Check memory limits
3. Flush cache if corrupted: `FLUSHALL` (data loss!)
4. Restart Redis container

---

### 🔴 Issue: High Response Times

**Symptoms:** p95 > 500ms, user complaints

**Diagnosis:**
```bash
# Check container resources
docker stats

# Check database queries
docker exec -it postgres_auth psql -U auth_user -d auth_db \
  -c "SELECT query, calls, mean_time FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10"
```

**Solutions:**
1. Scale service replicas
2. Add database indexes
3. Enable/check Redis caching
4. Review N+1 query issues

---

### 🔴 Issue: Booking Race Condition

**Symptoms:** Double bookings, seat conflicts

**Diagnosis:**
```bash
# Check booking logs
docker-compose logs booking-service | grep -i "lock\|conflict"

# Check Redis locks
docker exec -it movie_booking_redis redis-cli -a redis123 keys "lock:*"
```

**Solutions:**
1. Verify distributed lock is working
2. Check lock TTL settings
3. Review booking service code
4. Manual seat cleanup if needed

---

## Incident Response

### Severity Levels

| Level | Description | Response Time | Examples |
|-------|-------------|---------------|----------|
| **P1** | System down | 15 min | All services unreachable |
| **P2** | Major feature broken | 1 hour | Payments failing |
| **P3** | Minor issue | 4 hours | Slow responses |
| **P4** | Low impact | 24 hours | UI bugs |

### P1 Response Checklist

1. [ ] Acknowledge incident
2. [ ] Check all service health endpoints
3. [ ] Review recent deployments
4. [ ] Check infrastructure (Docker, network)
5. [ ] Rollback if recent change caused issue
6. [ ] Communicate status to stakeholders
7. [ ] Document root cause

### Rollback Procedure

```bash
# Stop current deployment
docker-compose down

# Checkout previous version
git checkout <previous-tag>

# Rebuild and deploy
docker-compose up -d --build

# Verify health
./scripts/e2e_test.sh
```

---

## Monitoring & Alerts

### Grafana Dashboards

- **Overview Dashboard**: System-wide health
- **Booking Dashboard**: Booking metrics
- **Payment Dashboard**: Payment success rates

### Key Metrics to Watch

| Metric | Warning | Critical |
|--------|---------|----------|
| Error Rate | > 1% | > 5% |
| p95 Latency | > 200ms | > 500ms |
| CPU Usage | > 70% | > 90% |
| Memory Usage | > 70% | > 90% |
| Queue Depth | > 1000 | > 5000 |

### Prometheus Queries

```promql
# Error rate
sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))

# p95 latency
histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket[5m])) by (le))

# Active bookings
booking_active_count
```

---

## Maintenance Procedures

### Database Backup

```bash
# Backup all databases
for db in auth movie booking payment notification; do
  docker exec postgres_$db pg_dump -U ${db}_user ${db}_db > backup_${db}_$(date +%Y%m%d).sql
done
```

### Database Restore

```bash
# Restore specific database
docker exec -i postgres_auth psql -U auth_user auth_db < backup_auth_20260124.sql
```

### Log Rotation

```bash
# Docker handles this automatically, but to force:
docker-compose logs --no-log-prefix > logs_$(date +%Y%m%d).txt
docker system prune -f
```

### Scaling Services

```bash
# Scale booking service to 3 replicas
docker-compose up -d --scale booking-service=3
```

---

## Contacts

| Role | Contact | Escalation |
|------|---------|------------|
| On-Call Engineer | - | 15 min |
| Tech Lead | - | 30 min |
| Product Owner | - | 1 hour |

---

## Revision History

| Date | Version | Changes |
|------|---------|---------|
| 2026-01-24 | 1.0 | Initial runbook |
