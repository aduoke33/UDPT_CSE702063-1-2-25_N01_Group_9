# Database Management Scripts

Các scripts tiện ích để quản lý hệ thống database-per-service của Movie Booking System.

## 📋 Danh sách Scripts

### 1. `db-manager.ps1` (Windows PowerShell)
Script quản lý databases trên Windows.

### 2. `db-manager.sh` (Linux/Mac Bash)
Script quản lý databases trên Linux/Mac.

### 3. `validate-databases.ps1` (Windows PowerShell)
Script kiểm tra toàn diện hệ thống databases.

## 🚀 Cách sử dụng

### Windows (PowerShell)

```powershell
# Kiểm tra trạng thái tất cả databases
.\scripts\db-manager.ps1 check

# Xem thống kê databases
.\scripts\db-manager.ps1 stats

# Backup một database
.\scripts\db-manager.ps1 backup auth

# Backup tất cả databases
.\scripts\db-manager.ps1 backup-all

# Restore database từ backup
.\scripts\db-manager.ps1 restore auth backup_auth_db_20260123_120000.sql

# Kết nối vào database qua psql
.\scripts\db-manager.ps1 connect booking

# Reset database (XÓA TOÀN BỘ DỮ LIỆU)
.\scripts\db-manager.ps1 reset payment

# Chạy validation đầy đủ
.\scripts\validate-databases.ps1
```

### Linux/Mac (Bash)

```bash
# Cấp quyền thực thi (chỉ cần làm 1 lần)
chmod +x scripts/db-manager.sh

# Kiểm tra trạng thái tất cả databases
./scripts/db-manager.sh check

# Xem thống kê databases
./scripts/db-manager.sh stats

# Backup một database
./scripts/db-manager.sh backup auth

# Backup tất cả databases
./scripts/db-manager.sh backup-all

# Restore database từ backup
./scripts/db-manager.sh restore auth backup_auth_db_20260123_120000.sql

# Kết nối vào database qua psql
./scripts/db-manager.sh connect booking

# Reset database (XÓA TOÀN BỘ DỮ LIỆU)
./scripts/db-manager.sh reset payment
```

## 📊 Các Service Databases

Hệ thống có 5 databases độc lập:

| Service | Database | Port | User |
|---------|----------|------|------|
| auth | auth_db | 5433 | auth_user |
| movie | movies_db | 5434 | movie_user |
| booking | bookings_db | 5435 | booking_user |
| payment | payments_db | 5436 | payment_user |
| notification | notifications_db | 5437 | notification_user |

## 🔍 Script Validation

Script `validate-databases.ps1` thực hiện kiểm tra toàn diện:

1. ✅ Kiểm tra Docker containers đang chạy
2. ✅ Test kết nối đến từng database
3. ✅ Verify database isolation (mỗi service có DB riêng)
4. ✅ Kiểm tra service endpoints (/health)
5. 📊 Hiển thị statistics (size, tables, connections)

**Ví dụ kết quả:**

```
======================================
Database Architecture Validation
======================================

Step 1: Checking Docker Containers
-----------------------------------
Checking Auth Service (postgres-auth)... ✓ Running
Checking Movie Service (postgres-movie)... ✓ Running
Checking Booking Service (postgres-booking)... ✓ Running
Checking Payment Service (postgres-payment)... ✓ Running
Checking Notification Service (postgres-notification)... ✓ Running

Step 2: Testing Database Connections
-------------------------------------
Testing Auth Service database... ✓ Connected
Testing Movie Service database... ✓ Connected
Testing Booking Service database... ✓ Connected
Testing Payment Service database... ✓ Connected
Testing Notification Service database... ✓ Connected

...

======================================
✓ All Tests Passed
Database-per-Service architecture is working correctly!
======================================
```

## 💾 Backup & Restore

### Backup Strategy

**Backup thủ công:**
```powershell
# Backup trước khi deploy
.\scripts\db-manager.ps1 backup-all

# File backup sẽ được tạo với tên:
# backup_<database>_<timestamp>.sql
# Ví dụ: backup_auth_db_20260123_143022.sql
```

**Scheduled Backup (Windows Task Scheduler):**
```powershell
# Tạo task backup hàng ngày lúc 2h sáng
$action = New-ScheduledTaskAction -Execute 'PowerShell.exe' `
  -Argument '-File "D:\path\to\scripts\db-manager.ps1" backup-all'
  
$trigger = New-ScheduledTaskTrigger -Daily -At 2am

Register-ScheduledTask -Action $action -Trigger $trigger `
  -TaskName "MovieBooking-DailyBackup" -Description "Daily database backup"
```

### Restore Process

```powershell
# 1. Xem danh sách backups
Get-ChildItem *.sql | Sort-Object LastWriteTime -Descending

# 2. Restore database
.\scripts\db-manager.ps1 restore auth backup_auth_db_20260123_143022.sql

# 3. Verify restore thành công
.\scripts\db-manager.ps1 connect auth
```

## 🛠️ Troubleshooting

### Database không kết nối được

```powershell
# Kiểm tra container đang chạy
docker ps | Select-String postgres

# Restart container nếu cần
docker restart postgres-auth

# Kiểm tra logs
docker logs postgres-auth
```

### Reset database khi gặp lỗi migration

```powershell
# 1. Backup trước (phòng xa)
.\scripts\db-manager.ps1 backup booking

# 2. Reset database
.\scripts\db-manager.ps1 reset booking

# 3. Chạy migrations lại
# (từ booking-service directory)
alembic upgrade head
```

### Xem kết nối hiện tại

```powershell
# Kết nối vào database
.\scripts\db-manager.ps1 connect auth

# Trong psql, chạy:
SELECT * FROM pg_stat_activity WHERE datname='auth_db';
```

## 📈 Best Practices

1. **Backup thường xuyên:** Backup trước mỗi deployment hoặc migration
2. **Test restore:** Định kỳ test restore backup để đảm bảo backup hoạt động
3. **Monitor size:** Theo dõi database size qua `stats` command
4. **Isolate environments:** Không restore production backup vào development
5. **Validate after changes:** Chạy `validate-databases.ps1` sau mỗi thay đổi infrastructure

## 🔒 Security Notes

⚠️ **QUAN TRỌNG:**
- Scripts chứa credentials trong code (chỉ dùng cho development)
- Trong production, sử dụng secrets management (Kubernetes Secrets, Azure Key Vault, AWS Secrets Manager)
- Không commit backup files (đã có trong .gitignore)
- Encrypt backups khi lưu trữ lâu dài

## 📚 Thêm thông tin

- [DATABASE_ARCHITECTURE.md](../DATABASE_ARCHITECTURE.md) - Chi tiết kiến trúc database-per-service
- [docker-compose.yml](../docker-compose.yml) - Cấu hình Docker containers
- [k8s/database/](../k8s/database/) - Kubernetes manifests cho production

## 🆘 Support

Nếu gặp vấn đề:
1. Chạy `validate-databases.ps1` để diagnostic
2. Kiểm tra logs: `docker logs <container-name>`
3. Verify database URLs trong docker-compose.yml
4. Kiểm tra port conflicts: `netstat -ano | findstr "543"`
