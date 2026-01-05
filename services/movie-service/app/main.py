from fastapi import FastAPI
from datetime import datetime

app = FastAPI(
    title="Movie Service",
    description="Movie Catalog & Showtimes Management",
    version="1.0.0"
)

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

@app.get("/movies")
async def get_movies():
    """Get all movies - To be implemented in Week 2"""
    return {"message": "Movie list endpoint - Coming in Week 2"}

@app.get("/showtimes")
async def get_showtimes():
    """Get all showtimes - To be implemented in Week 2"""
    return {"message": "Showtimes endpoint - Coming in Week 2"}
