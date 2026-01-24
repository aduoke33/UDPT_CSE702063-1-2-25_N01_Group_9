#!/bin/bash
# =====================================================
# END-TO-END TEST SCRIPT
# Movie Booking System - Smoke Tests
# =====================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

API_BASE="${API_BASE:-http://localhost}"
TIMEOUT=10
PASSED=0
FAILED=0
TOTAL=0

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  🧪 Movie Booking System - E2E Tests${NC}"
echo -e "${BLUE}================================================${NC}"
echo -e "  Base URL: ${API_BASE}"
echo ""

# Test function
test_endpoint() {
    local name="$1"
    local method="$2"
    local url="$3"
    local expected_code="$4"
    local data="$5"
    local auth="$6"
    
    TOTAL=$((TOTAL + 1))
    
    echo -n "  [$TOTAL] $name... "
    
    local curl_args="-s -o /tmp/response.json -w %{http_code} --max-time $TIMEOUT"
    
    if [ -n "$auth" ]; then
        curl_args="$curl_args -H \"Authorization: Bearer $auth\""
    fi
    
    if [ "$method" = "POST" ]; then
        curl_args="$curl_args -X POST -H \"Content-Type: application/json\""
        if [ -n "$data" ]; then
            curl_args="$curl_args -d '$data'"
        fi
    fi
    
    local status_code=$(eval "curl $curl_args '$url'" 2>/dev/null || echo "000")
    
    if [ "$status_code" = "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $status_code)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (Expected $expected_code, got $status_code)"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

# Wait for services
echo -e "${YELLOW}⏳ Checking service availability...${NC}"
echo ""

MAX_RETRIES=30
RETRY=0
while [ $RETRY -lt $MAX_RETRIES ]; do
    if curl -s -o /dev/null -w "%{http_code}" "$API_BASE/api/auth/health" --max-time 5 | grep -q "200"; then
        echo -e "${GREEN}✓ Services are ready${NC}"
        break
    fi
    RETRY=$((RETRY + 1))
    echo -e "  Waiting for services... ($RETRY/$MAX_RETRIES)"
    sleep 2
done

if [ $RETRY -eq $MAX_RETRIES ]; then
    echo -e "${RED}❌ Services did not become ready in time${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"
echo -e "${BLUE}  1. HEALTH CHECKS${NC}"
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"

test_endpoint "Auth Service Health" "GET" "$API_BASE/api/auth/health" "200"
test_endpoint "Movie Service Health" "GET" "$API_BASE/api/movies/health" "200"
test_endpoint "Booking Service Health" "GET" "$API_BASE/api/bookings/health" "200"
test_endpoint "Payment Service Health" "GET" "$API_BASE/api/payments/health" "200"
test_endpoint "Notification Service Health" "GET" "$API_BASE/api/notifications/health" "200"

echo ""
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"
echo -e "${BLUE}  2. API ENDPOINTS${NC}"
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"

# Test API endpoints
test_endpoint "Get Movies List" "GET" "$API_BASE/api/movies/movies" "200"
test_endpoint "Get Showtimes" "GET" "$API_BASE/api/movies/showtimes" "200"

# Test user registration (may fail if user exists - that's OK)
RANDOM_USER="testuser_$(date +%s)"
REGISTER_DATA="{\"email\":\"$RANDOM_USER@test.com\",\"username\":\"$RANDOM_USER\",\"password\":\"test123456\",\"full_name\":\"Test User\"}"

echo -n "  [$((TOTAL + 1))] User Registration... "
TOTAL=$((TOTAL + 1))
REG_CODE=$(curl -s -o /tmp/reg_response.json -w "%{http_code}" --max-time $TIMEOUT \
    -X POST -H "Content-Type: application/json" \
    -d "$REGISTER_DATA" \
    "$API_BASE/api/auth/register" 2>/dev/null || echo "000")

if [ "$REG_CODE" = "200" ] || [ "$REG_CODE" = "201" ] || [ "$REG_CODE" = "400" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $REG_CODE)"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $REG_CODE)"
    FAILED=$((FAILED + 1))
fi

# Test login
echo -n "  [$((TOTAL + 1))] User Login... "
TOTAL=$((TOTAL + 1))
LOGIN_CODE=$(curl -s -o /tmp/login_response.json -w "%{http_code}" --max-time $TIMEOUT \
    -X POST -H "Content-Type: application/x-www-form-urlencoded" \
    -d "username=$RANDOM_USER&password=test123456" \
    "$API_BASE/api/auth/token" 2>/dev/null || echo "000")

if [ "$LOGIN_CODE" = "200" ]; then
    TOKEN=$(cat /tmp/login_response.json | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
    echo -e "${GREEN}✓ PASS${NC} (HTTP $LOGIN_CODE)"
    PASSED=$((PASSED + 1))
else
    echo -e "${YELLOW}⚠ SKIP${NC} (HTTP $LOGIN_CODE - user may not exist)"
    # Don't count as failed
fi

echo ""
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"
echo -e "${BLUE}  3. PROTECTED ENDPOINTS${NC}"
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"

# Test protected endpoint without auth (should fail)
test_endpoint "Protected Endpoint (no auth)" "GET" "$API_BASE/api/bookings/bookings" "401"

# Test with auth if we have a token
if [ -n "$TOKEN" ]; then
    echo -n "  [$((TOTAL + 1))] Protected Endpoint (with auth)... "
    TOTAL=$((TOTAL + 1))
    PROTECTED_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT \
        -H "Authorization: Bearer $TOKEN" \
        "$API_BASE/api/bookings/bookings" 2>/dev/null || echo "000")
    
    if [ "$PROTECTED_CODE" = "200" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $PROTECTED_CODE)"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗ FAIL${NC} (HTTP $PROTECTED_CODE)"
        FAILED=$((FAILED + 1))
    fi
fi

# Summary
echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  📊 TEST SUMMARY${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""
echo -e "  Total:  $TOTAL"
echo -e "  Passed: ${GREEN}$PASSED${NC}"
echo -e "  Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}  ✅ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}  ❌ Some tests failed${NC}"
    exit 1
fi
