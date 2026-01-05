from fastapi import FastAPI
from datetime import datetime
import redis.asyncio as redis
import os

app = FastAPI(
    title="Booking Service",
    description="Ticket Booking with Distributed Locking",
    version="1.0.0"
)

REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/2")
redis_client = None

@app.on_event("startup")
async def startup_event():
    global redis_client
    redis_client = await redis.from_url(REDIS_URL, decode_responses=True)

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

@app.post("/book")
async def book_tickets():
    """Book tickets with Redis distributed lock - To be implemented in Week 2"""
    return {"message": "Booking endpoint with Redis locking - Coming in Week 2"}

@app.get("/bookings")
async def get_bookings():
    """Get user bookings - To be implemented in Week 2"""
    return {"message": "Bookings list endpoint - Coming in Week 2"}
