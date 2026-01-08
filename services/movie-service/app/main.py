from fastapi import FastAPI, Depends
from datetime import datetime, date
from typing import Optional, List
import os
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, Integer, Date, Boolean, DateTime, Text, DECIMAL, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
import uuid
from pydantic import BaseModel

DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")

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

# Schemas
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
    
    class Config:
        from_attributes = True

app = FastAPI(
    title="Movie Service",
    description="Movie Catalog & Showtimes Management",
    version="1.0.0"
)

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

@app.get("/showtimes")
async def get_showtimes():
    """Get all showtimes - To be implemented"""
    return {"message": "Showtimes endpoint - Coming soon"}
