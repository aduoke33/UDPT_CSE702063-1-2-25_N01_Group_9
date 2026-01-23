import logging
import os
import uuid
from contextvars import ContextVar
from datetime import date, datetime, time
from decimal import Decimal
from typing import List, Optional

import redis.asyncio as redis
from fastapi import Depends, FastAPI, HTTPException, Request, status
from fastapi.middleware.cors import CORSMiddleware
from prometheus_client import Counter, Histogram
from prometheus_fastapi_instrumentator import Instrumentator
from pydantic import BaseModel
from sqlalchemy import (
    DECIMAL,
    Boolean,
    Column,
    Date,
    DateTime,
    ForeignKey,
    Integer,
    String,
    Text,
    Time,
)
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.orm import declarative_base
from starlette.middleware.base import BaseHTTPMiddleware

# Correlation ID context
correlation_id_ctx: ContextVar[str] = ContextVar("correlation_id", default="")


# Configure logging with correlation ID
class CorrelationIdFilter(logging.Filter):
    def filter(self, record):
        record.correlation_id = correlation_id_ctx.get("")
        return True


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - [%(correlation_id)s] - %(message)s",
)
logger = logging.getLogger(__name__)
logger.addFilter(CorrelationIdFilter())

# Custom metrics
movie_requests = Counter(
    "movie_requests_total", "Total movie requests", ["endpoint", "status"]
)
showtime_requests = Counter(
    "showtime_requests_total", "Total showtime requests", ["endpoint"]
)
cache_hits = Counter("cache_hits_total", "Redis cache hits")
cache_misses = Counter("cache_misses_total", "Redis cache misses")
db_query_histogram = Histogram(
    "db_query_seconds", "Database query duration", ["operation"]
)

DATABASE_URL = os.getenv(
    "DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking"
)
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/1")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")

engine = create_async_engine(DATABASE_URL, echo=True)
async_session_maker = async_sessionmaker(
    engine, class_=AsyncSession, expire_on_commit=False
)
Base = declarative_base()


# Models
class Movie(Base):
    __tablename__ = "movies"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    title = Column(String(255), nullable=False)
    description = Column(Text)
    duration_minutes = Column(Integer, nullable=False)
    genre = Column(String(100))
    language = Column(String(50))
    rating = Column(String(10))
    release_date = Column(Date)
    poster_url = Column(String(500))
    trailer_url = Column(String(500))
    director = Column(String(255))
    cast = Column(Text)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)


class Theater(Base):
    __tablename__ = "theaters"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    name = Column(String(255), nullable=False)
    location = Column(String(500))
    city = Column(String(100))
    total_seats = Column(Integer, nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)


class Showtime(Base):
    __tablename__ = "showtimes"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    movie_id = Column(UUID(as_uuid=True), ForeignKey("movies.id"), nullable=False)
    theater_id = Column(UUID(as_uuid=True), ForeignKey("theaters.id"), nullable=False)
    show_date = Column(Date, nullable=False)
    show_time = Column(Time, nullable=False)
    price = Column(DECIMAL(10, 2), nullable=False)
    available_seats = Column(Integer, nullable=False)
    total_seats = Column(Integer, nullable=False)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)


class Seat(Base):
    __tablename__ = "seats"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    theater_id = Column(UUID(as_uuid=True), ForeignKey("theaters.id"), nullable=False)
    seat_row = Column(String(5), nullable=False)
    seat_number = Column(Integer, nullable=False)
    seat_type = Column(String(20), default="regular")


# Schemas
class MovieCreate(BaseModel):
    title: str
    description: Optional[str] = None
    duration_minutes: int
    genre: Optional[str] = None
    language: Optional[str] = None
    rating: Optional[str] = None
    release_date: Optional[date] = None
    poster_url: Optional[str] = None
    trailer_url: Optional[str] = None
    director: Optional[str] = None
    cast: Optional[str] = None


class MovieResponse(BaseModel):
    id: uuid.UUID
    title: str
    description: Optional[str]
    duration_minutes: int
    genre: Optional[str]
    language: Optional[str]
    rating: Optional[str]
    release_date: Optional[date]
    poster_url: Optional[str]
    director: Optional[str]
    is_active: bool

    class Config:
        from_attributes = True


class TheaterCreate(BaseModel):
    name: str
    location: Optional[str] = None
    city: Optional[str] = None
    total_seats: int


class TheaterResponse(BaseModel):
    id: uuid.UUID
    name: str
    location: Optional[str]
    city: Optional[str]
    total_seats: int

    class Config:
        from_attributes = True


class ShowtimeCreate(BaseModel):
    movie_id: uuid.UUID
    theater_id: uuid.UUID
    show_date: date
    show_time: time
    price: Decimal
    total_seats: int


class ShowtimeResponse(BaseModel):
    id: uuid.UUID
    movie_id: uuid.UUID
    theater_id: uuid.UUID
    show_date: date
    show_time: time
    price: Decimal
    available_seats: int
    total_seats: int

    class Config:
        from_attributes = True


class SeatResponse(BaseModel):
    id: uuid.UUID
    theater_id: uuid.UUID
    seat_row: str
    seat_number: int
    seat_type: str

    class Config:
        from_attributes = True


class ShowtimeDetailResponse(BaseModel):
    id: uuid.UUID
    movie_id: uuid.UUID
    theater_id: uuid.UUID
    show_date: date
    show_time: time
    price: Decimal
    available_seats: int
    total_seats: int
    movie: Optional[MovieResponse] = None
    theater: Optional[TheaterResponse] = None

    class Config:
        from_attributes = True


app = FastAPI(
    title="Movie Service",
    description="""
## Movie Catalog & Showtimes Management Service

This service manages movies, theaters, showtimes and seat availability.

### Features:
- 🎬 **Movies** - CRUD operations for movie catalog
- 🏛️ **Theaters** - Manage theater information
- 🕐 **Showtimes** - Schedule movie screenings
- 💺 **Seats** - Check seat availability with Redis caching

### Caching:
Seat availability is cached in Redis for fast lookup during booking.
    """,
    version="1.0.0",
    openapi_tags=[
        {"name": "Movies", "description": "Movie catalog operations"},
        {"name": "Theaters", "description": "Theater management"},
        {"name": "Showtimes", "description": "Movie showtime scheduling"},
        {"name": "Seats", "description": "Seat availability"},
        {"name": "Health", "description": "Service health checks"},
    ],
)


# Correlation ID Middleware
class CorrelationIdMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        correlation_id = request.headers.get("X-Correlation-ID", str(uuid.uuid4()))
        correlation_id_ctx.set(correlation_id)
        logger.info(f"Request started: {request.method} {request.url.path}")
        response = await call_next(request)
        response.headers["X-Correlation-ID"] = correlation_id
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

# Redis Client
redis_client: Optional[redis.Redis] = None


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


async def get_db():
    async with async_session_maker() as session:
        yield session


@app.get("/")
async def root():
    return {"service": "Movie Service", "status": "running", "version": "1.0.0"}


@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}


@app.get("/movies", response_model=List[MovieResponse])
async def get_movies(db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select

    result = await db.execute(select(Movie).filter(Movie.is_active.is_(True)))
    movies = result.scalars().all()
    return movies


@app.get("/movies/{movie_id}", response_model=MovieResponse)
async def get_movie(movie_id: uuid.UUID, db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select

    result = await db.execute(select(Movie).filter(Movie.id == movie_id))
    movie = result.scalar_one_or_none()

    if not movie:
        raise HTTPException(status_code=404, detail="Movie not found")

    return movie


@app.get("/showtimes", response_model=List[ShowtimeResponse])
async def get_showtimes(
    movie_id: Optional[uuid.UUID] = None,
    show_date: Optional[date] = None,
    db: AsyncSession = Depends(get_db),
):
    from sqlalchemy import and_, select  # noqa: F401

    query = select(Showtime).filter(Showtime.is_active.is_(True))

    if movie_id:
        query = query.filter(Showtime.movie_id == movie_id)
    if show_date:
        query = query.filter(Showtime.show_date == show_date)

    result = await db.execute(query)
    showtimes = result.scalars().all()
    return showtimes


@app.post("/movies", response_model=MovieResponse, status_code=status.HTTP_201_CREATED)
async def create_movie(movie_data: MovieCreate, db: AsyncSession = Depends(get_db)):
    """Create a new movie (admin only)"""
    new_movie = Movie(**movie_data.dict())
    db.add(new_movie)
    await db.commit()
    await db.refresh(new_movie)

    # Cache in Redis
    if redis_client:
        import json

        movie_dict = {
            "id": str(new_movie.id),
            "title": new_movie.title,
            "duration_minutes": new_movie.duration_minutes,
            "genre": new_movie.genre,
        }
        await redis_client.setex(f"movie:{new_movie.id}", 3600, json.dumps(movie_dict))

    return new_movie


@app.get("/theaters", response_model=List[TheaterResponse])
async def get_theaters(db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select

    result = await db.execute(select(Theater))
    theaters = result.scalars().all()
    return theaters


@app.post(
    "/theaters", response_model=TheaterResponse, status_code=status.HTTP_201_CREATED
)
async def create_theater(
    theater_data: TheaterCreate, db: AsyncSession = Depends(get_db)
):
    """Create a new theater (admin only)"""
    new_theater = Theater(**theater_data.dict())
    db.add(new_theater)
    await db.commit()
    await db.refresh(new_theater)
    return new_theater


@app.post(
    "/showtimes", response_model=ShowtimeResponse, status_code=status.HTTP_201_CREATED
)
async def create_showtime(
    showtime_data: ShowtimeCreate, db: AsyncSession = Depends(get_db)
):
    """Create a new showtime (admin only)"""
    from sqlalchemy import select

    # Verify movie exists
    movie_result = await db.execute(
        select(Movie).filter(Movie.id == showtime_data.movie_id)
    )
    if not movie_result.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Movie not found")

    # Verify theater exists
    theater_result = await db.execute(
        select(Theater).filter(Theater.id == showtime_data.theater_id)
    )
    if not theater_result.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Theater not found")

    new_showtime = Showtime(
        **showtime_data.dict(), available_seats=showtime_data.total_seats
    )
    db.add(new_showtime)
    await db.commit()
    await db.refresh(new_showtime)
    return new_showtime


@app.get("/showtimes/{showtime_id}", response_model=ShowtimeDetailResponse)
async def get_showtime_detail(
    showtime_id: uuid.UUID, db: AsyncSession = Depends(get_db)
):
    """Get showtime detail with movie and theater info"""
    from sqlalchemy import select

    result = await db.execute(select(Showtime).filter(Showtime.id == showtime_id))
    showtime = result.scalar_one_or_none()

    if not showtime:
        raise HTTPException(status_code=404, detail="Showtime not found")

    # Get movie and theater
    movie_result = await db.execute(select(Movie).filter(Movie.id == showtime.movie_id))
    movie = movie_result.scalar_one_or_none()

    theater_result = await db.execute(
        select(Theater).filter(Theater.id == showtime.theater_id)
    )
    theater = theater_result.scalar_one_or_none()

    return ShowtimeDetailResponse(
        id=showtime.id,
        movie_id=showtime.movie_id,
        theater_id=showtime.theater_id,
        show_date=showtime.show_date,
        show_time=showtime.show_time,
        price=showtime.price,
        available_seats=showtime.available_seats,
        total_seats=showtime.total_seats,
        movie=movie,
        theater=theater,
    )


@app.get("/seats/{theater_id}", response_model=List[SeatResponse])
async def get_theater_seats(theater_id: uuid.UUID, db: AsyncSession = Depends(get_db)):
    """Get all seats for a theater"""
    from sqlalchemy import select

    result = await db.execute(
        select(Seat)
        .filter(Seat.theater_id == theater_id)
        .order_by(Seat.seat_row, Seat.seat_number)
    )
    seats = result.scalars().all()
    return seats


@app.get("/showtimes/{showtime_id}/available-seats")
async def get_available_seats(
    showtime_id: uuid.UUID, db: AsyncSession = Depends(get_db)
):
    """Get available seats for a showtime (checking Redis for booked seats)"""
    from sqlalchemy import select

    # Get showtime
    result = await db.execute(select(Showtime).filter(Showtime.id == showtime_id))
    showtime = result.scalar_one_or_none()

    if not showtime:
        raise HTTPException(status_code=404, detail="Showtime not found")

    # Get all seats for the theater
    seats_result = await db.execute(
        select(Seat)
        .filter(Seat.theater_id == showtime.theater_id)
        .order_by(Seat.seat_row, Seat.seat_number)
    )
    all_seats = seats_result.scalars().all()

    # Check which seats are booked in Redis
    available_seats = []
    booked_seats = []

    for seat in all_seats:
        seat_key = f"seat:{showtime_id}:{seat.id}"
        is_booked = await redis_client.get(seat_key) if redis_client else None

        seat_info = {
            "id": str(seat.id),
            "seat_row": seat.seat_row,
            "seat_number": seat.seat_number,
            "seat_type": seat.seat_type,
            "is_available": is_booked is None,
        }

        if is_booked:
            booked_seats.append(seat_info)
        else:
            available_seats.append(seat_info)

    return {
        "showtime_id": str(showtime_id),
        "total_seats": len(all_seats),
        "available_count": len(available_seats),
        "booked_count": len(booked_seats),
        "available_seats": available_seats,
        "booked_seats": booked_seats,
    }


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8000)
