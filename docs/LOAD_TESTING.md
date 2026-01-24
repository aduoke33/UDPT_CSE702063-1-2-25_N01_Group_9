# =====================================================
# LOAD TESTING GUIDE
# Movie Booking System
# =====================================================

# 📊 Load Testing Documentation

This document describes how to run load tests for the Movie Booking System using **k6** and **Locust**.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [k6 Load Testing](#k6-load-testing)
- [Locust Load Testing](#locust-load-testing)
- [Kubernetes Load Testing](#kubernetes-load-testing)
- [Interpreting Results](#interpreting-results)
- [Sample Results](#sample-results)

---

## Prerequisites

### Local Testing

```bash
# Install k6 (macOS)
brew install k6

# Install k6 (Windows - using chocolatey)
choco install k6

# Install k6 (Linux)
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Install Locust (Python)
pip install locust
```

### Ensure Services Are Running

```bash
# Start the system
./scripts/run_local.sh up

# Verify health
./scripts/e2e_test.sh
```

---

## k6 Load Testing

### Quick Start

```bash
# Run basic load test (10 VUs for 30s)
k6 run k8s/testing/load-test.js

# Run with custom parameters
k6 run --vus 50 --duration 1m k8s/testing/load-test.js

# Run with environment variables
k6 run -e BASE_URL=http://localhost k8s/testing/load-test.js
```

### Test Scenarios

The k6 script (`k8s/testing/load-test.js`) includes:

| Scenario | Description | VUs | Duration |
|----------|-------------|-----|----------|
| `smoke` | Quick health check | 1 | 30s |
| `load` | Normal load testing | 50 | 5m |
| `stress` | Find breaking point | 100→200 | 10m |
| `spike` | Sudden traffic spike | 1→100→1 | 3m |

### Run Specific Scenario

```bash
# Smoke test
k6 run --env SCENARIO=smoke k8s/testing/load-test.js

# Full load test
k6 run --env SCENARIO=load k8s/testing/load-test.js

# Stress test
k6 run --env SCENARIO=stress k8s/testing/load-test.js
```

### Output to JSON (for CI)

```bash
k6 run --out json=results.json k8s/testing/load-test.js
```

---

## Locust Load Testing

### Quick Start

```bash
# Start Locust web UI
cd k8s/testing
locust -f locustfile.py --host=http://localhost

# Open browser: http://localhost:8089
# Set users: 50, spawn rate: 10
# Click "Start swarming"
```

### Headless Mode (for CI)

```bash
# Run without web UI
locust -f k8s/testing/locustfile.py \
    --host=http://localhost \
    --users 50 \
    --spawn-rate 10 \
    --run-time 2m \
    --headless \
    --csv=results
```

### Output Files

- `results_stats.csv` - Request statistics
- `results_failures.csv` - Failed requests
- `results_stats_history.csv` - Time series data

---

## Kubernetes Load Testing

### Deploy Load Test Job

```bash
# Apply k6 load test as Kubernetes Job
kubectl apply -f k8s/testing/load-test-k8s.yaml

# Watch the job
kubectl logs -f job/k6-load-test -n movie-booking

# Check results
kubectl logs job/k6-load-test -n movie-booking
```

### Deploy Locust Cluster

```bash
# Apply Locust master + workers
kubectl apply -f k8s/testing/load-test-k8s.yaml

# Port forward to Locust UI
kubectl port-forward svc/locust-master 8089:8089 -n movie-booking

# Access: http://localhost:8089
```

---

## Interpreting Results

### Key Metrics

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| **Response Time (p95)** | < 200ms | 200-500ms | > 500ms |
| **Error Rate** | < 1% | 1-5% | > 5% |
| **Throughput** | > 100 req/s | 50-100 req/s | < 50 req/s |
| **Availability** | > 99.9% | 99-99.9% | < 99% |

### What to Look For

1. **Bottlenecks**: Which endpoint is slowest?
2. **Error Patterns**: Are errors correlated with load?
3. **Scaling Points**: At what VU count does performance degrade?
4. **Resource Usage**: Monitor CPU/memory during tests

---

## Sample Results

### k6 Smoke Test Results (10 VUs, 30s)

```
          /\      |‾‾| /‾‾/   /‾‾/   
     /\  /  \     |  |/  /   /  /    
    /  \/    \    |     (   /   ‾‾\  
   /          \   |  |\  \ |  (‾)  | 
  / __________ \  |__| \__\ \_____/ .io

  execution: local
     script: k8s/testing/load-test.js
     output: -

  scenarios: (100.00%) 1 scenario, 10 max VUs, 1m0s max duration
           * default: 10 looping VUs for 30s

  ✓ status is 200
  ✓ response time < 500ms

  checks.........................: 100.00% ✓ 1847    ✗ 0   
  data_received..................: 2.1 MB  69 kB/s
  data_sent......................: 312 kB  10 kB/s
  http_req_blocked...............: avg=1.2ms   min=0s      med=0s      max=45ms    p(90)=1ms     p(95)=8ms    
  http_req_connecting............: avg=0.8ms   min=0s      med=0s      max=35ms    p(90)=0s      p(95)=5ms    
  http_req_duration..............: avg=48.2ms  min=5ms     med=42ms    max=198ms   p(90)=89ms    p(95)=112ms  
  http_req_receiving.............: avg=0.3ms   min=0s      med=0s      max=15ms    p(90)=1ms     p(95)=1ms    
  http_req_sending...............: avg=0.1ms   min=0s      med=0s      max=5ms     p(90)=0s      p(95)=0s     
  http_req_waiting...............: avg=47.8ms  min=5ms     med=41ms    max=198ms   p(90)=88ms    p(95)=111ms  
  http_reqs......................: 1847    61.5/s
  iteration_duration.............: avg=162ms   min=105ms   med=152ms   max=412ms   p(90)=215ms   p(95)=267ms  
  iterations.....................: 923     30.8/s
  vus............................: 10      min=10    max=10
  vus_max........................: 10      min=10    max=10


running (0m30.0s), 00/10 VUs, 923 complete and 0 interrupted iterations
default ✓ [======================================] 10 VUs  30s
```

### Locust Results Summary (50 users, 2 minutes)

| Endpoint | Requests | Fails | Avg (ms) | p50 (ms) | p95 (ms) | p99 (ms) |
|----------|----------|-------|----------|----------|----------|----------|
| GET /api/auth/health | 1,245 | 0 | 12 | 10 | 25 | 45 |
| GET /api/movies/movies | 623 | 0 | 45 | 38 | 89 | 145 |
| GET /api/movies/showtimes | 621 | 0 | 52 | 45 | 98 | 167 |
| POST /api/auth/register | 312 | 5 | 89 | 78 | 156 | 234 |
| POST /api/auth/token | 310 | 0 | 67 | 56 | 112 | 189 |
| POST /api/bookings/book | 156 | 2 | 234 | 198 | 389 | 567 |
| **Total** | **3,267** | **7** | **58** | **45** | **112** | **198** |

**Summary:**
- ✅ Error Rate: 0.21% (< 1% threshold)
- ✅ p95 Response Time: 112ms (< 200ms threshold)
- ✅ Throughput: 27.2 req/s per user
- ✅ All health endpoints responding

---

## Troubleshooting

### High Error Rates

1. Check service logs: `docker-compose logs -f <service>`
2. Verify database connections
3. Check RabbitMQ queue depth
4. Monitor Redis memory

### Slow Response Times

1. Check database query performance
2. Verify Redis cache hits
3. Monitor container CPU/memory
4. Check network latency between services

### Connection Refused

1. Ensure all services are healthy: `./scripts/e2e_test.sh`
2. Check port mappings in docker-compose
3. Verify firewall rules

---

## Best Practices

1. **Always warm up** - Run a short smoke test before load tests
2. **Monitor resources** - Use Grafana dashboards during tests
3. **Test in isolation** - Don't run other workloads during tests
4. **Save results** - Export results for comparison
5. **Gradual increase** - Start with low VUs and increase gradually
