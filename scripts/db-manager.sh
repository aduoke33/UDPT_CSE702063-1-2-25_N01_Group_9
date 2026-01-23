#!/bin/bash
# =====================================================
# Database Management Scripts
# Movie Booking System - Database Per Service
# =====================================================

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Database configurations
declare -A DATABASES=(
    ["auth"]="postgres-auth:5433:auth_user:auth_password123:auth_db"
    ["movie"]="postgres-movie:5434:movie_user:movie_password123:movies_db"
    ["booking"]="postgres-booking:5435:booking_user:booking_password123:bookings_db"
    ["payment"]="postgres-payment:5436:payment_user:payment_password123:payments_db"
    ["notification"]="postgres-notification:5437:notification_user:notification_password123:notifications_db"
)

function print_header() {
    echo -e "${GREEN}================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}================================${NC}"
}

function check_all_databases() {
    print_header "Checking All Databases"
    
    for service in "${!DATABASES[@]}"; do
        IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
        
        echo -e "\n${YELLOW}Checking $service database...${NC}"
        if docker exec "$container" pg_isready -U "$user" > /dev/null 2>&1; then
            echo -e "${GREEN}✓ $service database is ready${NC}"
        else
            echo -e "${RED}✗ $service database is NOT ready${NC}"
        fi
    done
}

function backup_database() {
    local service=$1
    
    if [ -z "$service" ]; then
        echo -e "${RED}Usage: $0 backup <service>${NC}"
        echo -e "Available services: auth, movie, booking, payment, notification"
        exit 1
    fi
    
    IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
    
    print_header "Backing up $service database"
    
    timestamp=$(date +%Y%m%d_%H%M%S)
    backup_file="backup_${db}_${timestamp}.sql"
    
    echo "Creating backup: $backup_file"
    docker exec "$container" pg_dump -U "$user" "$db" > "$backup_file"
    
    echo -e "${GREEN}✓ Backup completed: $backup_file${NC}"
}

function backup_all_databases() {
    print_header "Backing up All Databases"
    
    for service in "${!DATABASES[@]}"; do
        backup_database "$service"
    done
}

function restore_database() {
    local service=$1
    local backup_file=$2
    
    if [ -z "$service" ] || [ -z "$backup_file" ]; then
        echo -e "${RED}Usage: $0 restore <service> <backup_file>${NC}"
        exit 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        echo -e "${RED}Backup file not found: $backup_file${NC}"
        exit 1
    fi
    
    IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
    
    print_header "Restoring $service database from $backup_file"
    
    echo -e "${YELLOW}WARNING: This will overwrite the existing database!${NC}"
    read -p "Are you sure? (yes/no) " -n 3 -r
    echo
    
    if [[ $REPLY =~ ^yes$ ]]; then
        cat "$backup_file" | docker exec -i "$container" psql -U "$user" "$db"
        echo -e "${GREEN}✓ Restore completed${NC}"
    else
        echo "Restore cancelled"
    fi
}

function connect_to_database() {
    local service=$1
    
    if [ -z "$service" ]; then
        echo -e "${RED}Usage: $0 connect <service>${NC}"
        echo -e "Available services: auth, movie, booking, payment, notification"
        exit 1
    fi
    
    IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
    
    print_header "Connecting to $service database"
    
    docker exec -it "$container" psql -U "$user" "$db"
}

function show_database_stats() {
    print_header "Database Statistics"
    
    for service in "${!DATABASES[@]}"; do
        IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
        
        echo -e "\n${YELLOW}=== $service Database ===${NC}"
        
        # Get database size
        size=$(docker exec "$container" psql -U "$user" "$db" -t -c "SELECT pg_size_pretty(pg_database_size('$db'));" 2>/dev/null | xargs)
        echo "Size: $size"
        
        # Get table count
        tables=$(docker exec "$container" psql -U "$user" "$db" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null | xargs)
        echo "Tables: $tables"
        
        # Get connection count
        connections=$(docker exec "$container" psql -U "$user" "$db" -t -c "SELECT count(*) FROM pg_stat_activity WHERE datname='$db';" 2>/dev/null | xargs)
        echo "Active connections: $connections"
    done
}

function reset_database() {
    local service=$1
    
    if [ -z "$service" ]; then
        echo -e "${RED}Usage: $0 reset <service>${NC}"
        echo -e "Available services: auth, movie, booking, payment, notification"
        exit 1
    fi
    
    IFS=':' read -r container port user password db <<< "${DATABASES[$service]}"
    
    print_header "Resetting $service database"
    
    echo -e "${RED}WARNING: This will DELETE ALL DATA in $service database!${NC}"
    read -p "Are you sure? Type 'DELETE' to confirm: " -r
    echo
    
    if [[ $REPLY == "DELETE" ]]; then
        docker exec "$container" psql -U "$user" -c "DROP DATABASE IF EXISTS $db;"
        docker exec "$container" psql -U "$user" -c "CREATE DATABASE $db;"
        echo -e "${GREEN}✓ Database reset completed${NC}"
    else
        echo "Reset cancelled"
    fi
}

function show_help() {
    cat << EOF
Database Management Tool for Movie Booking System

Usage: $0 <command> [options]

Commands:
  check                     - Check status of all databases
  stats                     - Show database statistics
  backup <service>          - Backup a specific database
  backup-all                - Backup all databases
  restore <service> <file>  - Restore a database from backup
  connect <service>         - Connect to a database via psql
  reset <service>           - Reset a database (DELETE ALL DATA)
  help                      - Show this help message

Services:
  auth, movie, booking, payment, notification

Examples:
  $0 check
  $0 backup auth
  $0 backup-all
  $0 restore auth backup_auth_db_20260123_120000.sql
  $0 connect booking
  $0 stats
  $0 reset payment

EOF
}

# Main script logic
case "${1:-help}" in
    check)
        check_all_databases
        ;;
    stats)
        show_database_stats
        ;;
    backup)
        backup_database "$2"
        ;;
    backup-all)
        backup_all_databases
        ;;
    restore)
        restore_database "$2" "$3"
        ;;
    connect)
        connect_to_database "$2"
        ;;
    reset)
        reset_database "$2"
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_help
        exit 1
        ;;
esac
