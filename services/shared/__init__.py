"""
Shared module for Movie Booking Microservices
"""

from .config import (
    AuthServiceConfig,
    BaseConfig,
    BookingServiceConfig,
    MovieServiceConfig,
    NotificationServiceConfig,
    PaymentServiceConfig,
    get_config,
)
from .database import Base, DatabaseManager
from .distributed_patterns import (
    CircuitBreaker,
    CircuitBreakerOpenError,
    DistributedLock,
    IdempotencyChecker,
    LockAcquisitionError,
    RateLimiter,
    Retry,
)
from .messaging import Event, EventTypes, Exchanges, MessageBroker, Queues

__all__ = [
    # Config
    "BaseConfig",
    "AuthServiceConfig",
    "MovieServiceConfig",
    "BookingServiceConfig",
    "PaymentServiceConfig",
    "NotificationServiceConfig",
    "get_config",
    # Database
    "DatabaseManager",
    "Base",
    # Distributed Patterns
    "CircuitBreaker",
    "CircuitBreakerOpenError",
    "DistributedLock",
    "LockAcquisitionError",
    "Retry",
    "IdempotencyChecker",
    "RateLimiter",
    # Messaging
    "MessageBroker",
    "Event",
    "EventTypes",
    "Exchanges",
    "Queues",
]
