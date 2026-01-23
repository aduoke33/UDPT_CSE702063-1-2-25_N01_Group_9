# =====================================================
# PYTEST CONFIGURATION
# Movie Booking System - Test Configuration
# =====================================================
import pytest
import asyncio
from typing import AsyncGenerator
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker

# Test database URL
TEST_DATABASE_URL = "postgresql+asyncpg://admin:admin123@localhost:5432/movie_booking_test"

@pytest.fixture(scope="session")
def event_loop():
    """Create an instance of the default event loop for each test case."""
    loop = asyncio.get_event_loop_policy().new_event_loop()
    yield loop
    loop.close()

@pytest.fixture(scope="session")
async def test_engine():
    """Create async engine for testing."""
    engine = create_async_engine(TEST_DATABASE_URL, echo=True)
    yield engine
    await engine.dispose()

@pytest.fixture(scope="function")
async def test_session(test_engine) -> AsyncGenerator[AsyncSession, None]:
    """Create a new database session for a test."""
    async_session = async_sessionmaker(
        test_engine, 
        class_=AsyncSession, 
        expire_on_commit=False
    )
    async with async_session() as session:
        yield session
        await session.rollback()

@pytest.fixture
def anyio_backend():
    return 'asyncio'
