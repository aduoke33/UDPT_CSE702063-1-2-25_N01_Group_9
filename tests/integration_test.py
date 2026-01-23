"""
Integration Tests for Movie Booking System
Tests the complete flow across services
"""

import pytest
import httpx
import asyncio
from typing import Optional

BASE_URL = "http://localhost:80"


class TestAuthenticationFlow:
    """Test authentication flow"""
    
    @pytest.fixture
    def test_user(self):
        import uuid
        return {
            "username": f"test_{uuid.uuid4().hex[:8]}",
            "email": f"test_{uuid.uuid4().hex[:8]}@test.com",
            "password": "Test@123456",
            "full_name": "Test User"
        }
    
    @pytest.mark.asyncio
    async def test_register_user(self, test_user):
        """Test user registration"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            response = await client.post("/api/auth/register", json=test_user)
            assert response.status_code == 200
            data = response.json()
            assert "id" in data
            assert data["email"] == test_user["email"]
    
    @pytest.mark.asyncio
    async def test_login_user(self, test_user):
        """Test user login"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            # Register first
            await client.post("/api/auth/register", json=test_user)
            
            # Login
            response = await client.post(
                "/api/auth/token",
                data={
                    "username": test_user["username"],
                    "password": test_user["password"]
                }
            )
            assert response.status_code == 200
            data = response.json()
            assert "access_token" in data
            assert data["token_type"] == "bearer"
    
    @pytest.mark.asyncio
    async def test_verify_token(self, test_user):
        """Test token verification"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            # Register and login
            await client.post("/api/auth/register", json=test_user)
            login_response = await client.post(
                "/api/auth/token",
                data={
                    "username": test_user["username"],
                    "password": test_user["password"]
                }
            )
            token = login_response.json()["access_token"]
            
            # Verify token
            response = await client.get(
                "/api/auth/verify",
                headers={"Authorization": f"Bearer {token}"}
            )
            assert response.status_code == 200
            data = response.json()
            assert data["username"] == test_user["username"]


class TestMovieService:
    """Test movie service"""
    
    @pytest.mark.asyncio
    async def test_get_movies(self):
        """Test getting movie list"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            response = await client.get("/api/movies/movies")
            assert response.status_code == 200
            data = response.json()
            assert isinstance(data, list)
    
    @pytest.mark.asyncio
    async def test_get_showtimes(self):
        """Test getting showtimes"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            response = await client.get("/api/movies/showtimes")
            assert response.status_code == 200
            data = response.json()
            assert isinstance(data, list)
    
    @pytest.mark.asyncio
    async def test_get_theaters(self):
        """Test getting theaters"""
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            response = await client.get("/api/movies/theaters")
            assert response.status_code == 200
            data = response.json()
            assert isinstance(data, list)


class TestBookingFlow:
    """Test complete booking flow"""
    
    @pytest.fixture
    async def authenticated_client(self):
        """Create authenticated client"""
        import uuid
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            user = {
                "username": f"test_{uuid.uuid4().hex[:8]}",
                "email": f"test_{uuid.uuid4().hex[:8]}@test.com",
                "password": "Test@123456",
                "full_name": "Test User"
            }
            
            await client.post("/api/auth/register", json=user)
            response = await client.post(
                "/api/auth/token",
                data={
                    "username": user["username"],
                    "password": user["password"]
                }
            )
            token = response.json()["access_token"]
            
            yield client, token
    
    @pytest.mark.asyncio
    async def test_create_booking(self, authenticated_client):
        """Test creating a booking"""
        client, token = authenticated_client
        
        # Get available showtimes
        showtimes_response = await client.get("/api/movies/showtimes")
        showtimes = showtimes_response.json()
        
        if not showtimes:
            pytest.skip("No showtimes available")
        
        showtime = showtimes[0]
        
        # Create booking
        response = await client.post(
            "/api/bookings/bookings",
            json={
                "showtime_id": showtime["id"],
                "seats": ["A1"]
            },
            headers={"Authorization": f"Bearer {token}"}
        )
        
        assert response.status_code in [200, 201]
        data = response.json()
        assert "booking_code" in data


class TestDistributedPatterns:
    """Test distributed system patterns"""
    
    @pytest.mark.asyncio
    async def test_concurrent_seat_booking(self):
        """Test distributed lock for concurrent seat booking"""
        # This test simulates multiple users trying to book the same seat
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            # Create multiple users
            users = []
            for i in range(3):
                import uuid
                user = {
                    "username": f"concurrent_{uuid.uuid4().hex[:8]}",
                    "email": f"concurrent_{uuid.uuid4().hex[:8]}@test.com",
                    "password": "Test@123456",
                    "full_name": f"Concurrent User {i}"
                }
                await client.post("/api/auth/register", json=user)
                response = await client.post(
                    "/api/auth/token",
                    data={"username": user["username"], "password": user["password"]}
                )
                users.append(response.json()["access_token"])
            
            # Get a showtime
            showtimes = (await client.get("/api/movies/showtimes")).json()
            if not showtimes:
                pytest.skip("No showtimes available")
            
            showtime_id = showtimes[0]["id"]
            
            # Try concurrent bookings for same seat
            async def book_seat(token):
                return await client.post(
                    "/api/bookings/bookings",
                    json={
                        "showtime_id": showtime_id,
                        "seats": ["Z1"]  # Same seat for all
                    },
                    headers={"Authorization": f"Bearer {token}"}
                )
            
            results = await asyncio.gather(
                *[book_seat(token) for token in users],
                return_exceptions=True
            )
            
            # Only one should succeed
            successes = [r for r in results if not isinstance(r, Exception) and r.status_code in [200, 201]]
            assert len(successes) <= 1, "Distributed lock should prevent double booking"
    
    @pytest.mark.asyncio
    async def test_idempotent_payment(self):
        """Test idempotency for payment processing"""
        import uuid
        idempotency_key = str(uuid.uuid4())
        
        async with httpx.AsyncClient(base_url=BASE_URL) as client:
            # Create user and get token
            user = {
                "username": f"payment_{uuid.uuid4().hex[:8]}",
                "email": f"payment_{uuid.uuid4().hex[:8]}@test.com",
                "password": "Test@123456",
                "full_name": "Payment Test User"
            }
            await client.post("/api/auth/register", json=user)
            response = await client.post(
                "/api/auth/token",
                data={"username": user["username"], "password": user["password"]}
            )
            token = response.json()["access_token"]
            headers = {"Authorization": f"Bearer {token}"}
            
            # Create a booking first
            showtimes = (await client.get("/api/movies/showtimes")).json()
            if not showtimes:
                pytest.skip("No showtimes available")
            
            booking_response = await client.post(
                "/api/bookings/bookings",
                json={
                    "showtime_id": showtimes[0]["id"],
                    "seats": ["Y1"]
                },
                headers=headers
            )
            
            if booking_response.status_code not in [200, 201]:
                pytest.skip("Could not create booking")
            
            booking = booking_response.json()
            
            # Make same payment request twice with same idempotency key
            payment_data = {
                "booking_id": booking["id"],
                "amount": booking.get("total_price", 100000),
                "payment_method": "credit_card",
                "idempotency_key": idempotency_key
            }
            
            response1 = await client.post(
                "/api/payments/payments",
                json=payment_data,
                headers=headers
            )
            
            response2 = await client.post(
                "/api/payments/payments",
                json=payment_data,
                headers=headers
            )
            
            # Both should return same result (idempotency)
            if response1.status_code == 200 and response2.status_code == 200:
                assert response1.json()["id"] == response2.json()["id"]


class TestHealthEndpoints:
    """Test health check endpoints"""
    
    @pytest.mark.asyncio
    async def test_all_services_healthy(self):
        """Test all services are healthy"""
        async with httpx.AsyncClient(base_url=BASE_URL, timeout=10.0) as client:
            services = [
                "/api/auth/health",
                "/api/movies/health",
                "/api/bookings/health",
                "/api/payments/health",
                "/api/notifications/health"
            ]
            
            for service in services:
                response = await client.get(service)
                assert response.status_code == 200, f"{service} is not healthy"


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
