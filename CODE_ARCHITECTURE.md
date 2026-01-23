# 🏗️ Phase 2: Code Architecture & Refactoring Guide
## Movie Booking Distributed System

> **Scope**: Internal service architecture, logic risk analysis, and concrete refactoring recommendations.  
> **Audience**: Senior Engineers, Tech Leads, Code Reviewers.

---

## Table of Contents

1. [Current Code Analysis](#1-current-code-analysis)
2. [Clean Internal Architecture Pattern](#2-clean-internal-architecture-pattern)
3. [Dependency Boundary Design](#3-dependency-boundary-design)
4. [Configuration Management](#4-configuration-management)
5. [Error Handling Patterns](#5-error-handling-patterns)
6. [Logging & Tracing Integration](#6-logging--tracing-integration)
7. [Retry & Timeout Strategies](#7-retry--timeout-strategies)
8. [Logic Risk Analysis](#8-logic-risk-analysis)
9. [Refactoring Recommendations](#9-refactoring-recommendations)
10. [Validation Checklist](#10-validation-checklist)

---

## 1. Current Code Analysis

### 1.1 Service Structure Overview

```
Current Structure (Flat/Monolithic per Service)
================================================

services/
├── auth-service/app/
│   └── main.py          # 373 lines - ALL logic in one file
├── booking-service/app/
│   └── main.py          # 575 lines - ALL logic in one file
├── payment-service/app/
│   └── main.py          # 450 lines - ALL logic in one file
├── movie-service/app/
│   └── main.py          # 482 lines - ALL logic in one file
└── notification-service/app/
    └── main.py          # 321 lines - ALL logic in one file

TOTAL: ~2,200 lines of code in 5 monolithic files
```

### 1.2 Current Issues Identified

| Issue | Location | Severity | Impact |
|-------|----------|----------|--------|
| **No layering** | All services | 🔴 HIGH | Testing impossible, logic tangled |
| **Mixed concerns** | `main.py` files | 🔴 HIGH | API + DB + Business logic mixed |
| **Inline imports** | Route handlers | 🟠 MEDIUM | Circular dependency risk |
| **Global state** | `redis_client`, `rabbitmq_*` | 🟠 MEDIUM | Testing difficulty |
| **No interfaces** | DB access | 🟡 LOW | Can't mock repositories |
| **Hardcoded config** | Defaults in code | 🟠 MEDIUM | Environment-specific issues |

### 1.3 Code Smell Examples

**Smell #1: Mixed Concerns in Route Handler** ([booking-service/app/main.py#L347-L464](services/booking-service/app/main.py#L347-L464))

```python
@app.post("/book")
async def book_tickets(
    booking_data: BookingCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    # ❌ Lock acquisition (infrastructure concern)
    # ❌ Redis operations (infrastructure concern)
    # ❌ HTTP call to movie-service (external integration)
    # ❌ Business logic (price calculation)
    # ❌ Database operations (persistence)
    # ❌ Metrics recording (observability)
    # ALL IN ONE FUNCTION - 120+ lines
```

**Smell #2: Inline Imports** (Multiple files)

```python
@app.post("/register")
async def register(...):
    from sqlalchemy import select  # ❌ Imported inside function
    # ...
```

**Smell #3: Global Mutable State** ([payment-service/app/main.py#L65-L67](services/payment-service/app/main.py#L65-L67))

```python
redis_client: Optional = None           # ❌ Global mutable
rabbitmq_connection: Optional = None    # ❌ Global mutable
rabbitmq_channel: Optional = None       # ❌ Global mutable
```

---

## 2. Clean Internal Architecture Pattern

### 2.1 Target Layered Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SERVICE ARCHITECTURE                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                         API LAYER (Thin)                              │  │
│  │  • FastAPI routes                                                     │  │
│  │  • Request/Response schemas (Pydantic)                               │  │
│  │  • Input validation                                                   │  │
│  │  • Authentication/Authorization middleware                            │  │
│  │  • HTTP error mapping                                                 │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                       DOMAIN LAYER (Thick)                            │  │
│  │  • Business logic / Use cases                                        │  │
│  │  • Domain models (pure Python, no ORM)                               │  │
│  │  • Domain events                                                      │  │
│  │  • Validation rules                                                   │  │
│  │  • Interfaces (abstract repositories)                                │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                       INFRASTRUCTURE LAYER                            │  │
│  │  • Repository implementations (SQLAlchemy)                           │  │
│  │  • External service clients (HTTP, gRPC)                             │  │
│  │  • Message queue publishers/consumers                                │  │
│  │  • Cache implementations (Redis)                                      │  │
│  │  • Lock managers                                                      │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

Dependency Flow: API → Domain ← Infrastructure
                 (Domain never imports from API or Infrastructure)
```

### 2.2 Target Directory Structure

```
services/booking-service/
├── app/
│   ├── __init__.py
│   ├── main.py                 # FastAPI app setup only
│   ├── config.py               # Configuration (Pydantic Settings)
│   ├── dependencies.py         # FastAPI dependencies
│   │
│   ├── api/                    # API Layer
│   │   ├── __init__.py
│   │   ├── routes/
│   │   │   ├── __init__.py
│   │   │   ├── booking.py      # /book, /bookings endpoints
│   │   │   └── health.py       # /health endpoints
│   │   ├── schemas/
│   │   │   ├── __init__.py
│   │   │   ├── booking.py      # BookingCreate, BookingResponse
│   │   │   └── common.py       # Shared schemas
│   │   └── middleware/
│   │       ├── __init__.py
│   │       ├── correlation.py  # X-Correlation-ID
│   │       └── error_handler.py
│   │
│   ├── domain/                 # Domain Layer
│   │   ├── __init__.py
│   │   ├── models/
│   │   │   ├── __init__.py
│   │   │   ├── booking.py      # Booking entity
│   │   │   └── seat.py         # BookingSeat entity
│   │   ├── services/
│   │   │   ├── __init__.py
│   │   │   └── booking_service.py  # BookingService (use case)
│   │   ├── events/
│   │   │   ├── __init__.py
│   │   │   └── booking_events.py   # BookingCreated, BookingCancelled
│   │   ├── interfaces/
│   │   │   ├── __init__.py
│   │   │   ├── booking_repository.py   # Abstract interface
│   │   │   ├── lock_manager.py         # Abstract lock interface
│   │   │   └── event_publisher.py      # Abstract event interface
│   │   └── exceptions.py       # Domain exceptions
│   │
│   └── infrastructure/         # Infrastructure Layer
│       ├── __init__.py
│       ├── database/
│       │   ├── __init__.py
│       │   ├── connection.py   # Engine, session maker
│       │   ├── models.py       # SQLAlchemy ORM models
│       │   └── repositories/
│       │       ├── __init__.py
│       │       └── booking_repository.py  # Concrete implementation
│       ├── cache/
│       │   ├── __init__.py
│       │   └── redis_client.py
│       ├── locks/
│       │   ├── __init__.py
│       │   └── redis_lock_manager.py
│       ├── messaging/
│       │   ├── __init__.py
│       │   └── rabbitmq_publisher.py
│       └── external/
│           ├── __init__.py
│           ├── auth_client.py
│           └── movie_client.py
│
├── tests/
│   ├── __init__.py
│   ├── unit/
│   │   ├── __init__.py
│   │   └── domain/
│   │       └── test_booking_service.py
│   └── integration/
│       ├── __init__.py
│       └── test_booking_api.py
│
├── Dockerfile
├── requirements.txt
└── pyproject.toml
```

### 2.3 Layer Implementation Examples

#### API Layer: Thin Route Handler

```python
# app/api/routes/booking.py

from fastapi import APIRouter, Depends, HTTPException, status
from app.api.schemas.booking import BookingCreate, BookingResponse
from app.domain.services.booking_service import BookingService
from app.domain.exceptions import (
    SeatAlreadyBookedException,
    ShowtimeNotFoundException,
    LockAcquisitionFailedException,
)
from app.dependencies import get_booking_service, get_current_user

router = APIRouter(prefix="/bookings", tags=["Booking"])


@router.post("/", response_model=BookingResponse, status_code=status.HTTP_201_CREATED)
async def book_tickets(
    booking_data: BookingCreate,
    current_user: dict = Depends(get_current_user),
    booking_service: BookingService = Depends(get_booking_service),
):
    """
    Book tickets with distributed lock protection.
    
    - Acquires per-seat locks to prevent double-booking
    - Validates seat availability
    - Creates booking with 15-minute expiration
    """
    try:
        booking = await booking_service.create_booking(
            user_id=current_user["user_id"],
            user_email=current_user["email"],
            showtime_id=booking_data.showtime_id,
            seat_ids=booking_data.seat_ids,
        )
        return BookingResponse.from_domain(booking)
    
    except SeatAlreadyBookedException as e:
        raise HTTPException(status_code=400, detail=str(e))
    except ShowtimeNotFoundException as e:
        raise HTTPException(status_code=404, detail=str(e))
    except LockAcquisitionFailedException:
        raise HTTPException(status_code=503, detail="Service busy, please retry")
```

#### Domain Layer: Business Logic

```python
# app/domain/services/booking_service.py

from typing import List
from uuid import UUID
from datetime import datetime, timedelta
from dataclasses import dataclass
import logging

from app.domain.models.booking import Booking, BookingStatus
from app.domain.interfaces.booking_repository import BookingRepositoryInterface
from app.domain.interfaces.lock_manager import LockManagerInterface
from app.domain.interfaces.event_publisher import EventPublisherInterface
from app.domain.events.booking_events import BookingCreatedEvent
from app.domain.exceptions import (
    SeatAlreadyBookedException,
    ShowtimeNotFoundException,
    LockAcquisitionFailedException,
)

logger = logging.getLogger(__name__)


@dataclass
class ShowtimeInfo:
    """Value object for showtime data"""
    id: UUID
    movie_title: str
    theater_name: str
    show_datetime: datetime
    price_per_seat: float
    available_seats: int


class BookingService:
    """
    Booking domain service - encapsulates all booking business logic.
    
    Dependencies are injected through constructor (Dependency Inversion).
    """
    
    BOOKING_EXPIRY_MINUTES = 15
    MAX_SEATS_PER_BOOKING = 10
    
    def __init__(
        self,
        repository: BookingRepositoryInterface,
        lock_manager: LockManagerInterface,
        event_publisher: EventPublisherInterface,
        showtime_provider,  # Interface to get showtime info (cache or service)
    ):
        self._repository = repository
        self._lock_manager = lock_manager
        self._event_publisher = event_publisher
        self._showtime_provider = showtime_provider
    
    async def create_booking(
        self,
        user_id: str,
        user_email: str,
        showtime_id: UUID,
        seat_ids: List[UUID],
    ) -> Booking:
        """
        Create a new booking with seat locking.
        
        Business Rules:
        1. Maximum 10 seats per booking
        2. All seats must be available
        3. Booking expires in 15 minutes if unpaid
        4. Seat locks are per-seat (not per-showtime)
        
        Raises:
            SeatAlreadyBookedException: If any seat is taken
            ShowtimeNotFoundException: If showtime doesn't exist
            LockAcquisitionFailedException: If locks can't be acquired
        """
        # Validate seat count
        if len(seat_ids) > self.MAX_SEATS_PER_BOOKING:
            raise ValueError(f"Cannot book more than {self.MAX_SEATS_PER_BOOKING} seats")
        
        if not seat_ids:
            raise ValueError("At least one seat must be selected")
        
        # Get showtime info (from cache or external service)
        showtime = await self._showtime_provider.get_showtime(showtime_id)
        if not showtime:
            raise ShowtimeNotFoundException(f"Showtime {showtime_id} not found")
        
        # Acquire locks for all seats
        acquired_locks = []
        try:
            for seat_id in seat_ids:
                lock_key = f"seat:{showtime_id}:{seat_id}"
                acquired = await self._lock_manager.acquire(
                    lock_key,
                    ttl_seconds=30,
                    owner=str(user_id),
                )
                if not acquired:
                    raise SeatAlreadyBookedException(f"Seat {seat_id} is being booked")
                acquired_locks.append(lock_key)
            
            # Check seat availability
            for seat_id in seat_ids:
                is_available = await self._repository.is_seat_available(
                    showtime_id=showtime_id,
                    seat_id=seat_id,
                )
                if not is_available:
                    raise SeatAlreadyBookedException(f"Seat {seat_id} is already booked")
            
            # Calculate total price
            total_price = showtime.price_per_seat * len(seat_ids)
            
            # Create booking
            booking = Booking.create(
                user_id=UUID(user_id),
                showtime_id=showtime_id,
                seat_ids=seat_ids,
                total_price=total_price,
                expires_at=datetime.utcnow() + timedelta(minutes=self.BOOKING_EXPIRY_MINUTES),
                showtime_snapshot={
                    "movie_title": showtime.movie_title,
                    "theater_name": showtime.theater_name,
                    "show_datetime": showtime.show_datetime.isoformat(),
                    "price_per_seat": showtime.price_per_seat,
                },
            )
            
            # Persist booking
            saved_booking = await self._repository.save(booking)
            
            # Publish event
            await self._event_publisher.publish(
                BookingCreatedEvent(
                    booking_id=saved_booking.id,
                    user_id=user_id,
                    user_email=user_email,
                    showtime_id=showtime_id,
                    seat_ids=[str(s) for s in seat_ids],
                    total_price=total_price,
                    expires_at=booking.expires_at,
                    showtime_snapshot=booking.showtime_snapshot,
                )
            )
            
            logger.info(
                "Booking created",
                extra={
                    "booking_id": str(saved_booking.id),
                    "user_id": user_id,
                    "seats": len(seat_ids),
                }
            )
            
            return saved_booking
            
        except Exception:
            # Release locks on any failure
            raise
        finally:
            # Always release acquired locks
            for lock_key in acquired_locks:
                await self._lock_manager.release(lock_key)
    
    async def cancel_booking(self, booking_id: UUID, user_id: str) -> Booking:
        """Cancel a booking and release seats."""
        booking = await self._repository.get_by_id(booking_id)
        
        if not booking:
            raise BookingNotFoundException(f"Booking {booking_id} not found")
        
        if str(booking.user_id) != user_id:
            raise UnauthorizedException("Not authorized to cancel this booking")
        
        if booking.status == BookingStatus.CANCELLED:
            raise InvalidBookingStateException("Booking already cancelled")
        
        if booking.payment_status == "paid":
            raise InvalidBookingStateException("Cannot cancel paid booking")
        
        # Update status
        booking.cancel()
        await self._repository.save(booking)
        
        # Publish event
        await self._event_publisher.publish(
            BookingCancelledEvent(
                booking_id=booking.id,
                user_id=user_id,
                seat_ids=booking.seat_ids,
                showtime_id=booking.showtime_id,
                reason="user_cancelled",
            )
        )
        
        return booking
```

#### Infrastructure Layer: Repository Implementation

```python
# app/infrastructure/database/repositories/booking_repository.py

from typing import List, Optional
from uuid import UUID
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, update

from app.domain.models.booking import Booking, BookingStatus
from app.domain.interfaces.booking_repository import BookingRepositoryInterface
from app.infrastructure.database.models import BookingORM, BookingSeatORM


class SQLAlchemyBookingRepository(BookingRepositoryInterface):
    """
    SQLAlchemy implementation of BookingRepository.
    
    Handles ORM ↔ Domain model mapping.
    """
    
    def __init__(self, session: AsyncSession):
        self._session = session
    
    async def save(self, booking: Booking) -> Booking:
        """Persist booking to database."""
        orm_booking = self._to_orm(booking)
        
        # Merge handles both insert and update
        self._session.add(orm_booking)
        await self._session.flush()
        
        # Add seats
        for seat_id in booking.seat_ids:
            seat_orm = BookingSeatORM(
                booking_id=orm_booking.id,
                seat_id=seat_id,
                showtime_id=booking.showtime_id,
                status="reserved",
            )
            self._session.add(seat_orm)
        
        await self._session.commit()
        await self._session.refresh(orm_booking)
        
        return self._to_domain(orm_booking)
    
    async def get_by_id(self, booking_id: UUID) -> Optional[Booking]:
        """Get booking by ID."""
        result = await self._session.execute(
            select(BookingORM).where(BookingORM.id == booking_id)
        )
        orm_booking = result.scalar_one_or_none()
        
        if not orm_booking:
            return None
        
        return self._to_domain(orm_booking)
    
    async def is_seat_available(self, showtime_id: UUID, seat_id: UUID) -> bool:
        """Check if seat is available for showtime."""
        result = await self._session.execute(
            select(BookingSeatORM)
            .where(
                BookingSeatORM.showtime_id == showtime_id,
                BookingSeatORM.seat_id == seat_id,
                BookingSeatORM.status.in_(["reserved", "confirmed"]),
            )
        )
        return result.scalar_one_or_none() is None
    
    def _to_orm(self, booking: Booking) -> BookingORM:
        """Map domain model to ORM."""
        return BookingORM(
            id=booking.id,
            user_id=booking.user_id,
            showtime_id=booking.showtime_id,
            booking_code=booking.booking_code,
            total_seats=len(booking.seat_ids),
            total_price=booking.total_price,
            status=booking.status.value,
            payment_status=booking.payment_status,
            expires_at=booking.expires_at,
            showtime_snapshot=booking.showtime_snapshot,
        )
    
    def _to_domain(self, orm: BookingORM) -> Booking:
        """Map ORM to domain model."""
        return Booking(
            id=orm.id,
            user_id=orm.user_id,
            showtime_id=orm.showtime_id,
            booking_code=orm.booking_code,
            seat_ids=[],  # Load separately if needed
            total_price=float(orm.total_price),
            status=BookingStatus(orm.status),
            payment_status=orm.payment_status,
            expires_at=orm.expires_at,
            created_at=orm.created_at,
        )
```

---

## 3. Dependency Boundary Design

### 3.1 Dependency Inversion Principle

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       DEPENDENCY INVERSION IN ACTION                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│    WRONG (Current):                    CORRECT (Target):                   │
│                                                                             │
│    BookingService                      BookingService                       │
│         │                                   │                               │
│         ├──► SQLAlchemy Session            ├──► BookingRepositoryInterface │
│         ├──► Redis Client                  ├──► LockManagerInterface       │
│         └──► RabbitMQ Channel              └──► EventPublisherInterface    │
│                                                       ▲                     │
│    (Concrete dependencies)                            │                     │
│                                            ┌──────────┴──────────┐          │
│                                            │   IMPLEMENTATIONS   │          │
│                                            │                     │          │
│                                            │ • SQLAlchemyRepo    │          │
│                                            │ • RedisLockManager  │          │
│                                            │ • RabbitMQPublisher │          │
│                                            └─────────────────────┘          │
│                                                                             │
│    Benefits:                                                                │
│    ✓ Domain layer has no external dependencies                             │
│    ✓ Easy to mock for unit tests                                           │
│    ✓ Can swap implementations (e.g., Redis → Etcd for locks)              │
│    ✓ Clear contracts between layers                                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Interface Definitions

```python
# app/domain/interfaces/booking_repository.py

from abc import ABC, abstractmethod
from typing import List, Optional
from uuid import UUID
from app.domain.models.booking import Booking


class BookingRepositoryInterface(ABC):
    """Abstract interface for booking persistence."""
    
    @abstractmethod
    async def save(self, booking: Booking) -> Booking:
        """Save or update a booking."""
        pass
    
    @abstractmethod
    async def get_by_id(self, booking_id: UUID) -> Optional[Booking]:
        """Get booking by ID."""
        pass
    
    @abstractmethod
    async def get_by_user(self, user_id: UUID) -> List[Booking]:
        """Get all bookings for a user."""
        pass
    
    @abstractmethod
    async def is_seat_available(self, showtime_id: UUID, seat_id: UUID) -> bool:
        """Check if seat is available."""
        pass


# app/domain/interfaces/lock_manager.py

class LockManagerInterface(ABC):
    """Abstract interface for distributed locking."""
    
    @abstractmethod
    async def acquire(
        self,
        key: str,
        ttl_seconds: int,
        owner: str,
        retry_count: int = 3,
        retry_delay: float = 0.1,
    ) -> bool:
        """
        Acquire a distributed lock.
        
        Args:
            key: Unique lock identifier
            ttl_seconds: Lock expiration time
            owner: Lock owner identifier (for safe release)
            retry_count: Number of acquisition attempts
            retry_delay: Delay between retries
            
        Returns:
            True if lock acquired, False otherwise
        """
        pass
    
    @abstractmethod
    async def release(self, key: str, owner: str = None) -> bool:
        """Release a lock. Owner check is optional."""
        pass
    
    @abstractmethod
    async def extend(self, key: str, ttl_seconds: int) -> bool:
        """Extend lock TTL."""
        pass


# app/domain/interfaces/event_publisher.py

class EventPublisherInterface(ABC):
    """Abstract interface for event publishing."""
    
    @abstractmethod
    async def publish(self, event: DomainEvent) -> None:
        """Publish a domain event."""
        pass
```

### 3.3 Dependency Injection Setup

```python
# app/dependencies.py

from functools import lru_cache
from fastapi import Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import Settings, get_settings
from app.infrastructure.database.connection import get_session
from app.infrastructure.database.repositories.booking_repository import SQLAlchemyBookingRepository
from app.infrastructure.locks.redis_lock_manager import RedisLockManager
from app.infrastructure.messaging.rabbitmq_publisher import RabbitMQEventPublisher
from app.infrastructure.external.movie_client import MovieServiceClient
from app.domain.services.booking_service import BookingService


@lru_cache()
def get_redis_lock_manager() -> RedisLockManager:
    """Singleton Redis lock manager."""
    settings = get_settings()
    return RedisLockManager(redis_url=settings.redis_url)


@lru_cache()
def get_event_publisher() -> RabbitMQEventPublisher:
    """Singleton event publisher."""
    settings = get_settings()
    return RabbitMQEventPublisher(rabbitmq_url=settings.rabbitmq_url)


@lru_cache()
def get_movie_client() -> MovieServiceClient:
    """Singleton movie service client."""
    settings = get_settings()
    return MovieServiceClient(base_url=settings.movie_service_url)


async def get_booking_repository(
    session: AsyncSession = Depends(get_session)
) -> SQLAlchemyBookingRepository:
    """Per-request repository with session."""
    return SQLAlchemyBookingRepository(session)


async def get_booking_service(
    repository: SQLAlchemyBookingRepository = Depends(get_booking_repository),
    lock_manager: RedisLockManager = Depends(get_redis_lock_manager),
    event_publisher: RabbitMQEventPublisher = Depends(get_event_publisher),
    movie_client: MovieServiceClient = Depends(get_movie_client),
) -> BookingService:
    """
    Assemble BookingService with all dependencies.
    
    This is the composition root where concrete implementations
    are injected into the domain service.
    """
    return BookingService(
        repository=repository,
        lock_manager=lock_manager,
        event_publisher=event_publisher,
        showtime_provider=movie_client,
    )
```

---

## 4. Configuration Management

### 4.1 Current Issues

```python
# ❌ CURRENT: Hardcoded defaults scattered across code
SECRET_KEY = os.getenv("SECRET_KEY", "your-super-secret-jwt-key-change-in-production")
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
REQUEST_TIMEOUT = float(os.getenv("REQUEST_TIMEOUT", "10.0"))
```

### 4.2 Target: Pydantic Settings

```python
# app/config.py

from functools import lru_cache
from typing import Optional
from pydantic import Field, field_validator
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """
    Application settings with validation.
    
    Values loaded from environment variables with type coercion.
    """
    
    # Service Identity
    service_name: str = "booking-service"
    environment: str = Field(default="development", pattern="^(development|staging|production)$")
    debug: bool = False
    
    # Database
    database_url: str = Field(..., description="PostgreSQL connection string")
    db_pool_size: int = Field(default=10, ge=1, le=50)
    db_max_overflow: int = Field(default=20, ge=0, le=100)
    db_pool_timeout: int = Field(default=30, ge=5, le=120)
    
    # Redis
    redis_url: str = Field(..., description="Redis connection string")
    redis_lock_ttl: int = Field(default=30, ge=5, le=300)
    redis_lock_retry_count: int = Field(default=5, ge=1, le=20)
    redis_lock_retry_delay: float = Field(default=0.1, ge=0.01, le=1.0)
    
    # RabbitMQ
    rabbitmq_url: str = Field(..., description="RabbitMQ AMQP URL")
    rabbitmq_exchange: str = "domain.events"
    rabbitmq_connection_retry: int = Field(default=5, ge=1, le=10)
    
    # External Services
    auth_service_url: str = Field(..., description="Auth service base URL")
    movie_service_url: str = Field(..., description="Movie service base URL")
    
    # HTTP Client
    http_timeout: float = Field(default=10.0, ge=1.0, le=60.0)
    http_max_retries: int = Field(default=3, ge=0, le=10)
    
    # Circuit Breaker
    circuit_breaker_failure_threshold: int = Field(default=5, ge=1, le=20)
    circuit_breaker_recovery_timeout: int = Field(default=30, ge=10, le=300)
    
    # Business Rules
    booking_expiry_minutes: int = Field(default=15, ge=5, le=60)
    max_seats_per_booking: int = Field(default=10, ge=1, le=50)
    
    # Observability
    enable_tracing: bool = True
    jaeger_endpoint: Optional[str] = None
    log_level: str = Field(default="INFO", pattern="^(DEBUG|INFO|WARNING|ERROR|CRITICAL)$")
    
    @field_validator("database_url")
    @classmethod
    def validate_database_url(cls, v: str) -> str:
        if not v.startswith(("postgresql://", "postgresql+asyncpg://")):
            raise ValueError("Database URL must be PostgreSQL")
        return v
    
    @field_validator("redis_url")
    @classmethod
    def validate_redis_url(cls, v: str) -> str:
        if not v.startswith("redis://"):
            raise ValueError("Redis URL must start with redis://")
        return v
    
    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False


@lru_cache()
def get_settings() -> Settings:
    """
    Cached settings loader.
    
    Call get_settings() anywhere to get validated configuration.
    """
    return Settings()


# Usage in other modules:
# from app.config import get_settings
# settings = get_settings()
# print(settings.database_url)
```

### 4.3 Environment File Structure

```bash
# .env.example (committed to git)

# Service Identity
SERVICE_NAME=booking-service
ENVIRONMENT=development
DEBUG=true

# Database (PostgreSQL)
DATABASE_URL=postgresql+asyncpg://admin:admin123@localhost:5432/movie_booking
DB_POOL_SIZE=10
DB_MAX_OVERFLOW=20

# Redis
REDIS_URL=redis://:redis123@localhost:6379/2
REDIS_LOCK_TTL=30

# RabbitMQ
RABBITMQ_URL=amqp://admin:admin123@localhost:5672/
RABBITMQ_EXCHANGE=domain.events

# External Services
AUTH_SERVICE_URL=http://localhost:8001
MOVIE_SERVICE_URL=http://localhost:8002

# HTTP Client
HTTP_TIMEOUT=10.0
HTTP_MAX_RETRIES=3

# Observability
ENABLE_TRACING=true
JAEGER_ENDPOINT=http://localhost:4317
LOG_LEVEL=DEBUG
```

### 4.4 Kubernetes ConfigMap/Secret Mapping

```yaml
# kubernetes/booking-service/configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: booking-service-config
  namespace: movie-booking
data:
  SERVICE_NAME: "booking-service"
  ENVIRONMENT: "production"
  DEBUG: "false"
  DB_POOL_SIZE: "20"
  HTTP_TIMEOUT: "10.0"
  LOG_LEVEL: "INFO"
  ENABLE_TRACING: "true"
  JAEGER_ENDPOINT: "http://jaeger-collector:4317"

---
# kubernetes/booking-service/secret.yaml (encrypted with sops/sealed-secrets)
apiVersion: v1
kind: Secret
metadata:
  name: booking-service-secrets
  namespace: movie-booking
type: Opaque
stringData:
  DATABASE_URL: "postgresql+asyncpg://booking_user:SECURE_PASSWORD@postgres-booking:5432/bookings_db"
  REDIS_URL: "redis://:REDIS_PASSWORD@redis-booking-sentinel:26379/0"
  RABBITMQ_URL: "amqp://booking_user:SECURE_PASSWORD@rabbitmq-cluster:5672/"
  AUTH_SERVICE_URL: "http://auth-service:8000"
  MOVIE_SERVICE_URL: "http://movie-service:8000"
```

---

## 5. Error Handling Patterns

### 5.1 Domain Exception Hierarchy

```python
# app/domain/exceptions.py

from typing import Optional


class DomainException(Exception):
    """Base class for all domain exceptions."""
    
    def __init__(self, message: str, code: str = None, details: dict = None):
        super().__init__(message)
        self.message = message
        self.code = code or self.__class__.__name__
        self.details = details or {}


# Booking Domain Exceptions
class BookingException(DomainException):
    """Base exception for booking domain."""
    pass


class SeatAlreadyBookedException(BookingException):
    """Raised when seat is already reserved."""
    
    def __init__(self, seat_id: str = None):
        super().__init__(
            message=f"Seat {seat_id} is already booked" if seat_id else "Seat is already booked",
            code="SEAT_ALREADY_BOOKED",
            details={"seat_id": seat_id} if seat_id else {},
        )


class ShowtimeNotFoundException(BookingException):
    """Raised when showtime doesn't exist."""
    
    def __init__(self, showtime_id: str = None):
        super().__init__(
            message=f"Showtime {showtime_id} not found" if showtime_id else "Showtime not found",
            code="SHOWTIME_NOT_FOUND",
            details={"showtime_id": showtime_id} if showtime_id else {},
        )


class BookingNotFoundException(BookingException):
    """Raised when booking doesn't exist."""
    pass


class InvalidBookingStateException(BookingException):
    """Raised when booking state transition is invalid."""
    pass


class BookingExpiredException(BookingException):
    """Raised when booking has expired."""
    pass


# Infrastructure Exceptions
class InfrastructureException(DomainException):
    """Base exception for infrastructure failures."""
    pass


class LockAcquisitionFailedException(InfrastructureException):
    """Raised when distributed lock can't be acquired."""
    
    def __init__(self, lock_key: str = None, retry_count: int = 0):
        super().__init__(
            message=f"Failed to acquire lock after {retry_count} attempts",
            code="LOCK_ACQUISITION_FAILED",
            details={"lock_key": lock_key, "retry_count": retry_count},
        )


class ExternalServiceException(InfrastructureException):
    """Raised when external service call fails."""
    
    def __init__(self, service: str, status_code: int = None, reason: str = None):
        super().__init__(
            message=f"External service '{service}' failed: {reason or 'Unknown error'}",
            code="EXTERNAL_SERVICE_ERROR",
            details={"service": service, "status_code": status_code},
        )


class CircuitBreakerOpenException(InfrastructureException):
    """Raised when circuit breaker is open."""
    
    def __init__(self, service: str):
        super().__init__(
            message=f"Service '{service}' is temporarily unavailable",
            code="SERVICE_UNAVAILABLE",
            details={"service": service},
        )
```

### 5.2 Global Exception Handler

```python
# app/api/middleware/error_handler.py

from fastapi import Request, status
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
import logging

from app.domain.exceptions import (
    DomainException,
    BookingException,
    SeatAlreadyBookedException,
    ShowtimeNotFoundException,
    BookingNotFoundException,
    InvalidBookingStateException,
    LockAcquisitionFailedException,
    ExternalServiceException,
    CircuitBreakerOpenException,
)

logger = logging.getLogger(__name__)


# Exception → HTTP Status Code mapping
EXCEPTION_STATUS_MAP = {
    # 400 Bad Request
    SeatAlreadyBookedException: status.HTTP_400_BAD_REQUEST,
    InvalidBookingStateException: status.HTTP_400_BAD_REQUEST,
    
    # 404 Not Found
    ShowtimeNotFoundException: status.HTTP_404_NOT_FOUND,
    BookingNotFoundException: status.HTTP_404_NOT_FOUND,
    
    # 503 Service Unavailable
    LockAcquisitionFailedException: status.HTTP_503_SERVICE_UNAVAILABLE,
    CircuitBreakerOpenException: status.HTTP_503_SERVICE_UNAVAILABLE,
    ExternalServiceException: status.HTTP_503_SERVICE_UNAVAILABLE,
}


def setup_exception_handlers(app):
    """Register exception handlers with FastAPI app."""
    
    @app.exception_handler(DomainException)
    async def domain_exception_handler(request: Request, exc: DomainException):
        """Handle all domain exceptions uniformly."""
        status_code = EXCEPTION_STATUS_MAP.get(type(exc), status.HTTP_500_INTERNAL_SERVER_ERROR)
        
        logger.warning(
            f"Domain exception: {exc.code}",
            extra={
                "exception_type": type(exc).__name__,
                "exception_code": exc.code,
                "exception_message": exc.message,
                "path": request.url.path,
                "method": request.method,
            }
        )
        
        return JSONResponse(
            status_code=status_code,
            content={
                "error": {
                    "code": exc.code,
                    "message": exc.message,
                    "details": exc.details,
                }
            }
        )
    
    @app.exception_handler(RequestValidationError)
    async def validation_exception_handler(request: Request, exc: RequestValidationError):
        """Handle Pydantic validation errors."""
        errors = []
        for error in exc.errors():
            errors.append({
                "field": ".".join(str(loc) for loc in error["loc"]),
                "message": error["msg"],
                "type": error["type"],
            })
        
        return JSONResponse(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            content={
                "error": {
                    "code": "VALIDATION_ERROR",
                    "message": "Request validation failed",
                    "details": {"errors": errors},
                }
            }
        )
    
    @app.exception_handler(Exception)
    async def unhandled_exception_handler(request: Request, exc: Exception):
        """Catch-all for unhandled exceptions."""
        logger.error(
            f"Unhandled exception: {exc}",
            exc_info=True,
            extra={
                "path": request.url.path,
                "method": request.method,
            }
        )
        
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={
                "error": {
                    "code": "INTERNAL_ERROR",
                    "message": "An unexpected error occurred",
                    "details": {},  # Don't expose internal details in production
                }
            }
        )
```

### 5.3 Error Response Schema

```python
# app/api/schemas/errors.py

from typing import Dict, Any, List, Optional
from pydantic import BaseModel


class ErrorDetail(BaseModel):
    """Detailed error information."""
    field: Optional[str] = None
    message: str
    type: Optional[str] = None


class ErrorResponse(BaseModel):
    """Standard error response format."""
    
    class Error(BaseModel):
        code: str
        message: str
        details: Dict[str, Any] = {}
        errors: Optional[List[ErrorDetail]] = None
    
    error: Error
    
    class Config:
        json_schema_extra = {
            "example": {
                "error": {
                    "code": "SEAT_ALREADY_BOOKED",
                    "message": "Seat A1 is already booked",
                    "details": {"seat_id": "550e8400-e29b-41d4-a716-446655440000"},
                }
            }
        }
```

---

## 6. Logging & Tracing Integration

### 6.1 Structured Logging Configuration

```python
# app/logging_config.py

import logging
import json
import sys
from datetime import datetime
from typing import Any
from contextvars import ContextVar

from app.config import get_settings

# Context variables for request-scoped data
correlation_id_ctx: ContextVar[str] = ContextVar("correlation_id", default="")
user_id_ctx: ContextVar[str] = ContextVar("user_id", default="")


class JSONLogFormatter(logging.Formatter):
    """
    JSON formatter for structured logging.
    
    Output format compatible with:
    - ELK Stack
    - Loki/Grafana
    - CloudWatch Logs Insights
    """
    
    def format(self, record: logging.LogRecord) -> str:
        log_data = {
            "@timestamp": datetime.utcnow().isoformat() + "Z",
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "service": get_settings().service_name,
            "environment": get_settings().environment,
            
            # Request context (from context vars)
            "correlation_id": correlation_id_ctx.get(""),
            "user_id": user_id_ctx.get(""),
            
            # Code location
            "module": record.module,
            "function": record.funcName,
            "line": record.lineno,
        }
        
        # Add extra fields from record
        if hasattr(record, "extra") and record.extra:
            log_data.update(record.extra)
        
        # Add exception info if present
        if record.exc_info:
            log_data["exception"] = {
                "type": record.exc_info[0].__name__ if record.exc_info[0] else None,
                "message": str(record.exc_info[1]) if record.exc_info[1] else None,
                "traceback": self.formatException(record.exc_info),
            }
        
        return json.dumps(log_data, default=str)


class ContextFilter(logging.Filter):
    """Add context variables to all log records."""
    
    def filter(self, record: logging.LogRecord) -> bool:
        record.correlation_id = correlation_id_ctx.get("")
        record.user_id = user_id_ctx.get("")
        return True


def setup_logging() -> None:
    """Configure logging for the application."""
    settings = get_settings()
    
    # Root logger configuration
    root_logger = logging.getLogger()
    root_logger.setLevel(getattr(logging, settings.log_level))
    
    # Remove existing handlers
    root_logger.handlers.clear()
    
    # Console handler with JSON format
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(JSONLogFormatter())
    console_handler.addFilter(ContextFilter())
    root_logger.addHandler(console_handler)
    
    # Reduce noise from third-party libraries
    logging.getLogger("uvicorn.access").setLevel(logging.WARNING)
    logging.getLogger("sqlalchemy.engine").setLevel(logging.WARNING)
    logging.getLogger("httpx").setLevel(logging.WARNING)


# Convenience logger with extra support
class ServiceLogger:
    """Logger wrapper with extra field support."""
    
    def __init__(self, name: str):
        self._logger = logging.getLogger(name)
    
    def _log(self, level: int, message: str, extra: dict = None, exc_info: bool = False):
        self._logger.log(level, message, extra={"extra": extra or {}}, exc_info=exc_info)
    
    def debug(self, message: str, **extra):
        self._log(logging.DEBUG, message, extra)
    
    def info(self, message: str, **extra):
        self._log(logging.INFO, message, extra)
    
    def warning(self, message: str, **extra):
        self._log(logging.WARNING, message, extra)
    
    def error(self, message: str, exc_info: bool = False, **extra):
        self._log(logging.ERROR, message, extra, exc_info=exc_info)


def get_logger(name: str) -> ServiceLogger:
    """Get a service logger instance."""
    return ServiceLogger(name)
```

### 6.2 OpenTelemetry Tracing Setup

```python
# app/tracing.py

from opentelemetry import trace
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor
from opentelemetry.exporter.otlp.proto.grpc.trace_exporter import OTLPSpanExporter
from opentelemetry.sdk.resources import Resource, SERVICE_NAME, SERVICE_VERSION
from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
from opentelemetry.instrumentation.httpx import HTTPXClientInstrumentor
from opentelemetry.instrumentation.sqlalchemy import SQLAlchemyInstrumentor
from opentelemetry.instrumentation.redis import RedisInstrumentor
from opentelemetry.instrumentation.aio_pika import AioPikaInstrumentor
from opentelemetry.propagate import set_global_textmap
from opentelemetry.propagators.b3 import B3MultiFormat

from app.config import get_settings


def setup_tracing(app=None, engine=None) -> None:
    """
    Configure OpenTelemetry distributed tracing.
    
    Instruments:
    - FastAPI (HTTP server)
    - httpx (HTTP client)
    - SQLAlchemy (database)
    - Redis (cache/locks)
    - aio_pika (RabbitMQ)
    """
    settings = get_settings()
    
    if not settings.enable_tracing:
        return
    
    # Resource attributes (service identity)
    resource = Resource.create({
        SERVICE_NAME: settings.service_name,
        SERVICE_VERSION: "1.0.0",
        "deployment.environment": settings.environment,
    })
    
    # Tracer provider
    provider = TracerProvider(resource=resource)
    
    # OTLP exporter (Jaeger)
    if settings.jaeger_endpoint:
        exporter = OTLPSpanExporter(endpoint=settings.jaeger_endpoint)
        processor = BatchSpanProcessor(exporter)
        provider.add_span_processor(processor)
    
    trace.set_tracer_provider(provider)
    
    # Use B3 propagation (compatible with most service meshes)
    set_global_textmap(B3MultiFormat())
    
    # Auto-instrument libraries
    if app:
        FastAPIInstrumentor.instrument_app(app)
    
    HTTPXClientInstrumentor().instrument()
    
    if engine:
        SQLAlchemyInstrumentor().instrument(engine=engine.sync_engine)
    
    RedisInstrumentor().instrument()
    AioPikaInstrumentor().instrument()


def get_tracer(name: str) -> trace.Tracer:
    """Get a tracer for custom spans."""
    return trace.get_tracer(name)


# Example usage for custom spans:
#
# tracer = get_tracer(__name__)
#
# async def process_booking():
#     with tracer.start_as_current_span("process_booking") as span:
#         span.set_attribute("booking.user_id", user_id)
#         span.set_attribute("booking.seats", len(seat_ids))
#         
#         # ... business logic ...
#         
#         span.add_event("seats_locked", {"count": len(seat_ids)})
```

### 6.3 Correlation ID Middleware

```python
# app/api/middleware/correlation.py

from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware
import uuid

from app.logging_config import correlation_id_ctx, user_id_ctx, get_logger

logger = get_logger(__name__)


class CorrelationIdMiddleware(BaseHTTPMiddleware):
    """
    Middleware to propagate correlation ID across services.
    
    - Reads X-Correlation-ID from incoming request
    - Generates new ID if not present
    - Sets context variable for logging
    - Adds header to response
    """
    
    HEADER_NAME = "X-Correlation-ID"
    
    async def dispatch(self, request: Request, call_next):
        # Get or generate correlation ID
        correlation_id = request.headers.get(self.HEADER_NAME)
        if not correlation_id:
            correlation_id = str(uuid.uuid4())
        
        # Set context for this request
        correlation_id_ctx.set(correlation_id)
        
        logger.info(
            "Request started",
            method=request.method,
            path=request.url.path,
            client_ip=request.client.host if request.client else None,
        )
        
        try:
            response = await call_next(request)
            
            # Add correlation ID to response
            response.headers[self.HEADER_NAME] = correlation_id
            
            logger.info(
                "Request completed",
                method=request.method,
                path=request.url.path,
                status_code=response.status_code,
            )
            
            return response
        
        except Exception as e:
            logger.error(
                "Request failed",
                method=request.method,
                path=request.url.path,
                error=str(e),
                exc_info=True,
            )
            raise
```

---

## 7. Retry & Timeout Strategies

### 7.1 HTTP Client with Retry

```python
# app/infrastructure/external/http_client.py

import httpx
from typing import Optional, Dict, Any
from tenacity import (
    retry,
    stop_after_attempt,
    wait_exponential,
    retry_if_exception_type,
    before_sleep_log,
)
import logging

from app.config import get_settings
from app.logging_config import correlation_id_ctx, get_logger
from app.domain.exceptions import ExternalServiceException

logger = get_logger(__name__)
settings = get_settings()


class RetryableHTTPClient:
    """
    HTTP client with configurable retry, timeout, and circuit breaker.
    
    Features:
    - Exponential backoff retry
    - Connection/read timeouts
    - Correlation ID propagation
    - Structured logging
    """
    
    def __init__(
        self,
        base_url: str,
        timeout: float = None,
        max_retries: int = None,
    ):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout or settings.http_timeout
        self.max_retries = max_retries or settings.http_max_retries
        
        self._client = httpx.AsyncClient(
            base_url=self.base_url,
            timeout=httpx.Timeout(self.timeout),
        )
    
    def _get_headers(self) -> Dict[str, str]:
        """Build request headers with correlation ID."""
        return {
            "X-Correlation-ID": correlation_id_ctx.get(""),
            "Content-Type": "application/json",
        }
    
    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=0.5, min=0.5, max=10),
        retry=retry_if_exception_type((httpx.ConnectError, httpx.ReadTimeout)),
        before_sleep=before_sleep_log(logging.getLogger(__name__), logging.WARNING),
    )
    async def get(self, path: str, params: Dict = None) -> Dict[str, Any]:
        """GET request with retry."""
        url = f"{self.base_url}{path}"
        
        try:
            response = await self._client.get(
                path,
                params=params,
                headers=self._get_headers(),
            )
            response.raise_for_status()
            return response.json()
        
        except httpx.HTTPStatusError as e:
            logger.warning(
                "HTTP error",
                url=url,
                status_code=e.response.status_code,
                response_body=e.response.text[:500],
            )
            raise ExternalServiceException(
                service=self.base_url,
                status_code=e.response.status_code,
                reason=e.response.text[:200],
            )
        
        except httpx.TimeoutException as e:
            logger.error("HTTP timeout", url=url, timeout=self.timeout)
            raise ExternalServiceException(
                service=self.base_url,
                reason="Request timed out",
            )
    
    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=0.5, min=0.5, max=10),
        retry=retry_if_exception_type((httpx.ConnectError, httpx.ReadTimeout)),
    )
    async def post(self, path: str, json: Dict = None) -> Dict[str, Any]:
        """POST request with retry."""
        try:
            response = await self._client.post(
                path,
                json=json,
                headers=self._get_headers(),
            )
            response.raise_for_status()
            return response.json()
        
        except httpx.HTTPStatusError as e:
            raise ExternalServiceException(
                service=self.base_url,
                status_code=e.response.status_code,
                reason=e.response.text[:200],
            )
    
    async def close(self):
        """Close the client."""
        await self._client.aclose()
```

### 7.2 Circuit Breaker Implementation

```python
# app/infrastructure/external/circuit_breaker.py

import time
from enum import Enum
from dataclasses import dataclass
from typing import Callable, TypeVar, Optional
import asyncio
from functools import wraps

from app.config import get_settings
from app.logging_config import get_logger
from app.domain.exceptions import CircuitBreakerOpenException

logger = get_logger(__name__)
settings = get_settings()

T = TypeVar("T")


class CircuitState(Enum):
    CLOSED = "closed"       # Normal operation
    OPEN = "open"           # Failing fast
    HALF_OPEN = "half_open" # Testing recovery


@dataclass
class CircuitBreakerConfig:
    """Circuit breaker configuration."""
    failure_threshold: int = 5      # Failures before opening
    recovery_timeout: int = 30      # Seconds before half-open
    success_threshold: int = 2      # Successes to close from half-open


class CircuitBreaker:
    """
    Circuit breaker for external service calls.
    
    States:
    - CLOSED: Normal operation, tracking failures
    - OPEN: Failing fast, not calling service
    - HALF_OPEN: Testing if service recovered
    
    Usage:
        breaker = CircuitBreaker("auth-service")
        
        async def call_auth():
            async with breaker.execute():
                return await http_client.get("/verify")
    """
    
    def __init__(self, name: str, config: CircuitBreakerConfig = None):
        self.name = name
        self.config = config or CircuitBreakerConfig(
            failure_threshold=settings.circuit_breaker_failure_threshold,
            recovery_timeout=settings.circuit_breaker_recovery_timeout,
        )
        
        self._state = CircuitState.CLOSED
        self._failure_count = 0
        self._success_count = 0
        self._last_failure_time: Optional[float] = None
        self._lock = asyncio.Lock()
    
    @property
    def state(self) -> CircuitState:
        return self._state
    
    @property
    def is_open(self) -> bool:
        return self._state == CircuitState.OPEN
    
    async def _check_state(self) -> None:
        """Check and possibly transition state."""
        if self._state == CircuitState.OPEN:
            if self._last_failure_time:
                elapsed = time.time() - self._last_failure_time
                if elapsed >= self.config.recovery_timeout:
                    async with self._lock:
                        self._state = CircuitState.HALF_OPEN
                        self._success_count = 0
                        logger.info(
                            f"Circuit breaker {self.name} transitioned to HALF_OPEN",
                            circuit=self.name,
                            elapsed_seconds=elapsed,
                        )
    
    async def record_success(self) -> None:
        """Record a successful call."""
        async with self._lock:
            if self._state == CircuitState.HALF_OPEN:
                self._success_count += 1
                if self._success_count >= self.config.success_threshold:
                    self._state = CircuitState.CLOSED
                    self._failure_count = 0
                    logger.info(
                        f"Circuit breaker {self.name} CLOSED",
                        circuit=self.name,
                    )
            elif self._state == CircuitState.CLOSED:
                self._failure_count = 0  # Reset on success
    
    async def record_failure(self) -> None:
        """Record a failed call."""
        async with self._lock:
            self._failure_count += 1
            self._last_failure_time = time.time()
            
            if self._state == CircuitState.HALF_OPEN:
                self._state = CircuitState.OPEN
                logger.warning(
                    f"Circuit breaker {self.name} OPENED (half-open test failed)",
                    circuit=self.name,
                )
            elif self._state == CircuitState.CLOSED:
                if self._failure_count >= self.config.failure_threshold:
                    self._state = CircuitState.OPEN
                    logger.warning(
                        f"Circuit breaker {self.name} OPENED",
                        circuit=self.name,
                        failure_count=self._failure_count,
                    )
    
    async def execute(self) -> "CircuitBreakerContext":
        """Context manager for circuit breaker."""
        await self._check_state()
        
        if self._state == CircuitState.OPEN:
            raise CircuitBreakerOpenException(self.name)
        
        return CircuitBreakerContext(self)


class CircuitBreakerContext:
    """Context manager for circuit breaker execution."""
    
    def __init__(self, breaker: CircuitBreaker):
        self._breaker = breaker
    
    async def __aenter__(self):
        return self
    
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        if exc_type is None:
            await self._breaker.record_success()
        else:
            await self._breaker.record_failure()
        return False  # Don't suppress the exception


def with_circuit_breaker(breaker: CircuitBreaker):
    """Decorator for circuit breaker."""
    def decorator(func: Callable[..., T]) -> Callable[..., T]:
        @wraps(func)
        async def wrapper(*args, **kwargs) -> T:
            async with await breaker.execute():
                return await func(*args, **kwargs)
        return wrapper
    return decorator
```

### 7.3 Timeout Configuration Matrix

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          TIMEOUT CONFIGURATION                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Component              Connect    Read       Write     Retry Config        │
│  ────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│  HTTP Clients                                                               │
│  ├─ Auth Service        2s         5s         -         3x, exp backoff    │
│  ├─ Movie Service       2s         10s        -         3x, exp backoff    │
│  └─ Payment Gateway     5s         30s        -         2x, fixed 1s       │
│                                                                             │
│  Database (PostgreSQL)                                                      │
│  ├─ Connection Pool     5s         -          -         -                  │
│  ├─ Query Timeout       -          30s        -         -                  │
│  └─ Transaction         -          -          60s       -                  │
│                                                                             │
│  Redis                                                                      │
│  ├─ Connection          1s         -          -         3x, 100ms          │
│  ├─ Lock Acquire        -          -          -         5x, 100ms          │
│  └─ Operations          -          2s         2s        -                  │
│                                                                             │
│  RabbitMQ                                                                   │
│  ├─ Connection          5s         -          -         5x, exp backoff    │
│  ├─ Publish             -          -          5s        3x, 500ms          │
│  └─ Consume Ack         -          -          30s       -                  │
│                                                                             │
│  Gateway (NGINX)                                                            │
│  ├─ proxy_connect       5s         -          -         -                  │
│  ├─ proxy_read          -          60s        -         -                  │
│  └─ proxy_send          -          -          60s       -                  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Logic Risk Analysis

### 8.1 Distributed Locking Risks

#### Risk #1: Lock-Delete Race Condition (CRITICAL)

```python
# ❌ CURRENT CODE (booking-service/main.py:265-270)
async def acquire_lock(lock_name: str, expire_time: int = 10) -> bool:
    lock_key = f"lock:{lock_name}"
    acquired = await redis_client.set(lock_key, "locked", nx=True, ex=expire_time)
    return acquired is not None

async def release_lock(lock_name: str):
    await redis_client.delete(f"lock:{lock_name}")  # ❌ NO OWNER CHECK!
```

**Vulnerability**:
```
Time │ Process A                  │ Process B
─────┼───────────────────────────┼─────────────────────────────
 T1  │ Acquire lock (10s TTL)    │
 T2  │ Start booking (slow)...   │
 T3  │                           │
...  │ (lock expires at T11)     │
 T12 │                           │ Acquire same lock (succeeds!)
 T13 │ Finish booking            │ Start booking...
 T14 │ Release lock ←────────────┤ ❌ DELETES B's LOCK!
 T15 │                           │ Thinks it still has lock
 T16 │ Process C acquires lock   │ Both B and C booking!
```

**Fix: Owner-Based Lock Release**

```python
# ✅ SAFE IMPLEMENTATION
import uuid

class RedisLockManager:
    """Safe distributed lock with owner verification."""
    
    # Lua script for atomic check-and-delete
    RELEASE_SCRIPT = """
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("del", KEYS[1])
    else
        return 0
    end
    """
    
    def __init__(self, redis_client):
        self._redis = redis_client
        self._release_script = None
    
    async def _get_release_script(self):
        if not self._release_script:
            self._release_script = self._redis.register_script(self.RELEASE_SCRIPT)
        return self._release_script
    
    async def acquire(
        self,
        key: str,
        ttl_seconds: int = 30,
        owner: str = None,
    ) -> tuple[bool, str]:
        """
        Acquire lock with owner token.
        
        Returns:
            (acquired: bool, owner_token: str)
        """
        owner_token = owner or str(uuid.uuid4())
        
        acquired = await self._redis.set(
            key,
            owner_token,
            nx=True,   # Only if not exists
            ex=ttl_seconds,
        )
        
        return (acquired is not None, owner_token)
    
    async def release(self, key: str, owner_token: str) -> bool:
        """
        Release lock ONLY if we own it.
        
        Uses Lua script for atomic check-and-delete.
        """
        script = await self._get_release_script()
        result = await script(keys=[key], args=[owner_token])
        return result == 1
    
    async def extend(self, key: str, owner_token: str, ttl_seconds: int) -> bool:
        """Extend lock TTL if we own it."""
        EXTEND_SCRIPT = """
        if redis.call("get", KEYS[1]) == ARGV[1] then
            return redis.call("expire", KEYS[1], ARGV[2])
        else
            return 0
        end
        """
        script = self._redis.register_script(EXTEND_SCRIPT)
        result = await script(keys=[key], args=[owner_token, ttl_seconds])
        return result == 1
```

#### Risk #2: Showtime-Level Lock Contention

```python
# ❌ CURRENT: Locks entire showtime
lock_name = f"showtime:{showtime_id}"  # 150-seat theater, 1 lock!
```

**Impact**: One booking blocks all 150 seats for 10 seconds.

**Fix: Per-Seat Locking** (See Section 9.2)

#### Risk #3: Single Redis Instance SPOF

**Current**: Single Redis, if down → locks unavailable → booking fails.

**Fix**: Redis Sentinel or Redlock algorithm for critical locks.

---

### 8.2 Payment Idempotency Risks

#### Risk #1: Dual Storage Inconsistency

```python
# ❌ CURRENT: Check Redis, then DB separately
async def check_idempotency(idempotency_key: str, db: AsyncSession):
    # Step 1: Check Redis
    cached = await redis_client.get(f"idempotency:{idempotency_key}")
    if cached:
        return None  # ⚠️ Returns None but doesn't return the payment!
    
    # Step 2: Check DB (separate step)
    result = await db.execute(
        select(Payment).filter(Payment.idempotency_key == idempotency_key)
    )
    return result.scalar_one_or_none()
```

**Vulnerabilities**:
1. Redis miss + DB hit = extra query every time
2. Race: Two requests can pass Redis check before either writes
3. Return value inconsistent (None vs Payment object)

**Fix: Unified Idempotency Check**

```python
# ✅ SAFE IMPLEMENTATION
async def check_and_lock_idempotency(
    idempotency_key: str,
    db: AsyncSession,
    lock_ttl: int = 60,
) -> tuple[bool, Optional[Payment]]:
    """
    Atomic idempotency check with distributed lock.
    
    Returns:
        (is_new: bool, existing_payment: Optional[Payment])
        
    If is_new=True, caller should proceed with payment.
    If is_new=False, return existing_payment.
    """
    lock_key = f"idempotency_lock:{idempotency_key}"
    
    # Try to acquire processing lock
    acquired = await redis_client.set(lock_key, "processing", nx=True, ex=lock_ttl)
    
    if not acquired:
        # Another request is processing, wait and check DB
        await asyncio.sleep(0.5)
        existing = await db.execute(
            select(Payment).filter(Payment.idempotency_key == idempotency_key)
        )
        payment = existing.scalar_one_or_none()
        if payment:
            return (False, payment)
        # If still not in DB, retry could be needed
        raise ConflictException("Payment in progress, please retry")
    
    # We have the lock, check DB
    existing = await db.execute(
        select(Payment).filter(Payment.idempotency_key == idempotency_key)
    )
    payment = existing.scalar_one_or_none()
    
    if payment:
        # Already exists, release lock
        await redis_client.delete(lock_key)
        return (False, payment)
    
    # New payment, keep lock until we commit
    return (True, None)
```

#### Risk #2: Payment-Booking Update Non-Atomicity

```python
# ❌ CURRENT CODE (payment-service/main.py:308-320)
# Step 1: Create payment
new_payment = Payment(...)
db.add(new_payment)

# Step 2: Update booking (SEPARATE TABLE!)
await db.execute(
    update(bookings_table)  # ⚠️ Cross-service table access!
    .where(bookings_table.c.id == payment_data.booking_id)
    .values(payment_status='paid', status='confirmed')
)

await db.commit()  # Both in same commit, but still problematic
```

**Problems**:
1. Payment writes to `bookings` table (cross-service boundary violation)
2. If commit fails after payment created, state inconsistent
3. No saga pattern for distributed transaction

**Fix**: Event-driven saga (detailed in Section 9.4)

---

### 8.3 Event Duplication Handling

#### Risk #1: No Consumer Idempotency

```python
# ❌ CURRENT (notification-service/main.py:186-215)
async def process_notification(message: aio_pika.IncomingMessage):
    async with message.process():
        data = json.loads(message.body)
        # ... process notification
        # ⚠️ No check if this message was already processed!
```

**Problem**: If consumer crashes after processing but before ack, message redelivered → duplicate notification.

**Fix: Idempotent Consumer**

```python
# ✅ SAFE IMPLEMENTATION
async def process_notification(message: aio_pika.IncomingMessage):
    async with message.process():
        data = json.loads(message.body)
        event_id = data.get("event_id")
        
        if not event_id:
            logger.error("Message missing event_id, rejecting")
            return  # Ack and discard
        
        # Check if already processed
        async with get_db() as db:
            processed = await db.execute(
                select(ProcessedEvent).filter(ProcessedEvent.event_id == event_id)
            )
            if processed.scalar_one_or_none():
                logger.info(f"Event {event_id} already processed, skipping")
                return  # Ack and skip
            
            # Process notification
            notification = await send_notification(data, db)
            
            # Mark as processed (in same transaction)
            db.add(ProcessedEvent(
                event_id=event_id,
                processed_at=datetime.utcnow(),
                result="success",
            ))
            await db.commit()
```

---

### 8.4 Data Consistency & Saga Risks

#### Risk #1: No Compensation Logic

**Current Flow**:
```
Payment → Create payment → Update booking → Send notification
                              ↑
                        If this fails,
                        payment exists but
                        booking not updated!
```

**Fix**: Saga with compensation (detailed in Section 9.4)

#### Risk #2: Event Order Not Guaranteed

RabbitMQ doesn't guarantee message ordering. If `booking.confirmed` arrives before `booking.created` at Payment service:

```
Expected: booking.created → booking.confirmed
Actual:   booking.confirmed → booking.created (possible!)
```

**Fix**: 
1. Consumer stores events with version/sequence
2. Process out-of-order events after dependencies arrive
3. Or: Use Kafka with partition-based ordering

---

### 8.5 Cache Invalidation Risks

#### Risk #1: Stale Seat Availability

```python
# Movie service caches showtime data
# Booking service caches seat availability
# ⚠️ No invalidation mechanism when booking happens!
```

**Scenario**:
1. Movie service caches showtime with 50 available seats
2. Booking service books 10 seats
3. Next request to Movie service still shows 50 seats

**Fix**: Event-driven cache invalidation

```python
# Booking publishes event
await publish_event("seats.reserved", {
    "showtime_id": showtime_id,
    "seat_ids": seat_ids,
})

# Movie service subscribes and invalidates cache
async def handle_seats_reserved(event):
    showtime_id = event["payload"]["showtime_id"]
    await redis_client.delete(f"showtime:{showtime_id}")
```

---

### 8.6 Race Condition Analysis

#### Race #1: Double Booking (Current Lock Is Insufficient)

```
Time │ Request A              │ Request B
─────┼───────────────────────┼─────────────────────────
 T1  │ Acquire showtime lock │
 T2  │ Check seat A1 in Redis│
 T3  │ Seat A1 available ✓   │
 T4  │                        │ (waiting for lock)
 T5  │ Mark A1 booked        │
 T6  │ Release lock          │
 T7  │                        │ Acquire lock
 T8  │                        │ Check seat A1 ← sees BOOKED ✓
```

**This works**, but what if both request same seat without lock?

```python
# If lock acquisition fails, current code retries 5x then fails
# But what if Redis is slow and both acquire "different" locks?
```

**Fix**: Use atomic Redis operations + DB constraint as last defense.

#### Race #2: Expired Booking Cleanup vs Payment

```
Time │ Cleanup Task           │ Payment Request
─────┼───────────────────────┼─────────────────────────
 T1  │ Find expired booking  │
 T2  │ Start cleanup...      │ User submits payment
 T3  │                        │ Check booking.status = pending ✓
 T4  │ Update status=expired │
 T5  │                        │ Create payment (booking is expired!)
```

**Fix**: Optimistic locking with version check

```python
# Payment: Check status AND update atomically
result = await db.execute(
    update(bookings_table)
    .where(
        bookings_table.c.id == booking_id,
        bookings_table.c.status == 'pending',  # ← Must still be pending
        bookings_table.c.expires_at > datetime.utcnow(),  # ← Not expired
    )
    .values(status='processing')
    .returning(bookings_table.c.id)
)
if not result.scalar_one_or_none():
    raise BookingExpiredException("Booking expired or already processed")
```

---

### 8.7 Security Vulnerability Analysis

| Vulnerability | Location | Risk | Fix |
|--------------|----------|------|-----|
| **JWT shared secret** | Auth HS256 | 🔴 HIGH | Switch to RS256 |
| **Weak default secret** | `"your-super-secret..."` | 🔴 HIGH | Generate strong key |
| **No token revocation** | All services | 🟠 MEDIUM | Add blacklist or short TTL |
| **SQL injection** | Unlikely (ORM) | 🟢 LOW | Keep using parameterized queries |
| **Mass assignment** | Pydantic schemas | 🟢 LOW | Explicit field definitions |
| **CORS allow all** | `allow_origins=["*"]` | 🟠 MEDIUM | Restrict to known origins |
| **No rate limiting** | API endpoints | 🟠 MEDIUM | Add at gateway |
| **Credentials in code** | Default passwords | 🔴 HIGH | Use secrets management |

---

## 9. Refactoring Recommendations

### 9.1 Priority Matrix

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         REFACTORING PRIORITY MATRIX                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  PRIORITY │ TASK                        │ EFFORT │ IMPACT │ RISK MITIGATED │
│  ─────────┼─────────────────────────────┼────────┼────────┼────────────────│
│           │                             │        │        │                │
│  🔴 P0    │ Fix lock release (owner)    │ LOW    │ HIGH   │ Double booking │
│  🔴 P0    │ Payment idempotency fix     │ MEDIUM │ HIGH   │ Duplicate pay  │
│  🔴 P0    │ Move to RS256 JWT           │ MEDIUM │ HIGH   │ Security       │
│           │                             │        │        │                │
│  🟠 P1    │ Per-seat locking            │ MEDIUM │ HIGH   │ Contention     │
│  🟠 P1    │ Idempotent consumers        │ MEDIUM │ HIGH   │ Duplicates     │
│  🟠 P1    │ Circuit breaker fix         │ LOW    │ MEDIUM │ Cascading fail │
│           │                             │        │        │                │
│  🟡 P2    │ Layered architecture        │ HIGH   │ HIGH   │ Maintainability│
│  🟡 P2    │ Event-driven saga           │ HIGH   │ HIGH   │ Consistency    │
│  🟡 P2    │ Configuration management    │ MEDIUM │ MEDIUM │ Operations     │
│           │                             │        │        │                │
│  🟢 P3    │ Structured logging          │ LOW    │ MEDIUM │ Observability  │
│  🟢 P3    │ OpenTelemetry tracing       │ MEDIUM │ MEDIUM │ Debugging      │
│  🟢 P3    │ Unit test coverage          │ HIGH   │ HIGH   │ Regressions    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 9.2 Concrete Fix: Per-Seat Locking

```python
# booking-service/app/domain/services/booking_service.py

async def create_booking_with_seat_locks(
    self,
    user_id: str,
    showtime_id: UUID,
    seat_ids: List[UUID],
) -> Booking:
    """
    Book tickets with per-seat locking (not per-showtime).
    
    This allows concurrent bookings for different seats
    in the same showtime.
    """
    # Sort seat IDs to prevent deadlock (always lock in same order)
    sorted_seat_ids = sorted(seat_ids, key=str)
    
    acquired_locks: List[tuple[str, str]] = []  # (key, owner_token)
    
    try:
        # Acquire lock for each seat
        for seat_id in sorted_seat_ids:
            lock_key = f"lock:seat:{showtime_id}:{seat_id}"
            acquired, owner_token = await self._lock_manager.acquire(
                key=lock_key,
                ttl_seconds=30,
                owner=user_id,
                retry_count=3,
                retry_delay=0.1,
            )
            
            if not acquired:
                raise SeatAlreadyBookedException(str(seat_id))
            
            acquired_locks.append((lock_key, owner_token))
        
        # All seats locked, proceed with booking...
        # (verification, creation, event publishing)
        
        booking = await self._create_booking_internal(
            user_id=user_id,
            showtime_id=showtime_id,
            seat_ids=seat_ids,
        )
        
        return booking
        
    finally:
        # Release all acquired locks (with owner verification)
        for lock_key, owner_token in acquired_locks:
            await self._lock_manager.release(lock_key, owner_token)
```

### 9.3 Concrete Fix: Safe Idempotency

```python
# payment-service/app/domain/services/payment_service.py

async def process_payment(
    self,
    booking_id: UUID,
    amount: Decimal,
    payment_method: str,
    idempotency_key: str,
    user_id: str,
) -> Payment:
    """
    Process payment with guaranteed idempotency.
    
    Uses distributed lock + DB unique constraint as defense layers.
    """
    # Layer 1: Distributed lock on idempotency key
    lock_key = f"payment_lock:{idempotency_key}"
    acquired, owner = await self._lock_manager.acquire(lock_key, ttl_seconds=60)
    
    if not acquired:
        # Another request processing, return existing payment
        existing = await self._repository.get_by_idempotency_key(idempotency_key)
        if existing:
            return existing
        raise PaymentInProgressException("Payment being processed, retry in a moment")
    
    try:
        # Layer 2: Check DB for existing payment
        existing = await self._repository.get_by_idempotency_key(idempotency_key)
        if existing:
            return existing
        
        # Create payment
        payment = Payment.create(
            booking_id=booking_id,
            amount=amount,
            payment_method=payment_method,
            idempotency_key=idempotency_key,
        )
        
        # Process with payment gateway
        transaction_result = await self._payment_gateway.charge(
            amount=amount,
            method=payment_method,
            idempotency_key=idempotency_key,
        )
        
        payment.complete(transaction_id=transaction_result.transaction_id)
        
        # Layer 3: DB unique constraint on idempotency_key
        # (Catches any remaining race conditions)
        try:
            saved = await self._repository.save(payment)
        except UniqueConstraintViolation:
            # Another request won the race, return theirs
            return await self._repository.get_by_idempotency_key(idempotency_key)
        
        # Publish event for saga
        await self._event_publisher.publish(
            PaymentCompletedEvent(
                payment_id=saved.id,
                booking_id=booking_id,
                amount=amount,
                transaction_id=transaction_result.transaction_id,
            )
        )
        
        return saved
        
    finally:
        await self._lock_manager.release(lock_key, owner)
```

### 9.4 Concrete Fix: Event-Driven Saga

```python
# booking-service/app/domain/services/saga/booking_saga.py

from enum import Enum
from dataclasses import dataclass
from typing import Optional
from datetime import datetime


class SagaState(Enum):
    STARTED = "started"
    SEATS_RESERVED = "seats_reserved"
    PAYMENT_PENDING = "payment_pending"
    PAYMENT_COMPLETED = "payment_completed"
    CONFIRMED = "confirmed"
    COMPENSATION_STARTED = "compensation_started"
    COMPENSATED = "compensated"
    FAILED = "failed"


@dataclass
class BookingSaga:
    """
    Saga state machine for booking flow.
    
    Flow:
    1. STARTED → Create booking, reserve seats
    2. SEATS_RESERVED → Publish booking.created
    3. PAYMENT_PENDING → Wait for payment.completed/failed
    4. PAYMENT_COMPLETED → Confirm booking, publish booking.confirmed
    5. CONFIRMED → Done (happy path)
    
    Compensation:
    - On payment.failed → Release seats, cancel booking
    - On timeout → Release seats, cancel booking
    """
    
    booking_id: str
    state: SagaState
    created_at: datetime
    updated_at: datetime
    error: Optional[str] = None
    compensation_reason: Optional[str] = None


class BookingSagaOrchestrator:
    """Handles saga state transitions and compensation."""
    
    def __init__(
        self,
        repository: BookingRepositoryInterface,
        saga_repository: SagaRepositoryInterface,
        event_publisher: EventPublisherInterface,
        seat_manager: SeatManagerInterface,
    ):
        self._repository = repository
        self._saga_repository = saga_repository
        self._event_publisher = event_publisher
        self._seat_manager = seat_manager
    
    async def start_booking(
        self,
        user_id: str,
        showtime_id: UUID,
        seat_ids: List[UUID],
    ) -> Booking:
        """Start booking saga."""
        # Create saga state
        saga = BookingSaga(
            booking_id=str(uuid.uuid4()),
            state=SagaState.STARTED,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )
        await self._saga_repository.save(saga)
        
        try:
            # Reserve seats
            await self._seat_manager.reserve(showtime_id, seat_ids, saga.booking_id)
            saga.state = SagaState.SEATS_RESERVED
            await self._saga_repository.save(saga)
            
            # Create booking
            booking = await self._repository.save(
                Booking.create(
                    id=UUID(saga.booking_id),
                    user_id=UUID(user_id),
                    showtime_id=showtime_id,
                    seat_ids=seat_ids,
                    status=BookingStatus.PENDING,
                )
            )
            
            # Publish event
            await self._event_publisher.publish(
                BookingCreatedEvent(
                    booking_id=saga.booking_id,
                    user_id=user_id,
                    showtime_id=str(showtime_id),
                    seat_ids=[str(s) for s in seat_ids],
                    total_price=booking.total_price,
                )
            )
            
            saga.state = SagaState.PAYMENT_PENDING
            await self._saga_repository.save(saga)
            
            return booking
            
        except Exception as e:
            await self._compensate(saga, str(e))
            raise
    
    async def handle_payment_completed(self, event: PaymentCompletedEvent):
        """Handle successful payment."""
        saga = await self._saga_repository.get(event.booking_id)
        
        if saga.state != SagaState.PAYMENT_PENDING:
            logger.warning(f"Unexpected saga state: {saga.state}")
            return
        
        saga.state = SagaState.PAYMENT_COMPLETED
        await self._saga_repository.save(saga)
        
        # Confirm booking
        booking = await self._repository.get_by_id(UUID(event.booking_id))
        booking.confirm(payment_id=event.payment_id)
        await self._repository.save(booking)
        
        # Publish confirmation
        await self._event_publisher.publish(
            BookingConfirmedEvent(
                booking_id=event.booking_id,
                user_id=str(booking.user_id),
            )
        )
        
        saga.state = SagaState.CONFIRMED
        await self._saga_repository.save(saga)
    
    async def handle_payment_failed(self, event: PaymentFailedEvent):
        """Handle payment failure - trigger compensation."""
        saga = await self._saga_repository.get(event.booking_id)
        await self._compensate(saga, f"Payment failed: {event.reason}")
    
    async def _compensate(self, saga: BookingSaga, reason: str):
        """Execute compensation logic."""
        saga.state = SagaState.COMPENSATION_STARTED
        saga.compensation_reason = reason
        await self._saga_repository.save(saga)
        
        try:
            # Release seats
            booking = await self._repository.get_by_id(UUID(saga.booking_id))
            if booking:
                await self._seat_manager.release(
                    booking.showtime_id,
                    booking.seat_ids,
                )
                
                # Update booking status
                booking.cancel(reason=reason)
                await self._repository.save(booking)
            
            # Publish cancellation event
            await self._event_publisher.publish(
                BookingCancelledEvent(
                    booking_id=saga.booking_id,
                    reason=reason,
                )
            )
            
            saga.state = SagaState.COMPENSATED
            
        except Exception as e:
            saga.state = SagaState.FAILED
            saga.error = str(e)
            logger.error(f"Compensation failed: {e}", exc_info=True)
        
        await self._saga_repository.save(saga)
```

---

## 10. Validation Checklist

### 10.1 Code Review Checklist

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          CODE REVIEW CHECKLIST                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  DISTRIBUTED LOCKING                                                        │
│  [ ] Lock has owner token                                                   │
│  [ ] Release verifies owner (Lua script or CAS)                            │
│  [ ] Lock TTL is appropriate (not too short)                               │
│  [ ] Lock acquisition has retry with backoff                               │
│  [ ] Deadlock prevention (consistent lock ordering)                        │
│  [ ] Finally block releases lock                                           │
│                                                                             │
│  IDEMPOTENCY                                                                │
│  [ ] Idempotency key validated (non-empty, reasonable length)              │
│  [ ] Check before processing (distributed lock + DB)                       │
│  [ ] DB has unique constraint on idempotency key                           │
│  [ ] Response for duplicate is same as original                            │
│  [ ] TTL for idempotency cache is documented                               │
│                                                                             │
│  EVENT HANDLING                                                             │
│  [ ] Event has unique ID                                                    │
│  [ ] Consumer checks for duplicate events                                   │
│  [ ] Processing is atomic (process + mark as processed)                    │
│  [ ] DLQ configured for failed messages                                    │
│  [ ] Retry logic with exponential backoff                                  │
│                                                                             │
│  DATABASE OPERATIONS                                                        │
│  [ ] Transactions are explicit (begin/commit/rollback)                     │
│  [ ] Optimistic locking for concurrent updates                             │
│  [ ] No cross-service table access                                         │
│  [ ] Connection pooling configured                                         │
│  [ ] Query timeouts set                                                    │
│                                                                             │
│  ERROR HANDLING                                                             │
│  [ ] Domain exceptions are specific                                        │
│  [ ] HTTP status codes are appropriate                                     │
│  [ ] Error response format is consistent                                   │
│  [ ] Sensitive data not exposed in errors                                  │
│  [ ] Errors are logged with context                                        │
│                                                                             │
│  EXTERNAL CALLS                                                             │
│  [ ] Timeout configured                                                    │
│  [ ] Circuit breaker in place                                              │
│  [ ] Retry with backoff for transient failures                             │
│  [ ] Correlation ID propagated                                             │
│  [ ] Response validated before use                                         │
│                                                                             │
│  SECURITY                                                                   │
│  [ ] No hardcoded secrets                                                  │
│  [ ] JWT algorithm is RS256 (not HS256)                                    │
│  [ ] Input validation on all endpoints                                     │
│  [ ] CORS restricted to known origins                                      │
│  [ ] Rate limiting configured                                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 10.2 Testing Requirements

```python
# tests/unit/domain/test_booking_service.py

import pytest
from unittest.mock import AsyncMock, MagicMock
from uuid import uuid4

from app.domain.services.booking_service import BookingService
from app.domain.exceptions import SeatAlreadyBookedException, LockAcquisitionFailedException


@pytest.fixture
def mock_repository():
    return AsyncMock()

@pytest.fixture
def mock_lock_manager():
    return AsyncMock()

@pytest.fixture
def mock_event_publisher():
    return AsyncMock()

@pytest.fixture
def booking_service(mock_repository, mock_lock_manager, mock_event_publisher):
    return BookingService(
        repository=mock_repository,
        lock_manager=mock_lock_manager,
        event_publisher=mock_event_publisher,
        showtime_provider=AsyncMock(),
    )


class TestBookingService:
    """Unit tests for BookingService."""
    
    async def test_create_booking_success(self, booking_service, mock_lock_manager, mock_repository):
        """Happy path: booking created successfully."""
        # Arrange
        mock_lock_manager.acquire.return_value = (True, "owner-token")
        mock_repository.is_seat_available.return_value = True
        mock_repository.save.return_value = MagicMock(id=uuid4())
        
        # Act
        result = await booking_service.create_booking(
            user_id=str(uuid4()),
            user_email="test@example.com",
            showtime_id=uuid4(),
            seat_ids=[uuid4(), uuid4()],
        )
        
        # Assert
        assert result is not None
        mock_lock_manager.acquire.assert_called()
        mock_lock_manager.release.assert_called()
        mock_repository.save.assert_called_once()
    
    async def test_create_booking_seat_already_booked(self, booking_service, mock_lock_manager, mock_repository):
        """Seat is already booked, should raise exception."""
        # Arrange
        mock_lock_manager.acquire.return_value = (True, "owner-token")
        mock_repository.is_seat_available.return_value = False  # Seat taken
        
        # Act & Assert
        with pytest.raises(SeatAlreadyBookedException):
            await booking_service.create_booking(
                user_id=str(uuid4()),
                user_email="test@example.com",
                showtime_id=uuid4(),
                seat_ids=[uuid4()],
            )
        
        # Lock should still be released
        mock_lock_manager.release.assert_called()
    
    async def test_create_booking_lock_not_acquired(self, booking_service, mock_lock_manager):
        """Cannot acquire lock, should raise exception."""
        # Arrange
        mock_lock_manager.acquire.return_value = (False, None)
        
        # Act & Assert
        with pytest.raises(SeatAlreadyBookedException):
            await booking_service.create_booking(
                user_id=str(uuid4()),
                user_email="test@example.com",
                showtime_id=uuid4(),
                seat_ids=[uuid4()],
            )
    
    async def test_create_booking_releases_locks_on_failure(self, booking_service, mock_lock_manager, mock_repository):
        """Locks are released even when an exception occurs."""
        # Arrange
        mock_lock_manager.acquire.return_value = (True, "owner-token")
        mock_repository.is_seat_available.side_effect = Exception("DB error")
        
        # Act & Assert
        with pytest.raises(Exception):
            await booking_service.create_booking(
                user_id=str(uuid4()),
                user_email="test@example.com",
                showtime_id=uuid4(),
                seat_ids=[uuid4()],
            )
        
        # Lock must be released
        mock_lock_manager.release.assert_called()


class TestLockOwnerVerification:
    """Tests for lock owner verification."""
    
    async def test_release_only_owned_lock(self, mock_lock_manager):
        """Should only release lock if owner matches."""
        # This tests the Lua script behavior
        mock_lock_manager.release.return_value = True  # Success
        result = await mock_lock_manager.release("lock:key", "correct-owner")
        assert result is True
    
    async def test_release_wrong_owner_fails(self, mock_lock_manager):
        """Should fail to release if owner doesn't match."""
        mock_lock_manager.release.return_value = False  # Failure
        result = await mock_lock_manager.release("lock:key", "wrong-owner")
        assert result is False
```

### 10.3 Integration Test Scenarios

```yaml
# Test scenarios for booking flow

scenarios:
  - name: "Concurrent booking same seat"
    description: "Two users try to book same seat simultaneously"
    setup:
      - showtime with seat A1 available
    actions:
      - user_a: POST /book {seat_ids: [A1]} (async)
      - user_b: POST /book {seat_ids: [A1]} (async)
    expected:
      - exactly_one_success: true
      - one_fails_with: "SEAT_ALREADY_BOOKED"
      - seat_A1_final_status: "reserved"
  
  - name: "Payment idempotency"
    description: "Same idempotency key submitted twice"
    setup:
      - booking B1 with status=pending
    actions:
      - POST /payments {booking_id: B1, idempotency_key: KEY1}
      - POST /payments {booking_id: B1, idempotency_key: KEY1}  # Duplicate
    expected:
      - both_return_same_payment_id: true
      - payment_count_in_db: 1
  
  - name: "Booking expiry vs payment race"
    description: "Payment submitted just as booking expires"
    setup:
      - booking B1 with expires_at = now + 1 second
    actions:
      - wait 2 seconds (let cleanup run)
      - POST /payments {booking_id: B1}
    expected:
      - response_status: 400 or 404
      - error_code: "BOOKING_EXPIRED" or "BOOKING_NOT_FOUND"
  
  - name: "Circuit breaker activation"
    description: "Auth service becomes unavailable"
    setup:
      - auth_service: down
    actions:
      - 5x POST /book (all fail with auth error)
      - 6th POST /book
    expected:
      - first_5_fail_with: "Auth service error"
      - 6th_fails_with: "SERVICE_UNAVAILABLE" (circuit open)
```

---

## Summary

This Phase 2 document provides:

1. **Clean Architecture Pattern**: Three-layer (API, Domain, Infrastructure) with dependency inversion
2. **Dependency Boundaries**: Interfaces for all external dependencies, enabling testability
3. **Configuration Management**: Pydantic Settings with validation, environment-specific configs
4. **Error Handling**: Domain exception hierarchy, global handlers, consistent response format
5. **Logging & Tracing**: Structured JSON logging, OpenTelemetry integration, correlation ID propagation
6. **Retry & Timeout**: HTTP client with tenacity, circuit breaker, timeout matrix
7. **Logic Risk Analysis**: 7 categories of risks with specific code locations
8. **Refactoring Recommendations**: Prioritized fixes with implementation code
9. **Validation Checklist**: Code review checklist and test scenarios

**Next Steps**:
1. Apply P0 fixes (lock release, idempotency, RS256)
2. Refactor one service to layered architecture (pilot)
3. Add unit tests for domain layer
4. Roll out pattern to remaining services

---

*Phase 2 - Code Architecture & Refactoring Guide v1.0*  
*CSE702063 - Movie Booking Distributed System*  
*January 2026*
