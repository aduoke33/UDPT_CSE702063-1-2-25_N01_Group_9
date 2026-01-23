#!/bin/bash
# =====================================================
# API TEST SCRIPT
# Movie Booking System - Automated API Testing
# =====================================================

set -e

BASE_URL="${BASE_URL:-http://localhost:80}"
AUTH_URL="${BASE_URL}/api/auth"
MOVIE_URL="${BASE_URL}/api/movies"
BOOKING_URL="${BASE_URL}/api/bookings"
PAYMENT_URL="${BASE_URL}/api/payments"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test counter
PASSED=0
FAILED=0

print_test() {
    echo -e "${YELLOW}[TEST] $1${NC}"
}

print_pass() {
    echo -e "${GREEN}[PASS] $1${NC}"
    ((PASSED++))
}

print_fail() {
    echo -e "${RED}[FAIL] $1${NC}"
    ((FAILED++))
}

# Test health endpoints
test_health() {
    print_test "Testing health endpoints..."
    
    for port in 8001 8002 8003 8004 8005; do
        response=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${port}/health" || echo "000")
        if [ "$response" == "200" ]; then
            print_pass "Service on port ${port} is healthy"
        else
            print_fail "Service on port ${port} returned ${response}"
        fi
    done
}

# Test user registration
test_register() {
    print_test "Testing user registration..."
    
    RANDOM_USER="testuser_$(date +%s)"
    response=$(curl -s -X POST "${AUTH_URL}/register" \
        -H "Content-Type: application/json" \
        -d "{
            \"email\": \"${RANDOM_USER}@example.com\",
            \"username\": \"${RANDOM_USER}\",
            \"password\": \"password123\",
            \"full_name\": \"Test User\"
        }")
    
    if echo "$response" | grep -q "id"; then
        print_pass "User registration successful"
        echo "  User: ${RANDOM_USER}"
    else
        print_fail "User registration failed: $response"
    fi
    
    export TEST_USER="${RANDOM_USER}"
}

# Test user login
test_login() {
    print_test "Testing user login..."
    
    response=$(curl -s -X POST "${AUTH_URL}/token" \
        -d "username=${TEST_USER}&password=password123")
    
    if echo "$response" | grep -q "access_token"; then
        print_pass "User login successful"
        export TOKEN=$(echo "$response" | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
        echo "  Token received"
    else
        print_fail "User login failed: $response"
    fi
}

# Test token verification
test_verify() {
    print_test "Testing token verification..."
    
    response=$(curl -s -X GET "${AUTH_URL}/verify" \
        -H "Authorization: Bearer ${TOKEN}")
    
    if echo "$response" | grep -q "username"; then
        print_pass "Token verification successful"
    else
        print_fail "Token verification failed: $response"
    fi
}

# Test get movies
test_get_movies() {
    print_test "Testing get movies..."
    
    response=$(curl -s -X GET "${MOVIE_URL}/movies")
    
    if echo "$response" | grep -q "\[" || echo "$response" | grep -q "id"; then
        print_pass "Get movies successful"
    else
        print_fail "Get movies failed: $response"
    fi
}

# Test get showtimes
test_get_showtimes() {
    print_test "Testing get showtimes..."
    
    response=$(curl -s -X GET "${MOVIE_URL}/showtimes")
    
    if echo "$response" | grep -q "\[" || echo "$response" | grep -q "id"; then
        print_pass "Get showtimes successful"
    else
        print_fail "Get showtimes failed: $response"
    fi
}

# Test get theaters
test_get_theaters() {
    print_test "Testing get theaters..."
    
    response=$(curl -s -X GET "${MOVIE_URL}/theaters")
    
    if echo "$response" | grep -q "\[" || echo "$response" | grep -q "id"; then
        print_pass "Get theaters successful"
    else
        print_fail "Get theaters failed: $response"
    fi
}

# Print summary
print_summary() {
    echo ""
    echo "========================================="
    echo "           TEST SUMMARY"
    echo "========================================="
    echo -e "Passed: ${GREEN}${PASSED}${NC}"
    echo -e "Failed: ${RED}${FAILED}${NC}"
    echo "========================================="
    
    if [ $FAILED -gt 0 ]; then
        exit 1
    fi
}

# Run all tests
main() {
    echo "========================================="
    echo "  Movie Booking API Tests"
    echo "========================================="
    echo ""
    
    test_health
    test_register
    test_login
    test_verify
    test_get_movies
    test_get_showtimes
    test_get_theaters
    
    print_summary
}

main "$@"
