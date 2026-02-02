import asyncio
import json
import logging
import os
import time
import uuid
from contextvars import ContextVar
from datetime import datetime
from typing import List, Optional

import aio_pika
from fastapi import Depends, FastAPI, Request, status
from fastapi.middleware.cors import CORSMiddleware
from prometheus_client import Counter, Gauge, Histogram
from prometheus_fastapi_instrumentator import Instrumentator
from pydantic import BaseModel
from sqlalchemy import Column, DateTime, String, Text
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
notification_counter = Counter(
    "notifications_total", "Total notifications", ["type", "status"]
)
notification_processing_histogram = Histogram(
    "notification_processing_seconds", "Notification processing duration"
)
queue_messages_received = Counter(
    "mq_messages_received_total", "Messages received from queue"
)
pending_notifications_gauge = Gauge(
    "pending_notifications", "Number of pending notifications"
)
notification_send_histogram = Histogram(
    "notification_send_seconds", "Time to send notification", ["type"]
)

# Configuration
DATABASE_URL = os.getenv(
    "DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking"
)
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")
CORS_ORIGINS = os.getenv(
    "CORS_ORIGINS", "http://localhost:8080,http://localhost:3000"
).split(",")

# Database Setup
engine = create_async_engine(
    DATABASE_URL,
    echo=True,
    pool_size=10,
    max_overflow=20,
    pool_pre_ping=True,
)
async_session_maker = async_sessionmaker(
    engine, class_=AsyncSession, expire_on_commit=False
)
Base = declarative_base()


# Models
class Notification(Base):
    __tablename__ = "notifications"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    # Note: No ForeignKey to users table since each service has its own database
    user_id = Column(UUID(as_uuid=True), nullable=False, index=True)
    type = Column(String(50), nullable=False)
    subject = Column(String(255))
    message = Column(Text, nullable=False)
    status = Column(String(20), default="pending")
    sent_at = Column(DateTime)
    created_at = Column(DateTime, default=datetime.utcnow)


# Schemas
class NotificationCreate(BaseModel):
    user_id: uuid.UUID
    type: str  # email, sms, push
    subject: Optional[str] = None
    message: str


class NotificationResponse(BaseModel):
    id: uuid.UUID
    user_id: uuid.UUID
    type: str
    subject: Optional[str]
    message: str
    status: str
    sent_at: Optional[datetime]

    class Config:
        from_attributes = True


app = FastAPI(
    title="Notification Service",
    description="""
## Email & SMS Notifications Service via RabbitMQ

This service consumes messages from **RabbitMQ** and sends notifications to users.

### Features:
- 📧 **Email Notifications** - Send booking confirmations via email
- 📱 **SMS Notifications** - Send SMS alerts
- 🔔 **Push Notifications** - In-app push notifications
- 📋 **Notification History** - View sent notifications

### Message Queue Consumer:
This service listens to the `notifications` queue in RabbitMQ.

Message format expected:
```json
{
  "type": "payment_confirmation",
  "user_id": "uuid",
  "booking_id": "uuid",
  "email": "user@example.com",
  "message": "Your booking has been confirmed!"
}
```

### Notification Types:
- `email` - Email notifications
- `sms` - SMS text messages
- `push` - Push notifications
    """,
    version="1.0.0",
    openapi_tags=[
        {"name": "Notifications", "description": "Notification management"},
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

rabbitmq_connection: Optional[aio_pika.Connection] = None
rabbitmq_channel: Optional[aio_pika.Channel] = None
consumer_task: Optional[asyncio.Task] = None


async def get_db():
    async with async_session_maker() as session:
        yield session


async def send_email_notification(notification_data: dict, db: AsyncSession):
    """Simulate sending email notification"""
    logger.info(f"Sending EMAIL to user {notification_data['user_id']}")
    logger.info(f"Subject: {notification_data.get('subject', 'No Subject')}")
    logger.debug(f"Message: {notification_data['message']}")

    # Save to database
    notification = Notification(
        user_id=uuid.UUID(notification_data["user_id"]),
        type="email",
        subject=notification_data.get("subject"),
        message=notification_data["message"],
        status="sent",
        sent_at=datetime.utcnow(),
    )

    db.add(notification)
    await db.commit()

    return True


async def send_sms_notification(notification_data: dict, db: AsyncSession):
    """Simulate sending SMS notification"""
    logger.info(f"Sending SMS to user {notification_data['user_id']}")
    logger.debug(f"Message: {notification_data['message']}")

    # Save to database
    notification = Notification(
        user_id=uuid.UUID(notification_data["user_id"]),
        type="sms",
        message=notification_data["message"],
        status="sent",
        sent_at=datetime.utcnow(),
    )

    db.add(notification)
    await db.commit()

    return True


async def process_notification(message: aio_pika.IncomingMessage):
    """Process notification from RabbitMQ queue with proper acknowledgment"""
    start_time = time.time()

    async with message.process():
        queue_messages_received.inc()
        pending_notifications_gauge.inc()

        try:
            notification_data = json.loads(message.body.decode())
            notification_type = notification_data.get("type", "email")

            async with async_session_maker() as db:
                if notification_type == "email":
                    await send_email_notification(notification_data, db)
                    notification_counter.labels(type="email", status="success").inc()
                elif notification_type == "sms":
                    await send_sms_notification(notification_data, db)
                    notification_counter.labels(type="sms", status="success").inc()
                else:
                    logger.warning(f"Unknown notification type: {notification_type}")
                    notification_counter.labels(
                        type=notification_type, status="unknown"
                    ).inc()

            notification_processing_histogram.observe(time.time() - start_time)
            notification_send_histogram.labels(type=notification_type).observe(
                time.time() - start_time
            )

        except json.JSONDecodeError as e:
            logger.error(f"Invalid JSON in message: {e}")
            notification_counter.labels(type="unknown", status="error").inc()
        except Exception as e:
            logger.error(f"Error processing notification: {e}", exc_info=True)
            notification_counter.labels(type="unknown", status="error").inc()
        finally:
            pending_notifications_gauge.dec()


async def consume_notifications():
    """Consumer for RabbitMQ notifications queue"""
    if rabbitmq_channel:
        queue = await rabbitmq_channel.declare_queue("notifications", durable=True)
        await queue.consume(process_notification)
        logger.info("Notification consumer started and listening for messages")


@app.on_event("startup")
async def startup_event():
    global rabbitmq_connection, rabbitmq_channel, consumer_task

    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    # Connect to RabbitMQ with retry logic
    max_retries = 5
    for attempt in range(max_retries):
        try:
            rabbitmq_connection = await aio_pika.connect_robust(RABBITMQ_URL)
            rabbitmq_channel = await rabbitmq_connection.channel()

            # Set prefetch count for better load distribution
            await rabbitmq_channel.set_qos(prefetch_count=10)

            # Start consumer
            consumer_task = asyncio.create_task(consume_notifications())
            logger.info("Connected to RabbitMQ and consumer started")
            break

        except Exception as e:
            logger.warning(f"RabbitMQ connection attempt {attempt + 1} failed: {e}")
            if attempt < max_retries - 1:
                await asyncio.sleep(2**attempt)
            else:
                logger.error(
                    f"RabbitMQ connection failed after {max_retries} attempts",
                    exc_info=True,
                )


@app.on_event("shutdown")
async def shutdown_event():
    if consumer_task:
        consumer_task.cancel()
    if rabbitmq_connection:
        await rabbitmq_connection.close()


@app.get("/")
async def root():
    return {
        "service": "Notification Service",
        "status": "running",
        "version": "1.0.0",
        "features": ["Email", "SMS", "RabbitMQ Consumer"],
    }


@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}


@app.post(
    "/send", response_model=NotificationResponse, status_code=status.HTTP_201_CREATED
)
async def send_notification(
    notification_data: NotificationCreate, db: AsyncSession = Depends(get_db)
):
    """Send notification directly (also can be queued via RabbitMQ)"""

    notification = Notification(
        user_id=notification_data.user_id,
        type=notification_data.type,
        subject=notification_data.subject,
        message=notification_data.message,
        status="pending",
    )

    db.add(notification)
    await db.commit()
    await db.refresh(notification)

    # Publish to RabbitMQ for async processing
    if rabbitmq_channel:
        message_body = {
            "user_id": str(notification_data.user_id),
            "type": notification_data.type,
            "subject": notification_data.subject,
            "message": notification_data.message,
        }

        await rabbitmq_channel.default_exchange.publish(
            aio_pika.Message(
                body=json.dumps(message_body).encode(), content_type="application/json"
            ),
            routing_key="notifications",
        )

    return notification


@app.get("/notifications", response_model=List[NotificationResponse])
async def get_notifications(user_id: uuid.UUID, db: AsyncSession = Depends(get_db)):
    """Get notifications for a user"""
    from sqlalchemy import select

    result = await db.execute(
        select(Notification)
        .filter(Notification.user_id == user_id)
        .order_by(Notification.created_at.desc())
    )

    notifications = result.scalars().all()
    return notifications


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8000)
