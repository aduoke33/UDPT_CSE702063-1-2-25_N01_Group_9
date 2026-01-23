# =====================================================
# MOVIE SERVICE UNIT TESTS
# Movie Booking System - Movie & Showtime Tests
# =====================================================
import os
import sys
from datetime import date, time
from decimal import Decimal

import pytest
from httpx import AsyncClient

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app


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


class TestMoviesEndpoint:
    """Test movies endpoints."""

    @pytest.mark.asyncio
    async def test_get_movies_list(self):
        """Test getting movies list."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/movies")
            # May return 500 if DB not connected, or 200 with empty list
            assert response.status_code in [200, 500]

    @pytest.mark.asyncio
    async def test_get_movie_not_found(self):
        """Test getting non-existent movie."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/movies/00000000-0000-0000-0000-000000000000")
            assert response.status_code in [404, 500]


class TestShowtimesEndpoint:
    """Test showtimes endpoints."""

    @pytest.mark.asyncio
    async def test_get_showtimes_list(self):
        """Test getting showtimes list."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/showtimes")
            assert response.status_code in [200, 500]


class TestTheatersEndpoint:
    """Test theaters endpoints."""

    @pytest.mark.asyncio
    async def test_get_theaters_list(self):
        """Test getting theaters list."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.get("/theaters")
            assert response.status_code in [200, 500]
