from fastapi import FastAPI, HTTPException, Depends, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer
from datetime import datetime
from typing import Optional, List
from decimal import Decimal
import os
import uuid
import httpx
import aio_pika
import json
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, DateTime, DECIMAL, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
from pydantic import BaseModel

# Configuration
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")

# Database Setup
engine = create_async_engine(DATABASE_URL, echo=True)
async_session_maker = async_sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
Base = declarative_base()

# Models
class Payment(Base):
    __tablename__ = "payments"
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    booking_id = Column(UUID(as_uuid=True), ForeignKey("bookings.id"), nullable=False)
    amount = Column(DECIMAL(10, 2), nullable=False)
    payment_method = Column(String(50))
    transaction_id = Column(String(255), unique=True)
    status = Column(String(20), default='pending')
    payment_date = Column(DateTime)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)

# Schemas
class PaymentCreate(BaseModel):
    booking_id: uuid.UUID
    payment_method: str
    amount: Decimal

class PaymentResponse(BaseModel):
    id: uuid.UUID
    booking_id: uuid.UUID
    amount: Decimal
    payment_method: str
    transaction_id: Optional[str]
    status: str
    payment_date: Optional[datetime]
    
    class Config:
        from_attributes = True

app = FastAPI(
    title="Payment Service",
    description="Payment Processing & Transaction Management",
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

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")
rabbitmq_connection: Optional[aio_pika.Connection] = None
rabbitmq_channel: Optional[aio_pika.Channel] = None

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

async def publish_notification(message: dict):
    """Publish notification to RabbitMQ"""
    if rabbitmq_channel:
        await rabbitmq_channel.default_exchange.publish(
            aio_pika.Message(
                body=json.dumps(message).encode(),
                content_type="application/json"
            ),
            routing_key="notifications"
        )

@app.on_event("startup")
async def startup_event():
    global rabbitmq_connection, rabbitmq_channel
    
    # Connect to RabbitMQ
    try:
        rabbitmq_connection = await aio_pika.connect_robust(RABBITMQ_URL)
        rabbitmq_channel = await rabbitmq_connection.channel()
        
        # Declare queue
        await rabbitmq_channel.declare_queue("notifications", durable=True)
    except Exception as e:
        print(f"RabbitMQ connection failed: {e}")
    
    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

@app.on_event("shutdown")
async def shutdown_event():
    if rabbitmq_connection:
        await rabbitmq_connection.close()

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

@app.post("/process", response_model=PaymentResponse, status_code=status.HTTP_201_CREATED)
async def process_payment(
    payment_data: PaymentCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Process payment for a booking"""
    from sqlalchemy import select, update
    
    # Import Booking model (minimal version for query)
    from sqlalchemy import Table, MetaData
    metadata = MetaData()
    bookings_table = Table('bookings', metadata, autoload_with=engine.sync_engine)
    
    # Verify booking exists and belongs to user
    result = await db.execute(
        select(bookings_table).where(
            bookings_table.c.id == payment_data.booking_id,
            bookings_table.c.user_id == uuid.UUID(current_user['user_id'])
        )
    )
    booking = result.first()
    
    if not booking:
        raise HTTPException(status_code=404, detail="Booking not found")
    
    if booking.payment_status == 'paid':
        raise HTTPException(status_code=400, detail="Booking already paid")
    
    if booking.status == 'cancelled':
        raise HTTPException(status_code=400, detail="Cannot pay for cancelled booking")
    
    # Verify amount matches
    if booking.total_price != payment_data.amount:
        raise HTTPException(status_code=400, detail="Payment amount mismatch")
    
    # Simulate payment processing
    transaction_id = f"TXN-{uuid.uuid4().hex[:12].upper()}"
    
    # Create payment record
    new_payment = Payment(
        booking_id=payment_data.booking_id,
        amount=payment_data.amount,
        payment_method=payment_data.payment_method,
        transaction_id=transaction_id,
        status='completed',
        payment_date=datetime.utcnow()
    )
    
    db.add(new_payment)
    
    # Update booking status
    await db.execute(
        update(bookings_table)
        .where(bookings_table.c.id == payment_data.booking_id)
        .values(
            payment_status='paid',
            status='confirmed'
        )
    )
    
    await db.commit()
    await db.refresh(new_payment)
    
    # Send notification via RabbitMQ
    notification_message = {
        "type": "email",
        "user_id": str(current_user['user_id']),
        "subject": "Payment Confirmation",
        "message": f"Your payment of ${payment_data.amount} has been processed successfully. Transaction ID: {transaction_id}"
    }
    await publish_notification(notification_message)
    
    return new_payment

@app.get("/payments", response_model=List[PaymentResponse])
async def get_payments(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get user's payment history"""
    from sqlalchemy import select, join
    from sqlalchemy import Table, MetaData
    
    metadata = MetaData()
    bookings_table = Table('bookings', metadata, autoload_with=engine.sync_engine)
    
    # Get payments for user's bookings
    result = await db.execute(
        select(Payment)
        .select_from(
            Payment.__table__.join(
                bookings_table,
                Payment.booking_id == bookings_table.c.id
            )
        )
        .where(bookings_table.c.user_id == uuid.UUID(current_user['user_id']))
    )
    
    payments = result.scalars().all()
    return payments

@app.get("/payments/{payment_id}", response_model=PaymentResponse)
async def get_payment(
    payment_id: uuid.UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db)
):
    """Get specific payment details"""
    from sqlalchemy import select, join
    from sqlalchemy import Table, MetaData
    
    metadata = MetaData()
    bookings_table = Table('bookings', metadata, autoload_with=engine.sync_engine)
    
    result = await db.execute(
        select(Payment)
        .select_from(
            Payment.__table__.join(
                bookings_table,
                Payment.booking_id == bookings_table.c.id
            )
        )
        .where(
            Payment.id == payment_id,
            bookings_table.c.user_id == uuid.UUID(current_user['user_id'])
        )
    )
    
    payment = result.scalar_one_or_none()
    
    if not payment:
        raise HTTPException(status_code=404, detail="Payment not found")
    
    return payment

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
