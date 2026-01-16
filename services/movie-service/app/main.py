from fastapi import FastAPI, Depends, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime, date, time
from typing import Optional, List
from decimal import Decimal
import os
import redis.asyncio as redis
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, Integer, Date, Boolean, DateTime, Text, DECIMAL, ForeignKey, Time
from sqlalchemy.dialects.postgresql import UUID
import uuid
from pydantic import BaseModel
import httpx

DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/1")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")

engine = create_async_engine(DATABASE_URL, echo=True)
async_session_maker = async_sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
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

app = FastAPI(
    title="Movie Service",
    description="Movie Catalog & Showtimes Management",
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
    return {
        "service": "Movie Service",
        "status": "running",
        "version": "1.0.0"
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}

@app.get("/movies", response_model=List[MovieResponse])
async def get_movies(db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select
    result = await db.execute(select(Movie).filter(Movie.is_active == True))
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
    db: AsyncSession = Depends(get_db)
):
    from sqlalchemy import select, and_
    
    query = select(Showtime).filter(Showtime.is_active == True)
    
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
            "genre": new_movie.genre
        }
        await redis_client.setex(
            f"movie:{new_movie.id}",
            3600,
            json.dumps(movie_dict)
        )
    
    return new_movie

@app.get("/theaters", response_model=List[TheaterResponse])
async def get_theaters(db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select
    result = await db.execute(select(Theater))
    theaters = result.scalars().all()
    return theaters

@app.post("/theaters", response_model=TheaterResponse, status_code=status.HTTP_201_CREATED)
async def create_theater(theater_data: TheaterCreate, db: AsyncSession = Depends(get_db)):
    """Create a new theater (admin only)"""
    new_theater = Theater(**theater_data.dict())
    db.add(new_theater)
    await db.commit()
    await db.refresh(new_theater)
    return new_theater

@app.post("/showtimes", response_model=ShowtimeResponse, status_code=status.HTTP_201_CREATED)
async def create_showtime(showtime_data: ShowtimeCreate, db: AsyncSession = Depends(get_db)):
    """Create a new showtime (admin only)"""
    from sqlalchemy import select
    
    # Verify movie exists
    movie_result = await db.execute(select(Movie).filter(Movie.id == showtime_data.movie_id))
    if not movie_result.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Movie not found")
    
    # Verify theater exists
    theater_result = await db.execute(select(Theater).filter(Theater.id == showtime_data.theater_id))
    if not theater_result.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Theater not found")
    
    new_showtime = Showtime(
        **showtime_data.dict(),
        available_seats=showtime_data.total_seats
    )
    db.add(new_showtime)
    await db.commit()
    await db.refresh(new_showtime)
    return new_showtime

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
