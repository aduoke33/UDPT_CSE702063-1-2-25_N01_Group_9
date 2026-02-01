import asyncio
import json
import logging
import os
import time
import uuid
from contextvars import ContextVar
from datetime import datetime
from decimal import Decimal
from typing import List, Optional

import aio_pika
import httpx
from fastapi import Depends, FastAPI, HTTPException, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer
from prometheus_client import Counter, Histogram
from prometheus_fastapi_instrumentator import Instrumentator
from pydantic import BaseModel
from sqlalchemy import DECIMAL, Column, DateTime, String, select
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


# Basic logging config (without correlation_id to avoid external lib crashes)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger(__name__)
logger.addFilter(CorrelationIdFilter())

# Custom metrics
payment_counter = Counter(
    "payments_total", "Total payment attempts", ["status", "method"]
)
payment_amount_histogram = Histogram(
    "payment_amount",
    "Payment amount distribution",
    buckets=[10, 50, 100, 200, 500, 1000],
)
payment_processing_histogram = Histogram(
    "payment_processing_seconds", "Payment processing duration"
)
refund_counter = Counter("refunds_total", "Total refund attempts", ["status"])
message_queue_counter = Counter(
    "mq_messages_total", "Messages sent to queue", ["queue"]
)

# Configuration
DATABASE_URL = os.getenv(
    "DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking"
)
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")
AUTH_SERVICE_URL = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")
BOOKING_SERVICE_URL = os.getenv("BOOKING_SERVICE_URL", "http://booking-service:8000")
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/3")
REQUEST_TIMEOUT = float(os.getenv("REQUEST_TIMEOUT", "10.0"))
CORS_ORIGINS = os.getenv("CORS_ORIGINS", "http://localhost:8080,http://localhost:3000").split(",")

# Database Setup with connection pooling
engine = create_async_engine(
    DATABASE_URL, echo=True, pool_size=10, max_overflow=20, pool_pre_ping=True
)
async_session_maker = async_sessionmaker(
    engine, class_=AsyncSession, expire_on_commit=False
)
Base = declarative_base()

# Redis client for idempotency
redis_client: Optional = None


# Models
class Payment(Base):
    __tablename__ = "payments"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    # Note: No ForeignKey to bookings table since each service has its own database
    booking_id = Column(UUID(as_uuid=True), nullable=False, index=True)
    user_id = Column(
        UUID(as_uuid=True), nullable=False, index=True
    )  # Store user_id for access control
    amount = Column(DECIMAL(10, 2), nullable=False)
    payment_method = Column(String(50))
    transaction_id = Column(String(255), unique=True)
    idempotency_key = Column(String(255), unique=True, nullable=True)
    status = Column(String(20), default="pending")
    payment_date = Column(DateTime)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow)


# Schemas
class PaymentCreate(BaseModel):
    booking_id: uuid.UUID
    payment_method: str
    amount: Decimal
    idempotency_key: Optional[str] = None  # Client-provided idempotency key


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
    description="""
## Payment Processing & Transaction Management Service

This service handles payment processing and sends notifications via **RabbitMQ**.

### Features:
- 💳 **Process Payment** - Handle payment for bookings
- 💵 **Refund** - Process refunds for cancelled bookings
- 📊 **Payment History** - View payment records

### Message Queue Integration:
After successful payment, a message is published to RabbitMQ:
```json
{
  "type": "payment_confirmation",
  "booking_id": "...",
  "user_id": "...",
  "amount": 150000,
  "transaction_id": "TXN..."
}
```
The Notification Service consumes these messages to send confirmations.

### Payment Methods:
- `credit_card` - Credit card payment
- `debit_card` - Debit card payment
- `bank_transfer` - Bank transfer
- `e_wallet` - E-wallet (MoMo, ZaloPay, etc.)
    """,
    version="1.0.0",
    openapi_tags=[
        {"name": "Payments", "description": "Payment processing operations"},
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
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS", "PATCH"],
    allow_headers=["Authorization", "Content-Type", "X-Correlation-ID"],
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
                headers={"Authorization": f"Bearer {token}"},
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
                body=json.dumps(message).encode(), content_type="application/json"
            ),
            routing_key="notifications",
        )
        message_queue_counter.labels(queue="notifications").inc()
        logger.info(f"Published notification message: {message.get('type')}")


# =====================================================
# IDEMPOTENCY CHECK
# =====================================================
async def check_idempotency(
    idempotency_key: str, db: AsyncSession
) -> Optional[Payment]:
    """Check if a payment with this idempotency key already exists"""
    if not idempotency_key:
        return None

    # Check Redis cache first
    cached = await redis_client.get(f"idempotency:{idempotency_key}")
    if cached:
        logger.info(f"Idempotency hit from cache: {idempotency_key}")
        return None  # Will be handled by returning cached payment

    # Check database
    result = await db.execute(
        select(Payment).filter(Payment.idempotency_key == idempotency_key)
    )
    existing = result.scalar_one_or_none()
    if existing:
        logger.info(f"Idempotency hit from DB: {idempotency_key}")
        return existing
    return None


async def store_idempotency(idempotency_key: str, payment_id: str):
    """Store idempotency key in Redis with 24h TTL"""
    if idempotency_key:
        await redis_client.setex(f"idempotency:{idempotency_key}", 86400, payment_id)


@app.on_event("startup")
async def startup_event():
    global rabbitmq_connection, rabbitmq_channel, redis_client

    # Connect to Redis
    import redis.asyncio as aioredis

    redis_client = await aioredis.from_url(REDIS_URL, decode_responses=True)
    logger.info("Connected to Redis")

    # Connect to RabbitMQ with retry
    max_retries = 5
    for attempt in range(max_retries):
        try:
            rabbitmq_connection = await aio_pika.connect_robust(RABBITMQ_URL)
            rabbitmq_channel = await rabbitmq_connection.channel()
            await rabbitmq_channel.declare_queue("notifications", durable=True)
            logger.info("Connected to RabbitMQ")
            break
        except Exception as e:
            logger.warning(f"RabbitMQ connection attempt {attempt + 1} failed: {e}")
            if attempt < max_retries - 1:
                await asyncio.sleep(2**attempt)

    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)


@app.on_event("shutdown")
async def shutdown_event():
    if rabbitmq_connection:
        await rabbitmq_connection.close()
    if redis_client:
        await redis_client.close()


@app.get("/")
async def root():
    return {"service": "Payment Service", "status": "running", "version": "1.0.0"}


@app.get("/health")
async def health_check():
    rabbitmq_healthy = (
        rabbitmq_connection is not None and not rabbitmq_connection.is_closed
    )
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat(),
        "dependencies": {
            "rabbitmq": "connected" if rabbitmq_healthy else "disconnected",
            "redis": "connected" if redis_client else "disconnected",
        },
    }


@app.post(
    "/process", response_model=PaymentResponse, status_code=status.HTTP_201_CREATED
)
async def process_payment(
    payment_data: PaymentCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Process payment for a booking.

    **Idempotency**: Provide `idempotency_key` to prevent duplicate payments.
    If the same key is used twice, the original payment is returned.
    """
    start_time = time.time()

    # Check idempotency first
    if payment_data.idempotency_key:
        existing_payment = await check_idempotency(payment_data.idempotency_key, db)
        if existing_payment:
            logger.info(
                f"Returning existing payment for idempotency key: {payment_data.idempotency_key}"
            )
            return existing_payment

    # Verify booking exists via booking-service API (database-per-service pattern)
    async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT) as client:
        try:
            response = await client.get(
                f"{BOOKING_SERVICE_URL}/internal/bookings/{payment_data.booking_id}",
            )
            if response.status_code == 404:
                raise HTTPException(status_code=404, detail="Booking not found")
            if response.status_code != 200:
                raise HTTPException(status_code=502, detail="Booking service error")
            booking = response.json()
        except httpx.RequestError as e:
            logger.error(f"Booking service request error: {e}")
            raise HTTPException(status_code=503, detail="Booking service unavailable")

    # Verify user owns this booking
    if booking.get("user_id") != str(current_user["user_id"]):
        raise HTTPException(status_code=403, detail="Booking does not belong to user")

    if booking.get("payment_status") == "paid":
        raise HTTPException(status_code=400, detail="Booking already paid")

    if booking.get("status") == "cancelled":
        raise HTTPException(status_code=400, detail="Cannot pay for cancelled booking")

    # Verify amount matches
    booking_price = Decimal(str(booking.get("total_price", 0)))
    if booking_price != payment_data.amount:
        raise HTTPException(status_code=400, detail="Payment amount mismatch")

    # Simulate payment processing
    transaction_id = f"TXN-{uuid.uuid4().hex[:12].upper()}"

    # Create payment record
    new_payment = Payment(
        booking_id=payment_data.booking_id,
        user_id=uuid.UUID(current_user["user_id"]),
        amount=payment_data.amount,
        payment_method=payment_data.payment_method,
        transaction_id=transaction_id,
        idempotency_key=payment_data.idempotency_key,
        status="completed",
        payment_date=datetime.utcnow(),
    )

    db.add(new_payment)
    await db.commit()
    await db.refresh(new_payment)

    # Update booking status via booking-service API
    async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT) as client:
        try:
            await client.patch(
                f"{BOOKING_SERVICE_URL}/internal/bookings/{payment_data.booking_id}/payment-status",
                json={"payment_status": "paid", "status": "confirmed"},
            )
        except httpx.RequestError as e:
            logger.warning(f"Failed to update booking status: {e}")
            # Payment is recorded, booking update can be retried or reconciled later

    # Store idempotency key
    if payment_data.idempotency_key:
        await store_idempotency(payment_data.idempotency_key, str(new_payment.id))

    # Record metrics
    payment_counter.labels(status="success", method=payment_data.payment_method).inc()
    payment_amount_histogram.observe(float(payment_data.amount))
    payment_processing_histogram.observe(time.time() - start_time)

    logger.info(f"Payment processed: {transaction_id}, amount={payment_data.amount}")

    # Send notification via RabbitMQ
    notification_message = {
        "type": "email",
        "user_id": str(current_user["user_id"]),
        "subject": "Payment Confirmation",
        "message": f"Your payment of ${payment_data.amount} has been processed successfully. Transaction ID: {transaction_id}",
    }
    await publish_notification(notification_message)

    return new_payment


@app.get("/payments", response_model=List[PaymentResponse])
async def get_payments(
    current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_db)
):
    """Get user's payment history"""
    # Get payments for this user directly (no cross-db join needed)
    result = await db.execute(
        select(Payment).where(Payment.user_id == uuid.UUID(current_user["user_id"]))
    )

    payments = result.scalars().all()
    return payments


@app.get("/payments/{payment_id}", response_model=PaymentResponse)
async def get_payment(
    payment_id: uuid.UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get specific payment details"""
    result = await db.execute(
        select(Payment).where(
            Payment.id == payment_id,
            Payment.user_id == uuid.UUID(current_user["user_id"]),
        )
    )

    payment = result.scalar_one_or_none()

    if not payment:
        raise HTTPException(status_code=404, detail="Payment not found")

    return payment


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8000)
