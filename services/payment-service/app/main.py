from fastapi import FastAPI
from datetime import datetime

app = FastAPI(
    title="Payment Service",
    description="Payment Processing & Transaction Management",
    version="1.0.0"
)

@app.get("/")
async def root():
    return {
        "service": "Payment Service",
        "status": "running",
        "version": "1.0.0"
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}

@app.post("/process")
async def process_payment():
    """Process payment - To be implemented in Week 3"""
    return {"message": "Payment processing - Coming in Week 3"}
