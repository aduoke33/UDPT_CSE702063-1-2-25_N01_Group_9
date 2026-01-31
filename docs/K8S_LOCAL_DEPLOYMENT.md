# 🚀 Local Kubernetes Deployment Guide

## Hướng dẫn triển khai Movie Booking System trên Kubernetes local

Tài liệu này hướng dẫn chi tiết cách chạy hệ thống Movie Booking trên Kubernetes local sử dụng **minikube**, **kind**, hoặc **Docker Desktop**.

---

## 📋 Mục lục

1. [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
2. [Cài đặt công cụ](#-cài-đặt-công-cụ)
3. [Cấu trúc dự án K8s](#-cấu-trúc-dự-án-k8s)
4. [Hướng dẫn triển khai](#-hướng-dẫn-triển-khai)
5. [Các phương pháp triển khai](#-các-phương-pháp-triển-khai)
6. [Truy cập ứng dụng](#-truy-cập-ứng-dụng)
7. [Giám sát và Debug](#-giám-sát-và-debug)
8. [Troubleshooting](#-troubleshooting)

---

## 💻 Yêu cầu hệ thống

### Phần cứng tối thiểu

- **CPU**: 4 cores
- **RAM**: 8 GB (khuyến nghị 16 GB)
- **Disk**: 30 GB trống

### Phần mềm cần thiết

| Công cụ                          | Phiên bản | Bắt buộc       |
| -------------------------------- | --------- | -------------- |
| Docker                           | >= 20.10  | ✅             |
| kubectl                          | >= 1.28   | ✅             |
| minikube / kind / Docker Desktop | Latest    | ✅ (1 trong 3) |
| Helm                             | >= 3.12   | Khuyến nghị    |
| Skaffold                         | >= 2.0    | Tuỳ chọn       |

---

## 🔧 Cài đặt công cụ

### Windows (PowerShell Admin)

```powershell
# Cài đặt Chocolatey (nếu chưa có)
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Cài đặt các công cụ
choco install docker-desktop -y
choco install kubectl -y
choco install minikube -y
choco install kubernetes-helm -y
choco install skaffold -y

# Khởi động lại terminal sau khi cài xong
```

### macOS (Homebrew)

```bash
# Cài đặt Homebrew (nếu chưa có)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Cài đặt các công cụ
brew install --cask docker
brew install kubectl
brew install minikube
brew install helm
brew install skaffold
```

### Linux (Ubuntu/Debian)

```bash
# Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# kubectl
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl

# minikube
curl -LO https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64
sudo install minikube-linux-amd64 /usr/local/bin/minikube

# Helm
curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash

# Skaffold
curl -Lo skaffold https://storage.googleapis.com/skaffold/releases/latest/skaffold-linux-amd64
sudo install skaffold /usr/local/bin/
```

---

## 📁 Cấu trúc dự án K8s

```
k8s/
├── namespace.yaml              # Namespace chính
├── base/
│   └── kustomization.yaml      # Base Kustomize config
├── overlays/
│   ├── local/                  # ⭐ Config cho local development
│   │   ├── kustomization.yaml
│   │   ├── namespace.yaml
│   │   ├── local-storage.yaml
│   │   └── local-secrets.yaml
│   ├── staging/
│   └── production/
├── configmaps/
├── secrets/
├── database/
├── services/
├── gateway/
├── autoscaling/
└── monitoring/

helm/movie-booking/
├── Chart.yaml
├── values.yaml                 # Default values
├── values-local.yaml           # ⭐ Local development values
└── templates/
    ├── _helpers.tpl
    ├── auth-service.yaml
    ├── movie-service.yaml
    ├── booking-service.yaml
    ├── payment-service.yaml
    ├── notification-service.yaml
    ├── api-gateway.yaml
    ├── databases.yaml
    ├── redis.yaml
    ├── rabbitmq.yaml
    └── secrets.yaml
```

---

## 🚀 Hướng dẫn triển khai

### Sử dụng Script tự động (Khuyến nghị)

#### Windows (PowerShell)

```powershell
# 1. Mở PowerShell với quyền Administrator
# 2. Di chuyển đến thư mục dự án
cd "D:\Ứng dụng phân tán\UDPT_CSE702063-1-2-25_N01_Group_9"

# 3. Chạy script với các lệnh:

# Khởi động cluster và deploy toàn bộ
.\scripts\k8s-local.ps1 all

# Hoặc từng bước:
.\scripts\k8s-local.ps1 start              # Khởi động minikube
.\scripts\k8s-local.ps1 build              # Build images
.\scripts\k8s-local.ps1 deploy             # Deploy ứng dụng

# Xem trạng thái
.\scripts\k8s-local.ps1 status

# Xem logs
.\scripts\k8s-local.ps1 logs -Service auth-service

# Dọn dẹp
.\scripts\k8s-local.ps1 clean
```

#### Linux/macOS (Bash)

```bash
# 1. Cấp quyền thực thi
chmod +x scripts/k8s-local.sh

# 2. Chạy script
./scripts/k8s-local.sh all                    # Toàn bộ
./scripts/k8s-local.sh start                  # Khởi động cluster
./scripts/k8s-local.sh build                  # Build images
./scripts/k8s-local.sh deploy                 # Deploy

# Xem trạng thái
./scripts/k8s-local.sh status

# Xem logs
SERVICE=auth-service ./scripts/k8s-local.sh logs

# Dọn dẹp
./scripts/k8s-local.sh clean
```

---

## 📦 Các phương pháp triển khai

### 1️⃣ Helm (Khuyến nghị cho local)

```bash
# Khởi động minikube
minikube start --cpus=4 --memory=8192 --driver=docker

# Cấu hình Docker sử dụng minikube daemon
eval $(minikube docker-env)

# Build images
docker build -t movie-booking/auth-service:local services/auth-service
docker build -t movie-booking/movie-service:local services/movie-service
docker build -t movie-booking/booking-service:local services/booking-service
docker build -t movie-booking/payment-service:local services/payment-service
docker build -t movie-booking/notification-service:local services/notification-service

# Deploy với Helm
helm upgrade --install movie-booking ./helm/movie-booking \
    -f ./helm/movie-booking/values-local.yaml \
    -n movie-booking-local \
    --create-namespace \
    --wait

# Xem trạng thái
kubectl get all -n movie-booking-local
```

### 2️⃣ Kustomize

```bash
# Apply overlay local
kubectl apply -k k8s/overlays/local

# Xem trạng thái
kubectl get all -n movie-booking-local

# Xoá
kubectl delete -k k8s/overlays/local
```

### 3️⃣ Skaffold (Hot-reload development)

```bash
# Chế độ development với hot-reload
skaffold dev -p local

# Hoặc deploy một lần
skaffold run -p local

# Debug mode
skaffold debug -p local
```

---

## 🌐 Truy cập ứng dụng

### Minikube

```bash
# Lấy IP của minikube
minikube ip
# Ví dụ: 192.168.49.2

# Truy cập API Gateway
# http://192.168.49.2:30080

# Hoặc sử dụng port-forward
kubectl port-forward svc/api-gateway 8080:80 -n movie-booking-local

# Mở dashboard minikube
minikube dashboard
```

### Kind / Docker Desktop

```bash
# Truy cập trực tiếp
# http://localhost:30080

# Hoặc port-forward
kubectl port-forward svc/api-gateway 8080:80 -n movie-booking-local
```

### API Endpoints

| Service       | Endpoint                 | Mô tả              |
| ------------- | ------------------------ | ------------------ |
| Auth          | `/api/v1/auth/`          | Đăng ký, đăng nhập |
| Users         | `/api/v1/users/`         | Quản lý người dùng |
| Movies        | `/api/v1/movies/`        | Danh sách phim     |
| Showtimes     | `/api/v1/showtimes/`     | Lịch chiếu         |
| Bookings      | `/api/v1/bookings/`      | Đặt vé             |
| Seats         | `/api/v1/seats/`         | Ghế ngồi           |
| Payments      | `/api/v1/payments/`      | Thanh toán         |
| Notifications | `/api/v1/notifications/` | Thông báo          |

### Ví dụ test API

```bash
# Health check
curl http://localhost:8080/health

# Đăng ký user
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "Password123!", "full_name": "Test User"}'

# Đăng nhập
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "Password123!"}'

# Lấy danh sách phim
curl http://localhost:8080/api/v1/movies/
```

---

## 📊 Giám sát và Debug

### Xem logs

```bash
# Logs của một service
kubectl logs -f -l app=auth-service -n movie-booking-local

# Logs tất cả services
kubectl logs -f -l tier=backend -n movie-booking-local --all-containers

# Logs với timestamp
kubectl logs -f deployment/auth-service -n movie-booking-local --timestamps
```

### Kiểm tra trạng thái

```bash
# Pods
kubectl get pods -n movie-booking-local -o wide

# Services
kubectl get svc -n movie-booking-local

# Deployments
kubectl get deployments -n movie-booking-local

# Events
kubectl get events -n movie-booking-local --sort-by='.lastTimestamp'

# Describe pod
kubectl describe pod <pod-name> -n movie-booking-local
```

### Exec vào container

```bash
# Exec vào pod
kubectl exec -it <pod-name> -n movie-booking-local -- /bin/sh

# Chạy command
kubectl exec -it <pod-name> -n movie-booking-local -- python -c "print('Hello')"
```

### Port-forward các services

```bash
# API Gateway
kubectl port-forward svc/api-gateway 8080:80 -n movie-booking-local &

# RabbitMQ Management
kubectl port-forward svc/rabbitmq-service 15672:15672 -n movie-booking-local &
# Truy cập: http://localhost:15672 (local_admin / rabbitmq_local_123)

# PostgreSQL (auth)
kubectl port-forward svc/postgres-auth-service 5432:5432 -n movie-booking-local &
```

---

## 🔧 Troubleshooting

### Lỗi thường gặp

#### 1. Pod ở trạng thái Pending

```bash
# Kiểm tra events
kubectl describe pod <pod-name> -n movie-booking-local

# Nguyên nhân thường gặp:
# - Không đủ resources → Giảm requests trong values-local.yaml
# - PVC không bind được → Kiểm tra StorageClass
```

#### 2. Pod CrashLoopBackOff

```bash
# Xem logs
kubectl logs <pod-name> -n movie-booking-local --previous

# Nguyên nhân thường gặp:
# - Database chưa sẵn sàng → Chờ database khởi động
# - Config sai → Kiểm tra environment variables
```

#### 3. Service không thể kết nối

```bash
# Kiểm tra endpoints
kubectl get endpoints -n movie-booking-local

# Kiểm tra DNS
kubectl run -it --rm debug --image=busybox --restart=Never -- nslookup auth-service.movie-booking-local.svc.cluster.local
```

#### 4. Image pull error

```bash
# Với minikube, đảm bảo đã cấu hình Docker env
eval $(minikube docker-env)

# Rebuild image
docker build -t movie-booking/auth-service:local services/auth-service

# Với kind, load image
kind load docker-image movie-booking/auth-service:local --name movie-booking
```

#### 5. Minikube không khởi động

```bash
# Xóa và tạo lại
minikube delete
minikube start --cpus=4 --memory=8192 --driver=docker

# Kiểm tra Docker đang chạy
docker info
```

### Reset môi trường

```bash
# Xóa namespace
kubectl delete namespace movie-booking-local

# Xóa Helm release
helm uninstall movie-booking -n movie-booking-local

# Reset minikube
minikube delete
minikube start

# Xóa tất cả Docker images local
docker rmi $(docker images -q "movie-booking/*")
```

---

## 📝 Ghi chú quan trọng

1. **Credentials local** (CHỈ dùng cho development):
   - PostgreSQL: `<service>_user` / `<service>_local_123`
   - Redis: `redis_local_123`
   - RabbitMQ: `local_admin` / `rabbitmq_local_123`
   - JWT Secret: `local_dev_jwt_secret_key_not_for_production_use_12345`

2. **Resources đã được tối ưu** cho local:
   - Mỗi service: 50m CPU request, 128Mi memory
   - Autoscaling đã được tắt
   - Chỉ 1 replica cho mỗi service

3. **Data persistence**:
   - Sử dụng emptyDir cho local (data mất khi pod restart)
   - Để giữ data, cần cấu hình PV/PVC riêng

---

## 🔗 Tài liệu tham khảo

- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Minikube Documentation](https://minikube.sigs.k8s.io/docs/)
- [Helm Documentation](https://helm.sh/docs/)
- [Skaffold Documentation](https://skaffold.dev/docs/)
- [Kind Documentation](https://kind.sigs.k8s.io/)
