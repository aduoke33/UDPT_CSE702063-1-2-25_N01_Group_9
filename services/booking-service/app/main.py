from fastapi import FastAPI, HTTPException, Depends, status
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
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, Integer, DateTime, DECIMAL, ForeignKey, Boolean
from sqlalchemy.dialects.postgresql import UUID
from pydantic import BaseModel
import asyncio

# Configuration
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/2")
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")
MOVIE_SERVICE_URL = os.getenv("MOVIE_SERVICE_URL", "http://movie-service:8000")

# Database Setup
engine = create_async_engine(DATABASE_URL, echo=True)
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
    description="Ticket Booking with Distributed Locking",
    version="1.0.0"
)

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
    """Verify token with auth service"""
    async with httpx.AsyncClient() as client:
        try:
            response = await client.get(
                f"{AUTH_SERVICE_URL}/verify",
                headers={"Authorization": f"Bearer {token}"}
            )
            if response.status_code == 200:
                return response.json()
            raise HTTPException(status_code=401, detail="Invalid authentication")
        except Exception as e:
            raise HTTPException(status_code=401, detail=f"Auth service error: {str(e)}")

def generate_booking_code() -> str:
    """Generate unique booking code"""
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=10))

async def acquire_lock(lock_name: str, expire_time: int = 10) -> bool:
    """Acquire distributed lock using Redis"""
    lock_key = f"lock:{lock_name}"
    acquired = await redis_client.set(lock_key, "locked", nx=True, ex=expire_time)
    return acquired is not None

async def release_lock(lock_name: str):
    """Release distributed lock"""
    await redis_client.delete(f"lock:{lock_name}")

@app.on_event("startup")
async def startup_event():
    global redis_client
    redis_client = await redis.from_url(REDIS_URL, decode_responses=True)
    
    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

@app.on_event("shutdown")
async def shutdown_event():
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
    
    showtime_id = str(booking_data.showtime_id)
    seat_ids = [str(sid) for sid in booking_data.seat_ids]
    
    # Acquire lock for this showtime
    lock_name = f"showtime:{showtime_id}"
    max_retries = 5
    retry_count = 0
    
    while retry_count < max_retries:
        if await acquire_lock(lock_name, expire_time=10):
            try:
                # Verify seats availability in Redis
                for seat_id in seat_ids:
                    seat_key = f"seat:{showtime_id}:{seat_id}"
                    is_booked = await redis_client.get(seat_key)
                    if is_booked:
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
                        raise HTTPException(status_code=404, detail="Showtime not found")
                    
                    if showtime['available_seats'] < len(seat_ids):
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
                
                return new_booking
                
            finally:
                # Always release the lock
                await release_lock(lock_name)
        else:
            # Lock not acquired, retry
            retry_count += 1
            await asyncio.sleep(0.5)
    
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
    
    return {"message": "Booking cancelled successfully"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
