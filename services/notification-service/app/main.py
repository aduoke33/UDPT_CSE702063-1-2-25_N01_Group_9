from fastapi import FastAPI
from datetime import datetime

app = FastAPI(
    title="Notification Service",
    description="Email & SMS Notifications via RabbitMQ",
    version="1.0.0"
)

@app.get("/")
async def root():
    return {
        "service": "Notification Service",
        "status": "running",
        "version": "1.0.0",
        "features": ["Email", "SMS", "RabbitMQ Consumer"]
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}

@app.post("/send")
async def send_notification():
    """Send notification - To be implemented in Week 3"""
    return {"message": "Notification sending - Coming in Week 3"}
