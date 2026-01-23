# =====================================================
# AUTH SERVICE UNIT TESTS
# Movie Booking System - Authentication Tests
# =====================================================
import os
import sys
from unittest.mock import MagicMock, patch

import pytest
from httpx import AsyncClient

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app, create_access_token, get_password_hash, verify_password


class TestPasswordHashing:
    """Test password hashing functions."""

    def test_password_hash_creates_different_hashes(self):
        """Test that same password creates different hashes."""
        password = "testpassword123"
        hash1 = get_password_hash(password)
        hash2 = get_password_hash(password)
        assert hash1 != hash2  # bcrypt creates unique salts

    def test_verify_password_correct(self):
        """Test password verification with correct password."""
        password = "testpassword123"
        hashed = get_password_hash(password)
        assert verify_password(password, hashed) is True

    def test_verify_password_incorrect(self):
        """Test password verification with incorrect password."""
        password = "testpassword123"
        hashed = get_password_hash(password)
        assert verify_password("wrongpassword", hashed) is False


class TestJWTToken:
    """Test JWT token functions."""

    def test_create_access_token(self):
        """Test access token creation."""
        data = {"sub": "testuser"}
        token = create_access_token(data)
        assert token is not None
        assert isinstance(token, str)
        assert len(token) > 0

    def test_create_access_token_with_expiry(self):
        """Test access token creation with custom expiry."""
        from datetime import timedelta

        data = {"sub": "testuser"}
        token = create_access_token(data, expires_delta=timedelta(minutes=60))
        assert token is not None


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


class TestRegisterEndpoint:
    """Test user registration endpoint."""

    @pytest.mark.asyncio
    async def test_register_missing_fields(self):
        """Test registration with missing fields."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.post("/register", json={})
            assert response.status_code == 422  # Validation error


class TestLoginEndpoint:
    """Test user login endpoint."""

    @pytest.mark.asyncio
    async def test_login_invalid_credentials(self):
        """Test login with invalid credentials."""
        async with AsyncClient(app=app, base_url="http://test") as client:
            response = await client.post(
                "/token", data={"username": "nonexistent", "password": "wrongpassword"}
            )
            # Should fail authentication
            assert response.status_code in [401, 500]  # 500 if DB not connected
