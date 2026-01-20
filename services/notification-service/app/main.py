from fastapi import FastAPI, HTTPException, Depends, status
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime
from typing import Optional, List
import os
import uuid
import logging
import time
import aio_pika
import json
import asyncio
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, String, DateTime, Text, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
from pydantic import BaseModel
from prometheus_fastapi_instrumentator import Instrumentator
from prometheus_client import Counter, Histogram, Gauge

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Custom metrics
notification_counter = Counter('notifications_total', 'Total notifications', ['type', 'status'])
notification_processing_histogram = Histogram('notification_processing_seconds', 'Notification processing duration')
queue_messages_received = Counter('mq_messages_received_total', 'Messages received from queue')
pending_notifications_gauge = Gauge('pending_notifications', 'Number of pending notifications')
notification_send_histogram = Histogram('notification_send_seconds', 'Time to send notification', ['type'])

# Configuration
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking")
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://admin:admin123@rabbitmq:5672/")

# Database Setup
engine = create_async_engine(DATABASE_URL, echo=True)
async_session_maker = async_sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
Base = declarative_base()

# Models
class Notification(Base):
    __tablename__ = "notifications"
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    user_id = Column(UUID(as_uuid=True), ForeignKey("users.id"), nullable=False)
    type = Column(String(50), nullable=False)
    subject = Column(String(255))
    message = Column(Text, nullable=False)
    status = Column(String(20), default='pending')
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
    description="Email & SMS Notifications via RabbitMQ",
    version="1.0.0"
)

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

rabbitmq_connection: Optional[aio_pika.Connection] = None
rabbitmq_channel: Optional[aio_pika.Channel] = None
consumer_task: Optional[asyncio.Task] = None

async def get_db():
    async with async_session_maker() as session:
        yield session

async def send_email_notification(notification_data: dict, db: AsyncSession):
    """Simulate sending email notification"""
    print(f"📧 Sending EMAIL to user {notification_data['user_id']}")
    print(f"   Subject: {notification_data.get('subject', 'No Subject')}")
    print(f"   Message: {notification_data['message']}")
    
    # Save to database
    notification = Notification(
        user_id=uuid.UUID(notification_data['user_id']),
        type='email',
        subject=notification_data.get('subject'),
        message=notification_data['message'],
        status='sent',
        sent_at=datetime.utcnow()
    )
    
    db.add(notification)
    await db.commit()
    
    return True

async def send_sms_notification(notification_data: dict, db: AsyncSession):
    """Simulate sending SMS notification"""
    print(f"📱 Sending SMS to user {notification_data['user_id']}")
    print(f"   Message: {notification_data['message']}")
    
    # Save to database
    notification = Notification(
        user_id=uuid.UUID(notification_data['user_id']),
        type='sms',
        message=notification_data['message'],
        status='sent',
        sent_at=datetime.utcnow()
    )
    
    db.add(notification)
    await db.commit()
    
    return True

async def process_notification(message: aio_pika.IncomingMessage):
    """Process notification from RabbitMQ queue"""
    async with message.process():
        notification_data = json.loads(message.body.decode())
        
        async with async_session_maker() as db:
            try:
                notification_type = notification_data.get('type', 'email')
                
                if notification_type == 'email':
                    await send_email_notification(notification_data, db)
                elif notification_type == 'sms':
                    await send_sms_notification(notification_data, db)
                else:
                    print(f"Unknown notification type: {notification_type}")
                
            except Exception as e:
                print(f"Error processing notification: {e}")

async def consume_notifications():
    """Consumer for RabbitMQ notifications queue"""
    if rabbitmq_channel:
        queue = await rabbitmq_channel.declare_queue("notifications", durable=True)
        await queue.consume(process_notification)
        print("🎯 Notification consumer started")

@app.on_event("startup")
async def startup_event():
    global rabbitmq_connection, rabbitmq_channel, consumer_task
    
    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    
    # Connect to RabbitMQ
    try:
        rabbitmq_connection = await aio_pika.connect_robust(RABBITMQ_URL)
        rabbitmq_channel = await rabbitmq_connection.channel()
        
        # Start consumer
        consumer_task = asyncio.create_task(consume_notifications())
        
    except Exception as e:
        print(f"RabbitMQ connection failed: {e}")

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
        "features": ["Email", "SMS", "RabbitMQ Consumer"]
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}

@app.post("/send", response_model=NotificationResponse, status_code=status.HTTP_201_CREATED)
async def send_notification(
    notification_data: NotificationCreate,
    db: AsyncSession = Depends(get_db)
):
    """Send notification directly (also can be queued via RabbitMQ)"""
    
    notification = Notification(
        user_id=notification_data.user_id,
        type=notification_data.type,
        subject=notification_data.subject,
        message=notification_data.message,
        status='pending'
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
            "message": notification_data.message
        }
        
        await rabbitmq_channel.default_exchange.publish(
            aio_pika.Message(
                body=json.dumps(message_body).encode(),
                content_type="application/json"
            ),
            routing_key="notifications"
        )
    
    return notification

@app.get("/notifications", response_model=List[NotificationResponse])
async def get_notifications(
    user_id: uuid.UUID,
    db: AsyncSession = Depends(get_db)
):
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
