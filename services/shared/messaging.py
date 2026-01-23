"""
Shared Messaging Module
RabbitMQ integration for async messaging between services
"""

import asyncio
import json
import logging
from typing import Any, Callable, Dict, Optional
from dataclasses import dataclass, asdict

import aio_pika
from aio_pika import Message, ExchangeType
from aio_pika.abc import AbstractIncomingMessage

logger = logging.getLogger(__name__)


@dataclass
class Event:
    """Base event class for messaging"""
    event_type: str
    payload: Dict[str, Any]
    correlation_id: Optional[str] = None
    timestamp: Optional[str] = None
    
    def to_json(self) -> str:
        return json.dumps(asdict(self))
    
    @classmethod
    def from_json(cls, data: str) -> "Event":
        parsed = json.loads(data)
        return cls(**parsed)


class MessageBroker:
    """RabbitMQ message broker for async communication"""
    
    def __init__(self, rabbitmq_url: str):
        self.rabbitmq_url = rabbitmq_url
        self.connection: Optional[aio_pika.RobustConnection] = None
        self.channel: Optional[aio_pika.Channel] = None
        self.exchanges: Dict[str, aio_pika.Exchange] = {}
        self.queues: Dict[str, aio_pika.Queue] = {}
        
    async def connect(self):
        """Establish connection to RabbitMQ"""
        try:
            self.connection = await aio_pika.connect_robust(
                self.rabbitmq_url,
                heartbeat=60
            )
            self.channel = await self.connection.channel()
            await self.channel.set_qos(prefetch_count=10)
            logger.info("Connected to RabbitMQ")
        except Exception as e:
            logger.error(f"Failed to connect to RabbitMQ: {e}")
            raise
            
    async def close(self):
        """Close connection"""
        if self.connection:
            await self.connection.close()
            logger.info("Disconnected from RabbitMQ")
            
    async def declare_exchange(
        self,
        name: str,
        exchange_type: ExchangeType = ExchangeType.TOPIC,
        durable: bool = True
    ) -> aio_pika.Exchange:
        """Declare an exchange"""
        if not self.channel:
            raise RuntimeError("Not connected to RabbitMQ")
            
        exchange = await self.channel.declare_exchange(
            name,
            exchange_type,
            durable=durable
        )
        self.exchanges[name] = exchange
        return exchange
        
    async def declare_queue(
        self,
        name: str,
        durable: bool = True,
        arguments: Optional[Dict] = None
    ) -> aio_pika.Queue:
        """Declare a queue"""
        if not self.channel:
            raise RuntimeError("Not connected to RabbitMQ")
            
        # Add dead letter queue configuration
        if arguments is None:
            arguments = {}
            
        queue = await self.channel.declare_queue(
            name,
            durable=durable,
            arguments=arguments
        )
        self.queues[name] = queue
        return queue
        
    async def publish(
        self,
        exchange_name: str,
        routing_key: str,
        event: Event,
        persistent: bool = True
    ):
        """Publish an event to an exchange"""
        if exchange_name not in self.exchanges:
            await self.declare_exchange(exchange_name)
            
        exchange = self.exchanges[exchange_name]
        
        message = Message(
            body=event.to_json().encode(),
            delivery_mode=aio_pika.DeliveryMode.PERSISTENT if persistent else aio_pika.DeliveryMode.NOT_PERSISTENT,
            correlation_id=event.correlation_id,
            content_type="application/json"
        )
        
        await exchange.publish(message, routing_key=routing_key)
        logger.debug(f"Published event {event.event_type} to {exchange_name}/{routing_key}")
        
    async def subscribe(
        self,
        queue_name: str,
        exchange_name: str,
        routing_key: str,
        callback: Callable[[Event], Any]
    ):
        """Subscribe to events from a queue"""
        if queue_name not in self.queues:
            await self.declare_queue(queue_name)
            
        if exchange_name not in self.exchanges:
            await self.declare_exchange(exchange_name)
            
        queue = self.queues[queue_name]
        exchange = self.exchanges[exchange_name]
        
        # Bind queue to exchange
        await queue.bind(exchange, routing_key=routing_key)
        
        async def process_message(message: AbstractIncomingMessage):
            async with message.process():
                try:
                    event = Event.from_json(message.body.decode())
                    await callback(event)
                except Exception as e:
                    logger.error(f"Error processing message: {e}")
                    # Optionally requeue or send to DLQ
                    raise
                    
        await queue.consume(process_message)
        logger.info(f"Subscribed to {queue_name} for {routing_key}")
        
    async def health_check(self) -> bool:
        """Check RabbitMQ connectivity"""
        try:
            if self.connection and not self.connection.is_closed:
                return True
            return False
        except Exception:
            return False


# Event types
class EventTypes:
    """Standard event types used across services"""
    
    # Booking events
    BOOKING_CREATED = "booking.created"
    BOOKING_CONFIRMED = "booking.confirmed"
    BOOKING_CANCELLED = "booking.cancelled"
    BOOKING_EXPIRED = "booking.expired"
    
    # Payment events
    PAYMENT_INITIATED = "payment.initiated"
    PAYMENT_COMPLETED = "payment.completed"
    PAYMENT_FAILED = "payment.failed"
    PAYMENT_REFUNDED = "payment.refunded"
    
    # Notification events
    NOTIFICATION_REQUESTED = "notification.requested"
    NOTIFICATION_SENT = "notification.sent"
    NOTIFICATION_FAILED = "notification.failed"
    
    # User events
    USER_REGISTERED = "user.registered"
    USER_VERIFIED = "user.verified"


# Exchanges
class Exchanges:
    """Exchange names"""
    
    BOOKING = "booking_exchange"
    PAYMENT = "payment_exchange"
    NOTIFICATION = "notification_exchange"
    USER = "user_exchange"


# Queues
class Queues:
    """Queue names"""
    
    PAYMENT_PROCESS = "payment_process_queue"
    NOTIFICATION_SEND = "notification_send_queue"
    BOOKING_EXPIRE = "booking_expire_queue"
