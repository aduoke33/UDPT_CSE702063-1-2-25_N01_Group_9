"""
Shared module for Movie Booking Microservices
"""

from .config import (
    BaseConfig,
    AuthServiceConfig,
    MovieServiceConfig,
    BookingServiceConfig,
    PaymentServiceConfig,
    NotificationServiceConfig,
    get_config
)
from .database import DatabaseManager, Base
from .distributed_patterns import (
    CircuitBreaker,
    CircuitBreakerOpenError,
    DistributedLock,
    LockAcquisitionError,
    Retry,
    IdempotencyChecker,
    RateLimiter
)
from .messaging import (
    MessageBroker,
    Event,
    EventTypes,
    Exchanges,
    Queues
)

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
    "Queues"
]
