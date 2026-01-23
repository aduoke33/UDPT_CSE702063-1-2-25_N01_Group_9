# =====================================================
# BOOKING SERVICE UNIT TESTS
# Movie Booking System - Booking & Distributed Lock Tests
# =====================================================
import pytest
from httpx import AsyncClient
from unittest.mock import patch, AsyncMock
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app, CircuitBreaker


class TestCircuitBreaker:
    """Test Circuit Breaker pattern implementation."""
    
    def test_circuit_breaker_initial_state(self):
        """Test circuit breaker starts in CLOSED state."""
        cb = CircuitBreaker(failure_threshold=3, recovery_timeout=10)
        assert cb.state == "CLOSED"
        assert cb.can_execute() is True
    
    def test_circuit_breaker_opens_after_failures(self):
        """Test circuit breaker opens after threshold failures."""
        cb = CircuitBreaker(failure_threshold=3, recovery_timeout=10)
        
        for _ in range(3):
            cb.record_failure()
        
        assert cb.state == "OPEN"
        assert cb.can_execute() is False
    
    def test_circuit_breaker_success_resets_failures(self):
        """Test successful call resets failure count."""
        cb = CircuitBreaker(failure_threshold=3, recovery_timeout=10)
        
        cb.record_failure()
        cb.record_failure()
        cb.record_success()
        
        assert cb.failures == 0
        assert cb.state == "CLOSED"


class TestHealthEndpoint:
    """Test health check endpoint."""
    
    @pytest.mark.asyncio
    async def test_health_endpoint(self):
        """Test health endpoint returns OK."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/health")
            assert response.status_code == 200
            data = response.json()
            assert data["status"] == "healthy"


class TestBookingEndpoint:
    """Test booking endpoints."""
    
    @pytest.mark.asyncio
    async def test_booking_requires_auth(self):
        """Test booking endpoint requires authentication."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.post(
                "/book",
                json={
                    "showtime_id": "00000000-0000-0000-0000-000000000000",
                    "seat_ids": ["00000000-0000-0000-0000-000000000001"]
                }
            )
            assert response.status_code == 401
    
    @pytest.mark.asyncio
    async def test_get_bookings_requires_auth(self):
        """Test getting bookings requires authentication."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/bookings")
            assert response.status_code == 401
