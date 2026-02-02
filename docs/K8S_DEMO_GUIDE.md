# Kubernetes Features Demonstration Guide

## Movie Booking System - Kubernetes Features Demonstration Guide

---

## Table of Contents

1. [Demo Preparation](#demo-preparation)
2. [Auto-Scaling Demo](#demo-1-horizontal-pod-autoscaling-hpa)
3. [Self-Healing Demo](#demo-2-self-healing-and-high-availability)
4. [Monitoring with Grafana](#demo-3-monitoring-with-prometheus-and-grafana)
5. [Service Mesh (Istio)](#demo-4-service-mesh-with-istio)
6. [Rolling Updates](#demo-5-rolling-updates-zero-downtime)
7. [Network Policies](#demo-6-network-security-policies)
8. [Load Testing](#demo-7-load-testing-and-performance)

---

## Demo Preparation

### 1. Khởi động K8s cluster

```powershell
# Windows PowerShell
cd "D:\Ứng dụng phân tán\UDPT_CSE702063-1-2-25_N01_Group_9"

# Start local K8s cluster (minikube hoặc Docker Desktop)
.\scripts\k8s-local.ps1 up

# Verify cluster
kubectl cluster-info
kubectl get nodes
```

### 2. Deploy toàn bộ hệ thống

```powershell
# Deploy namespace
kubectl apply -f k8s/namespace.yaml

# Deploy databases
kubectl apply -f k8s/database/

# Deploy services
kubectl apply -f k8s/services/

# Deploy monitoring stack
kubectl apply -f k8s/monitoring/

# Deploy HPA
kubectl apply -f k8s/autoscaling/hpa.yaml

# Check deployment
kubectl get all -n movie-booking
```

### 3. Access Grafana Dashboard

```powershell
# Port-forward Grafana
kubectl port-forward -n movie-booking svc/grafana 3000:3000

# Mở browser: http://localhost:3000
# Default login: admin / admin (hoặc check k8s secret)
```

---

## DEMO 1: Horizontal Pod Autoscaling (HPA)

### Mục tiêu

Chứng minh hệ thống tự động scale pods khi tải tăng cao.

### Bước 1: Kiểm tra trạng thái ban đầu

```powershell
# Xem số lượng pods hiện tại
kubectl get hpa -n movie-booking

# Xem chi tiết booking-service
kubectl get pods -n movie-booking -l app=booking-service
# → Kết quả: Sẽ thấy 2-3 pods (minReplicas)
```

### Bước 2: Tạo load test để tăng CPU/Memory

```powershell
# Terminal 1: Monitor HPA real-time
kubectl get hpa -n movie-booking -w

# Terminal 2: Chạy load test
cd tests
python load_test.py --target http://localhost/api/bookings --duration 300 --users 100
```

### Bước 3: Quan sát auto-scaling

```powershell
# Xem pods tăng dần
kubectl get pods -n movie-booking -l app=booking-service -w

# Xem metrics
kubectl top pods -n movie-booking
```

### Kết quả mong đợi

```
NAME                           REFERENCE                 TARGETS          MINPODS   MAXPODS   REPLICAS
booking-service-hpa   Deployment/booking-service   85%/70% (CPU)    2         15        8
```

**Giải thích**:

- Ban đầu: 2 pods (minReplicas)
- CPU > 70% → HPA tự động tăng lên 8 pods
- Load giảm → Scale down về 2 pods (sau 5 phút)

---

## DEMO 2: Self-Healing and High Availability

### Mục tiêu

Chứng minh K8s tự động restart pods bị lỗi.

### Bước 1: Xem pods đang chạy

```powershell
kubectl get pods -n movie-booking -l app=auth-service
```

### Bước 2: Kill một pod

```powershell
# Lấy tên pod
$POD_NAME = kubectl get pods -n movie-booking -l app=auth-service -o jsonpath='{.items[0].metadata.name}'

# Delete pod
kubectl delete pod $POD_NAME -n movie-booking

# Watch pods được tạo lại tự động
kubectl get pods -n movie-booking -l app=auth-service -w
```

### Kết quả mong đợi

```
NAME                            READY   STATUS        RESTARTS
auth-service-7d4b5f6c8d-abc12   1/1     Terminating   0
auth-service-7d4b5f6c8d-xyz99   0/1     Pending       0
auth-service-7d4b5f6c8d-xyz99   1/1     Running       0
```

**Giải thích**:

- K8s phát hiện pod bị xóa
- Tự động tạo pod mới trong vài giây
- Service vẫn hoạt động bình thường (không downtime)

---

## DEMO 3: Monitoring with Prometheus and Grafana

### Hệ thống Monitoring hoạt động như sau

```
┌─────────────────────────────────────────────────────┐
│                  GRAFANA DASHBOARD                   │
│  http://localhost:3000                               │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐             │
│  │ CPU/Mem  │ │ Requests │ │ Errors   │             │
│  │ Usage    │ │ Rate     │ │ Rate     │             │
│  └──────────┘ └──────────┘ └──────────┘             │
└──────────────────┬──────────────────────────────────┘
                   │ Query PromQL
                   ▼
┌─────────────────────────────────────────────────────┐
│              PROMETHEUS SERVER                       │
│  http://localhost:9090                               │
│  - Scrape metrics every 15s                          │
│  - Store time-series data                            │
│  - Evaluate alerting rules                           │
└──────────────────┬──────────────────────────────────┘
                   │ Scrape /metrics
                   ▼
┌─────────────────────────────────────────────────────┐
│              MICROSERVICES                           │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐    │
│  │Auth Service│  │Movie Service│  │Booking Svc│    │
│  │:8000/metrics│  │:8000/metrics│  │:8000/metrics│  │
│  └────────────┘  └────────────┘  └────────────┘    │
│  - Request count, latency, errors                    │
│  - Custom business metrics (bookings, payments)      │
└─────────────────────────────────────────────────────┘
```

### Bước 1: Access Grafana

```powershell
# Port-forward (nếu chưa làm)
kubectl port-forward -n movie-booking svc/grafana 3000:3000

# Mở browser: http://localhost:3000
# Login: admin / admin
```

### Bước 2: Import Dashboard

Dashboard đã được cấu hình sẵn tại `monitoring/grafana-dashboard.json`

**Các panels có sẵn:**

1. **Business Metrics**
   - Successful Bookings (1h): Số lượng booking thành công
   - Payment Success Rate: Tỷ lệ thanh toán thành công
   - Active Users: Số user đang online

2. **Performance Metrics**
   - Request Rate (RPS): Requests per second cho mỗi service
   - Response Time (p95): 95th percentile latency
   - Error Rate: Tỷ lệ lỗi 4xx/5xx

3. **Infrastructure Metrics**
   - CPU Usage per Pod: % CPU sử dụng
   - Memory Usage per Pod: RAM usage
   - Pod Restarts: Số lần pod restart

4. **Database Metrics**
   - Connection Pool Usage
   - Query Performance
   - Active Connections

### Bước 3: Tạo traffic để xem metrics

```powershell
# Chạy test script
.\test_api.ps1

# Hoặc manual requests
curl http://localhost/api/movies
curl http://localhost/api/auth/health
```

### Bước 4: Quan sát Dashboard

Trên Grafana bạn sẽ thấy:

- **Real-time graphs** cập nhật mỗi 5s
- **Alerts** nếu có vấn đề (CPU > 90%, Error rate > 5%)
- **Heatmaps** thể hiện latency distribution

### Metrics quan trọng

```promql
# Request rate
rate(http_requests_total[5m])

# Error rate
rate(http_requests_total{status=~"5.."}[5m])

# P95 latency
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))

# CPU usage
rate(container_cpu_usage_seconds_total[5m])
```

---

## DEMO 4: Service Mesh with Istio

### Mục tiêu

Chứng minh traffic management, retries, circuit breaking.

### Feature 1: Traffic Routing

```powershell
# Deploy Istio configs
kubectl apply -f k8s/istio/service-mesh.yaml

# Check VirtualService
kubectl get virtualservice -n movie-booking
```

### Feature 2: Fault Injection (Demo lỗi)

Thêm delay 5s vào 10% requests:

```yaml
# Edit virtual service
kubectl edit virtualservice api-gateway-vs -n movie-booking

# Thêm fault injection:
spec:
  http:
  - match:
    - uri:
        prefix: /api/bookings
    fault:
      delay:
        percentage:
          value: 10.0
        fixedDelay: 5s
```

Test:

```powershell
# Một số request sẽ chậm 5s
for ($i=1; $i -le 10; $i++) {
    Measure-Command { curl http://localhost/api/bookings }
}
```

### Feature 3: Retry & Timeout

Cấu hình sẵn trong `service-mesh.yaml`:

- **Retries**: Tự động retry 3 lần khi lỗi 5xx
- **Timeout**: 30s cho normal requests, 60s cho booking/payment
- **Circuit Breaking**: Ngắt kết nối nếu quá nhiều lỗi

---

## DEMO 5: Rolling Updates (Zero Downtime)

### Mục tiêu

Update service mà không downtime.

### Bước 1: Check version hiện tại

```powershell
kubectl describe deployment auth-service -n movie-booking | Select-String "Image:"
# → Image: ghcr.io/user/auth-service:v1.0
```

### Bước 2: Rolling update

```powershell
# Update image tag
kubectl set image deployment/auth-service -n movie-booking `
  auth-service=ghcr.io/user/auth-service:v1.1

# Watch rollout
kubectl rollout status deployment/auth-service -n movie-booking

# Monitor pods
kubectl get pods -n movie-booking -l app=auth-service -w
```

### Kết quả quan sát

```
NAME                            READY   STATUS              RESTARTS
auth-service-v1-abc123-xyz      1/1     Running             0
auth-service-v2-def456-abc      0/1     ContainerCreating   0
auth-service-v2-def456-abc      1/1     Running             0
auth-service-v1-abc123-xyz      1/1     Terminating         0
```

**Giải thích**:

1. K8s tạo pod mới (v1.1) trước
2. Chờ pod mới ready
3. Chuyển traffic sang pod mới
4. Xóa pod cũ (v1.0)
5. Lặp lại cho các pods còn lại

→ **Zero downtime!**

---

## DEMO 6: Network Security Policies

### Mục tiêu

Chứng minh isolation giữa các services.

### Bước 1: Apply network policies

```powershell
kubectl apply -f k8s/network/network-policies.yaml
```

### Bước 2: Test connectivity

```powershell
# Tạo test pod
kubectl run test-pod -n movie-booking --image=curlimages/curl -it --rm -- sh

# Bên trong pod, test access:
# Allowed: booking-service -> payment-service
curl http://payment-service:8000/health

# Denied: test-pod -> database (no matching label)
curl http://postgres-booking:5432
# → Connection timeout
```

**Policy ví dụ:**

- Chỉ booking-service được connect tới postgres-booking
- Chỉ API Gateway được nhận traffic từ bên ngoài
- Service-to-service communication qua specific ports

---

## DEMO 7: Load Testing and Performance

### Setup load test

```powershell
# Install k6
choco install k6

# Hoặc dùng Python locust
pip install locust
```

### Chạy load test với k6

```javascript
// load-test.js
import http from "k6/http";
import { check, sleep } from "k6";

export let options = {
  stages: [
    { duration: "2m", target: 100 }, // Ramp up to 100 users
    { duration: "5m", target: 100 }, // Stay at 100 users
    { duration: "2m", target: 0 }, // Ramp down
  ],
};

export default function () {
  let res = http.get("http://localhost/api/movies");
  check(res, {
    "status is 200": (r) => r.status === 200,
    "response time < 500ms": (r) => r.timings.duration < 500,
  });
  sleep(1);
}
```

```powershell
# Run test
k6 run load-test.js

# Observe trong Grafana:
# - Request rate tăng lên 100 RPS
# - HPA scale từ 2 → 8 pods
# - Response time vẫn < 500ms
```

---

## Comparison: Docker Compose vs Kubernetes

| Feature                | Docker Compose                 | Kubernetes              |
| ---------------------- | ------------------------------ | ----------------------- |
| **Deployment**         | `docker-compose up`            | `kubectl apply -k k8s/` |
| **Scaling**            | Manual: `docker-compose scale` | Auto: HPA               |
| **Self-Healing**       | None                           | Automatic               |
| **Load Balancing**     | Basic (nginx)                  | Advanced (Istio)        |
| **Monitoring**         | Manual setup                   | Prometheus+Grafana      |
| **Rolling Updates**    | Downtime                       | Zero-downtime           |
| **Resource Limits**    | Basic                          | Requests/Limits         |
| **Network Policies**   | None                           | Full isolation          |
| **Secrets Management** | .env files                     | K8s Secrets             |
| **Service Discovery**  | DNS only                       | Advanced                |

---

## Complete Demo Script (15 minutes)

### Phần 1: Giới thiệu (2 phút)

```
"Hệ thống Movie Booking được deploy trên Kubernetes với đầy đủ
production features: Auto-scaling, Self-healing, Monitoring, Service Mesh"
```

### Phần 2: Auto-Scaling Demo (4 phút)

1. Show HPA status: `kubectl get hpa -n movie-booking`
2. Chạy load test
3. Watch pods scale up real-time
4. Show Grafana metrics

### Phần 3: Self-Healing Demo (3 phút)

1. Delete một pod
2. K8s tự động tạo lại
3. Service không bị gián đoạn

### Phần 4: Monitoring Dashboard (3 phút)

1. Mở Grafana: http://localhost:3000
2. Show các metrics:
   - Request rate
   - Error rate
   - CPU/Memory usage
   - Business metrics (bookings, payments)

### Phần 5: Rolling Update (3 phút)

1. Update một service
2. Watch zero-downtime deployment
3. Rollback nếu có lỗi

---

## Troubleshooting

### Grafana không hiển thị data

```powershell
# Check Prometheus
kubectl port-forward -n movie-booking svc/prometheus 9090:9090
# Mở http://localhost:9090/targets
# → Tất cả targets phải "UP"

# Check service có expose metrics
kubectl exec -n movie-booking deployment/auth-service -- curl localhost:8000/metrics
```

### HPA không scale

```powershell
# Check metrics server
kubectl top nodes
kubectl top pods -n movie-booking

# Nếu lỗi, install metrics server:
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

### Pods không start

```powershell
# Check logs
kubectl logs -n movie-booking deployment/auth-service

# Check events
kubectl get events -n movie-booking --sort-by='.lastTimestamp'
```

---

## References

- [K8S_LOCAL_DEPLOYMENT.md](./K8S_LOCAL_DEPLOYMENT.md) - Setup K8s local
- [LOAD_TESTING.md](./LOAD_TESTING.md) - Load testing guide
- [RUNBOOK.md](./RUNBOOK.md) - Operational runbook

---

## Demo Checklist

- [ ] K8s cluster running
- [ ] All pods READY
- [ ] Grafana accessible at http://localhost:3000
- [ ] Prometheus targets all UP
- [ ] HPA configured
- [ ] Load test script ready
- [ ] Browser tabs open: Grafana, Prometheus
- [ ] Terminal ready: watch pods, logs

---

**Good luck with your demo!**
