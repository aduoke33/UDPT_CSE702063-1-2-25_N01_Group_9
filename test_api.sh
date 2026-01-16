#!/bin/bash

# Movie Booking System - API Testing Script
# ==========================================

API_BASE="http://localhost"
AUTH_SERVICE="${API_BASE}/api/auth"
MOVIE_SERVICE="${API_BASE}/api/movies"
BOOKING_SERVICE="${API_BASE}/api/bookings"
PAYMENT_SERVICE="${API_BASE}/api/payments"

echo "================================================"
echo "Movie Booking System - API Test Suite"
echo "================================================"
echo ""

# 1. Register User
echo "1️⃣  Registering new user..."
REGISTER_RESPONSE=$(curl -s -X POST "${AUTH_SERVICE}/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "username": "testuser",
    "password": "test123456",
    "full_name": "Test User",
    "phone_number": "0123456789"
  }')

echo "Register Response: ${REGISTER_RESPONSE}"
echo ""

# 2. Login
echo "2️⃣  Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "${AUTH_SERVICE}/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=testuser&password=test123456")

TOKEN=$(echo ${LOGIN_RESPONSE} | grep -o '"access_token":"[^"]*' | sed 's/"access_token":"//')

echo "Login Response: ${LOGIN_RESPONSE}"
echo "Access Token: ${TOKEN}"
echo ""

# 3. Get Movies
echo "3️⃣  Getting movies list..."
MOVIES_RESPONSE=$(curl -s -X GET "${MOVIE_SERVICE}/movies")
echo "Movies: ${MOVIES_RESPONSE}"
echo ""

# Extract first movie ID
MOVIE_ID=$(echo ${MOVIES_RESPONSE} | grep -o '"id":"[^"]*' | head -1 | sed 's/"id":"//')
echo "Selected Movie ID: ${MOVIE_ID}"
echo ""

# 4. Get Showtimes
echo "4️⃣  Getting showtimes..."
SHOWTIMES_RESPONSE=$(curl -s -X GET "${MOVIE_SERVICE}/showtimes")
echo "Showtimes: ${SHOWTIMES_RESPONSE}"
echo ""

# Extract first showtime ID
SHOWTIME_ID=$(echo ${SHOWTIMES_RESPONSE} | grep -o '"id":"[^"]*' | head -1 | sed 's/"id":"//')
echo "Selected Showtime ID: ${SHOWTIME_ID}"
echo ""

# 5. Create Booking (with authentication)
echo "5️⃣  Creating booking..."
# Generate random seat IDs for testing
SEAT_ID_1="11111111-1111-1111-1111-111111111111"
SEAT_ID_2="22222222-2222-2222-2222-222222222222"

BOOKING_RESPONSE=$(curl -s -X POST "${BOOKING_SERVICE}/book" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "{
    \"showtime_id\": \"${SHOWTIME_ID}\",
    \"seat_ids\": [\"${SEAT_ID_1}\", \"${SEAT_ID_2}\"]
  }")

echo "Booking Response: ${BOOKING_RESPONSE}"
echo ""

# Extract booking ID
BOOKING_ID=$(echo ${BOOKING_RESPONSE} | grep -o '"id":"[^"]*' | head -1 | sed 's/"id":"//')
BOOKING_AMOUNT=$(echo ${BOOKING_RESPONSE} | grep -o '"total_price":"[^"]*' | sed 's/"total_price":"//')

echo "Booking ID: ${BOOKING_ID}"
echo "Amount: ${BOOKING_AMOUNT}"
echo ""

# 6. Get User's Bookings
echo "6️⃣  Getting user bookings..."
USER_BOOKINGS=$(curl -s -X GET "${BOOKING_SERVICE}/bookings" \
  -H "Authorization: Bearer ${TOKEN}")
echo "User Bookings: ${USER_BOOKINGS}"
echo ""

# 7. Process Payment
echo "7️⃣  Processing payment..."
PAYMENT_RESPONSE=$(curl -s -X POST "${PAYMENT_SERVICE}/process" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "{
    \"booking_id\": \"${BOOKING_ID}\",
    \"payment_method\": \"credit_card\",
    \"amount\": ${BOOKING_AMOUNT}
  }")

echo "Payment Response: ${PAYMENT_RESPONSE}"
echo ""

# 8. Get Payment History
echo "8️⃣  Getting payment history..."
PAYMENTS=$(curl -s -X GET "${PAYMENT_SERVICE}/payments" \
  -H "Authorization: Bearer ${TOKEN}")
echo "Payments: ${PAYMENTS}"
echo ""

# 9. Verify Token
echo "9️⃣  Verifying authentication token..."
VERIFY_RESPONSE=$(curl -s -X GET "${AUTH_SERVICE}/verify" \
  -H "Authorization: Bearer ${TOKEN}")
echo "Verify Response: ${VERIFY_RESPONSE}"
echo ""

# 10. Health Checks
echo "🏥 Service Health Checks..."
echo "Auth Service: $(curl -s ${AUTH_SERVICE}/health)"
echo "Movie Service: $(curl -s ${MOVIE_SERVICE}/health)"
echo "Booking Service: $(curl -s ${BOOKING_SERVICE}/health)"
echo "Payment Service: $(curl -s ${PAYMENT_SERVICE}/health)"
echo ""

echo "================================================"
echo "✅ API Testing Complete!"
echo "================================================"
