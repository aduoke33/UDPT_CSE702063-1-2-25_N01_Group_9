from fastapi import FastAPI, HTTPException, Depends, status, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer
from datetime import datetime, timedelta
from typing import Optional, List
from decimal import Decimal
import redis.asyncio as redis
import os
import uuid
import random
import string
import httpx
import logging
import time
from contextvars import ContextVar
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, Integer, DateTime, DECIMAL, ForeignKey, Boolean
from sqlalchemy.dialects.postgresql import UUID
from pydantic import BaseModel
import asyncio
from prometheus_fastapi_instrumentator import Instrumentator
from prometheus_client import Counter, Histogram, Gauge
from starlette.middleware.base import BaseHTTPMiddleware

# Correlation ID context
correlation_id_ctx: ContextVar[str] = ContextVar('correlation_id', default='')

# Configure logging with correlation ID
class CorrelationIdFilter(logging.Filter):
    def filter(self, record):
        record.correlation_id = correlation_id_ctx.get('')
        return True

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - [%(correlation_id)s] - %(message)s'
)
logger = logging.getLogger(__name__)
logger.addFilter(CorrelationIdFilter())

# Custom metrics
booking_counter = Counter('bookings_total', 'Total booking attempts', ['status'])
lock_acquisition_histogram = Histogram('lock_acquisition_seconds', 'Lock acquisition duration')
lock_wait_counter = Counter('lock_wait_total', 'Total lock wait/retry attempts')
active_bookings_gauge = Gauge('active_bookings', 'Number of active pending bookings')
seat_reservation_histogram = Histogram('seat_reservation_seconds', 'Seat reservation duration')
circuit_breaker_state = Gauge('circuit_breaker_state', 'Circuit breaker state (0=closed, 1=open, 2=half-open)', ['service'])

# =====================================================
# CIRCUIT BREAKER PATTERN
# =====================================================
class CircuitBreaker:
    """Circuit Breaker to prevent cascading failures"""
    def __init__(self, failure_threshold: int = 5, recovery_timeout: int = 30):
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.failures = 0
        self.last_failure_time = None
        self.state = "CLOSED"  # CLOSED, OPEN, HALF_OPEN
    
    def record_failure(self):
        self.failures += 1
        self.last_failure_time = time.time()
        if self.failures >= self.failure_threshold:
            self.state = "OPEN"
            logger.warning(f"Circuit breaker OPENED after {self.failures} failures")
    
    def record_success(self):
        self.failures = 0
        self.state = "CLOSED"
    
    def can_execute(self) -> bool:
        if self.state == "CLOSED":
            return True
        if self.state == "OPEN":
            if time.time() - self.last_failure_time > self.recovery_timeout:
                self.state = "HALF_OPEN"
                logger.info("Circuit breaker moved to HALF_OPEN")
                return True
            return False
        return True  # HALF_OPEN allows one request

# Circuit breakers for external services
auth_circuit_breaker = CircuitBreaker(failure_threshold=5, recovery_timeout=30)
movie_circuit_breaker = CircuitBreaker(failure_threshold=5, recovery_timeout=30)

# =====================================================
# RETRY WITH EXPONENTIAL BACKOFF
# =====================================================
async def retry_with_backoff(func, max_retries: int = 3, base_delay: float = 0.5):
    """Retry a function with exponential backoff"""
    for attempt in range(max_retries):
        try:
            return await func()
        except Exception as e:
            if attempt == max_retries - 1:
                raise
            delay = base_delay * (2 ** attempt)
            logger.warning(f"Attempt {attempt + 1} failed, retrying in {delay}s: {str(e)}")
            await asyncio.sleep(delay)

# Configuration
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/2")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")
MOVIE_SERVICE_URL = os.getenv("MOVIE_SERVICE_URL", "http://movie-service:8000")
REQUEST_TIMEOUT = float(os.getenv("REQUEST_TIMEOUT", "10.0"))

# Database Setup with connection pooling
engine = create_async_engine(
    DATABASE_URL, 
    echo=True,
    pool_size=10,
    max_overflow=20,
    pool_pre_ping=True
)
async_session_maker = async_sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
Base = declarative_base()

# Models
class Booking(Base):
    __tablename__ = "bookings"
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    user_id = Column(UUID(as_uuid=True), nullable=False)
    showtime_id = Column(UUID(as_uuid=True), nullable=False)
    booking_code = Column(String(20), unique=True, nullable=False)
    total_seats = Column(Integer, nullable=False)
    total_price = Column(DECIMAL(10, 2), nullable=False)
    status = Column(String(20), default='pending')
    payment_status = Column(String(20), default='unpaid')
    booking_date = Column(DateTime, default=datetime.utcnow)
    expires_at = Column(DateTime)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)

class BookingSeat(Base):
    __tablename__ = "booking_seats"
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    booking_id = Column(UUID(as_uuid=True), ForeignKey("bookings.id"), nullable=False)
    seat_id = Column(UUID(as_uuid=True), nullable=False)
    showtime_id = Column(UUID(as_uuid=True), nullable=False)
    status = Column(String(20), default='reserved')

# Schemas
class SeatBookRequest(BaseModel):
    seat_ids: List[uuid.UUID]

class BookingCreate(BaseModel):
    showtime_id: uuid.UUID
    seat_ids: List[uuid.UUID]

class BookingResponse(BaseModel):
    id: uuid.UUID
    booking_code: str
    showtime_id: uuid.UUID
    total_seats: int
    total_price: Decimal
    status: str
    payment_status: str
    expires_at: datetime
    
    class Config:
        from_attributes = True

app = FastAPI(
    title="Booking Service",
    description="""
## Ticket Booking Service with Distributed Locking

This service handles ticket booking operations with **Redis-based distributed locking** to prevent race conditions.

### Features:
- 🎫 **Book Tickets** - Reserve seats with distributed lock protection
- 🔒 **Distributed Locking** - Redis SET NX EX pattern prevents double-booking
- ❌ **Cancel Booking** - Release seats and refund
- 📋 **View Bookings** - Get user's booking history

### Distributed Locking Flow:
1. Acquire lock on showtime: `SET lock:showtime:{id} locked NX EX 10`
2. Check seat availability in Redis
3. Reserve seats and create booking
4. Release lock

### Booking States:
- `pending` - Awaiting payment (15 min expiry)
- `confirmed` - Payment completed
- `cancelled` - Booking cancelled
    """,
    version="1.0.0",
    openapi_tags=[
        {"name": "Booking", "description": "Ticket booking operations with distributed locking"},
        {"name": "Health", "description": "Service health checks"}
    ]
)

# Correlation ID Middleware
class CorrelationIdMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        correlation_id = request.headers.get('X-Correlation-ID', str(uuid.uuid4()))
        correlation_id_ctx.set(correlation_id)
        logger.info(f"Request started: {request.method} {request.url.path}")
        response = await call_next(request)
        response.headers['X-Correlation-ID'] = correlation_id
        logger.info(f"Request completed: {response.status_code}")
        return response

app.add_middleware(CorrelationIdMiddleware)

# Prometheus instrumentation
Instrumentator().instrument(app).expose(app)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

redis_client: Optional[redis.Redis] = None
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")

async def get_db():
    async with async_session_maker() as session:
        yield session

async def get_current_user(token: str = Depends(oauth2_scheme)):
    """Verify token with auth service using Circuit Breaker"""
    if not auth_circuit_breaker.can_execute():
        circuit_breaker_state.labels(service='auth').set(1)
        raise HTTPException(status_code=503, detail="Auth service temporarily unavailable")
    
    async def call_auth_service():
        async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT) as client:
            response = await client.get(
                f"{AUTH_SERVICE_URL}/verify",
                headers={
                    "Authorization": f"Bearer {token}",
                    "X-Correlation-ID": correlation_id_ctx.get('')
                }
            )
            if response.status_code == 200:
                return response.json()
            raise HTTPException(status_code=401, detail="Invalid authentication")
    
    try:
        result = await retry_with_backoff(call_auth_service, max_retries=3)
        auth_circuit_breaker.record_success()
        circuit_breaker_state.labels(service='auth').set(0)
        return result
    except HTTPException:
        raise
    except Exception as e:
        auth_circuit_breaker.record_failure()
        circuit_breaker_state.labels(service='auth').set(1 if auth_circuit_breaker.state == "OPEN" else 2)
        logger.error(f"Auth service call failed: {str(e)}")
        raise HTTPException(status_code=503, detail=f"Auth service error: {str(e)}")

def generate_booking_code() -> str:
    """Generate unique booking code"""
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=10))

async def acquire_lock(lock_name: str, expire_time: int = 10) -> bool:
    """Acquire distributed lock using Redis"""
    start_time = time.time()
    lock_key = f"lock:{lock_name}"
    acquired = await redis_client.set(lock_key, "locked", nx=True, ex=expire_time)
    lock_acquisition_histogram.observe(time.time() - start_time)
    return acquired is not None

async def release_lock(lock_name: str):
    """Release distributed lock"""
    await redis_client.delete(f"lock:{lock_name}")
    logger.info(f"Released lock: {lock_name}")

# =====================================================
# BACKGROUND TASK: Expired Booking Cleanup
# =====================================================
async def cleanup_expired_bookings():
    """Background task to clean up expired bookings and release seats"""
    while True:
        try:
            await asyncio.sleep(60)  # Run every minute
            async with async_session_maker() as db:
                from sqlalchemy import select, update
                
                # Find expired pending bookings
                result = await db.execute(
                    select(Booking).filter(
                        Booking.status == 'pending',
                        Booking.expires_at < datetime.utcnow()
                    )
                )
                expired_bookings = result.scalars().all()
                
                for booking in expired_bookings:
                    logger.info(f"Cleaning up expired booking: {booking.booking_code}")
                    
                    # Release seats from Redis
                    seat_result = await db.execute(
                        select(BookingSeat).filter(BookingSeat.booking_id == booking.id)
                    )
                    booking_seats = seat_result.scalars().all()
                    
                    for bs in booking_seats:
                        seat_key = f"seat:{bs.showtime_id}:{bs.seat_id}"
                        await redis_client.delete(seat_key)
                        await db.execute(
                            update(BookingSeat)
                            .where(BookingSeat.id == bs.id)
                            .values(status='expired')
                        )
                    
                    # Update booking status
                    await db.execute(
                        update(Booking)
                        .where(Booking.id == booking.id)
                        .values(status='expired')
                    )
                    active_bookings_gauge.dec()
                    booking_counter.labels(status='expired').inc()
                
                await db.commit()
                
                if expired_bookings:
                    logger.info(f"Cleaned up {len(expired_bookings)} expired bookings")
        except Exception as e:
            logger.error(f"Error in cleanup task: {str(e)}")

cleanup_task: Optional[asyncio.Task] = None

@app.on_event("startup")
async def startup_event():
    global redis_client, cleanup_task
    redis_client = await redis.from_url(REDIS_URL, decode_responses=True)
    
    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    
    # Start background cleanup task
    cleanup_task = asyncio.create_task(cleanup_expired_bookings())
    logger.info("Started expired booking cleanup task")

@app.on_event("shutdown")
async def shutdown_event():
    if cleanup_task:
        cleanup_task.cancel()
    if redis_client:
        await redis_client.close()

@app.get("/")
async def root():
    return {
        "service": "Booking Service",
        "status": "running",
        "version": "1.0.0",
        "features": ["Distributed Locking", "Seat Reservation"]
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}

@app.post("/book", response_model=BookingResponse, status_code=status.HTTP_201_CREATED)
async def book_tickets(
    booking_data: BookingCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Book tickets with Redis distributed lock"""
    from sqlalchemy import select, update
    
    start_time = time.time()
    showtime_id = str(booking_data.showtime_id)
    seat_ids = [str(sid) for sid in booking_data.seat_ids]
    
    logger.info(f"Booking attempt: user={current_user['user_id']}, showtime={showtime_id}, seats={len(seat_ids)}")
    
    # Acquire lock for this showtime
    lock_name = f"showtime:{showtime_id}"
    max_retries = 5
    retry_count = 0
    
    while retry_count < max_retries:
        if await acquire_lock(lock_name, expire_time=10):
            logger.info(f"Lock acquired for {lock_name} after {retry_count} retries")
            try:
                # Verify seats availability in Redis
                for seat_id in seat_ids:
                    seat_key = f"seat:{showtime_id}:{seat_id}"
                    is_booked = await redis_client.get(seat_key)
                    if is_booked:
                        booking_counter.labels(status='seat_taken').inc()
                        raise HTTPException(
                            status_code=400,
                            detail=f"Seat {seat_id} is already booked"
                        )
                
                # Get showtime details from movie service
                async with httpx.AsyncClient() as client:
                    response = await client.get(f"{MOVIE_SERVICE_URL}/showtimes")
                    showtimes = response.json()
                    showtime = next((s for s in showtimes if s['id'] == showtime_id), None)
                    
                    if not showtime:
                        booking_counter.labels(status='showtime_not_found').inc()
                        raise HTTPException(status_code=404, detail="Showtime not found")
                    
                    if showtime['available_seats'] < len(seat_ids):
                        booking_counter.labels(status='no_seats').inc()
                        raise HTTPException(status_code=400, detail="Not enough seats available")
                
                # Calculate total price
                total_price = Decimal(str(showtime['price'])) * len(seat_ids)
                
                # Create booking
                booking_code = generate_booking_code()
                new_booking = Booking(
                    user_id=uuid.UUID(current_user['user_id']),
                    showtime_id=booking_data.showtime_id,
                    booking_code=booking_code,
                    total_seats=len(seat_ids),
                    total_price=total_price,
                    status='pending',
                    payment_status='unpaid',
                    expires_at=datetime.utcnow() + timedelta(minutes=15)
                )
                
                db.add(new_booking)
                await db.flush()
                
                # Reserve seats
                for seat_id in seat_ids:
                    booking_seat = BookingSeat(
                        booking_id=new_booking.id,
                        seat_id=uuid.UUID(seat_id),
                        showtime_id=booking_data.showtime_id,
                        status='reserved'
                    )
                    db.add(booking_seat)
                    
                    # Mark seat as booked in Redis (15 min TTL)
                    seat_key = f"seat:{showtime_id}:{seat_id}"
                    await redis_client.setex(seat_key, 900, str(new_booking.id))
                
                await db.commit()
                await db.refresh(new_booking)
                
                # Record success metrics
                booking_counter.labels(status='success').inc()
                active_bookings_gauge.inc()
                seat_reservation_histogram.observe(time.time() - start_time)
                logger.info(f"Booking successful: booking_code={booking_code}, duration={time.time() - start_time:.3f}s")
                
                return new_booking
                
            finally:
                # Always release the lock
                await release_lock(lock_name)
        else:
            # Lock not acquired, retry
            lock_wait_counter.inc()
            retry_count += 1
            logger.warning(f"Lock wait: {lock_name}, retry {retry_count}/{max_retries}")
            await asyncio.sleep(0.5)
    
    booking_counter.labels(status='timeout').inc()
    logger.error(f"Booking failed: lock timeout for {lock_name}")
    raise HTTPException(status_code=503, detail="Service busy, please try again")

@app.get("/bookings", response_model=List[BookingResponse])
async def get_bookings(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get user bookings"""
    from sqlalchemy import select
    
    result = await db.execute(
        select(Booking).filter(Booking.user_id == uuid.UUID(current_user['user_id']))
    )
    bookings = result.scalars().all()
    return bookings

@app.get("/bookings/{booking_id}", response_model=BookingResponse)
async def get_booking(
    booking_id: uuid.UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get specific booking"""
    from sqlalchemy import select
    
    result = await db.execute(
        select(Booking).filter(
            Booking.id == booking_id,
            Booking.user_id == uuid.UUID(current_user['user_id'])
        )
    )
    booking = result.scalar_one_or_none()
    
    if not booking:
        raise HTTPException(status_code=404, detail="Booking not found")
    
    return booking

@app.post("/bookings/{booking_id}/cancel")
async def cancel_booking(
    booking_id: uuid.UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Cancel a booking and release seats"""
    from sqlalchemy import select, update
    
    logger.info(f"Cancel request: booking={booking_id}, user={current_user['user_id']}")
    
    result = await db.execute(
        select(Booking).filter(
            Booking.id == booking_id,
            Booking.user_id == uuid.UUID(current_user['user_id'])
        )
    )
    booking = result.scalar_one_or_none()
    
    if not booking:
        raise HTTPException(status_code=404, detail="Booking not found")
    
    if booking.status == 'cancelled':
        raise HTTPException(status_code=400, detail="Booking already cancelled")
    
    if booking.payment_status == 'paid':
        raise HTTPException(status_code=400, detail="Cannot cancel paid booking")
    
    # Update booking status
    await db.execute(
        update(Booking)
        .where(Booking.id == booking_id)
        .values(status='cancelled')
    )
    
    # Release seats in Redis
    seat_result = await db.execute(
        select(BookingSeat).filter(BookingSeat.booking_id == booking_id)
    )
    booking_seats = seat_result.scalars().all()
    
    for bs in booking_seats:
        seat_key = f"seat:{bs.showtime_id}:{bs.seat_id}"
        await redis_client.delete(seat_key)
        
        # Update seat status
        await db.execute(
            update(BookingSeat)
            .where(BookingSeat.id == bs.id)
            .values(status='cancelled')
        )
    
    await db.commit()
    
    active_bookings_gauge.dec()
    booking_counter.labels(status='cancelled').inc()
    logger.info(f"Booking cancelled: booking={booking_id}")
    
    return {"message": "Booking cancelled successfully"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
