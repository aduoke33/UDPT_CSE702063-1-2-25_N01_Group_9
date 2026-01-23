"""
Shared Database Module
Database connection and session management for all services
"""

import logging
from contextlib import asynccontextmanager
from typing import AsyncGenerator

from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.orm import declarative_base

logger = logging.getLogger(__name__)

Base = declarative_base()


class DatabaseManager:
    """Manages database connections and sessions"""

    def __init__(self, database_url: str, echo: bool = False):
        self.database_url = database_url
        self.engine = create_async_engine(
            database_url,
            echo=echo,
            pool_size=10,
            max_overflow=20,
            pool_pre_ping=True,
            pool_recycle=3600,
        )
        self.async_session = async_sessionmaker(
            self.engine, class_=AsyncSession, expire_on_commit=False
        )

    async def create_tables(self):
        """Create all tables"""
        async with self.engine.begin() as conn:
            await conn.run_sync(Base.metadata.create_all)

    async def drop_tables(self):
        """Drop all tables"""
        async with self.engine.begin() as conn:
            await conn.run_sync(Base.metadata.drop_all)

    @asynccontextmanager
    async def session(self) -> AsyncGenerator[AsyncSession, None]:
        """Get a database session"""
        async with self.async_session() as session:
            try:
                yield session
                await session.commit()
            except Exception:
                await session.rollback()
                raise
            finally:
                await session.close()

    async def get_session(self) -> AsyncGenerator[AsyncSession, None]:
        """FastAPI dependency for database session"""
        async with self.session() as session:
            yield session

    async def close(self):
        """Close database connections"""
        await self.engine.dispose()

    async def health_check(self) -> bool:
        """Check database connectivity"""
        try:
            async with self.engine.connect() as conn:
                await conn.execute("SELECT 1")
            return True
        except Exception as e:
            logger.error(f"Database health check failed: {e}")
            return False
