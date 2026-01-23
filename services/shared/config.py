"""
Shared Configuration Module
Common configuration and utilities for all microservices
"""

import os
from functools import lru_cache
from typing import Optional

from pydantic_settings import BaseSettings


class BaseConfig(BaseSettings):
    """Base configuration shared by all services"""

    # Application
    DEBUG: bool = os.getenv("DEBUG", "false").lower() == "true"
    LOG_LEVEL: str = os.getenv("LOG_LEVEL", "INFO")

    # Database
    DATABASE_HOST: str = os.getenv("DATABASE_HOST", "postgres")
    DATABASE_PORT: int = int(os.getenv("DATABASE_PORT", "5432"))
    DATABASE_NAME: str = os.getenv("DATABASE_NAME", "movie_booking")
    DATABASE_USER: str = os.getenv("DATABASE_USER", "movie_user")
    DATABASE_PASSWORD: str = os.getenv("DATABASE_PASSWORD", "movie_password")

    @property
    def database_url(self) -> str:
        return (
            f"postgresql+asyncpg://{self.DATABASE_USER}:{self.DATABASE_PASSWORD}"
            f"@{self.DATABASE_HOST}:{self.DATABASE_PORT}/{self.DATABASE_NAME}"
        )

    # Redis
    REDIS_HOST: str = os.getenv("REDIS_HOST", "redis")
    REDIS_PORT: int = int(os.getenv("REDIS_PORT", "6379"))
    REDIS_PASSWORD: Optional[str] = os.getenv("REDIS_PASSWORD")
    REDIS_DB: int = int(os.getenv("REDIS_DB", "0"))

    @property
    def redis_url(self) -> str:
        if self.REDIS_PASSWORD:
            return f"redis://:{self.REDIS_PASSWORD}@{self.REDIS_HOST}:{self.REDIS_PORT}/{self.REDIS_DB}"
        return f"redis://{self.REDIS_HOST}:{self.REDIS_PORT}/{self.REDIS_DB}"

    # RabbitMQ
    RABBITMQ_HOST: str = os.getenv("RABBITMQ_HOST", "rabbitmq")
    RABBITMQ_PORT: int = int(os.getenv("RABBITMQ_PORT", "5672"))
    RABBITMQ_USER: str = os.getenv("RABBITMQ_USER", "movie_user")
    RABBITMQ_PASSWORD: str = os.getenv("RABBITMQ_PASSWORD", "movie_password")
    RABBITMQ_VHOST: str = os.getenv("RABBITMQ_VHOST", "/")

    @property
    def rabbitmq_url(self) -> str:
        return (
            f"amqp://{self.RABBITMQ_USER}:{self.RABBITMQ_PASSWORD}"
            f"@{self.RABBITMQ_HOST}:{self.RABBITMQ_PORT}/{self.RABBITMQ_VHOST}"
        )

    # JWT
    JWT_SECRET_KEY: str = os.getenv(
        "JWT_SECRET_KEY", "your-super-secret-jwt-key-change-in-production"
    )
    JWT_ALGORITHM: str = os.getenv("JWT_ALGORITHM", "HS256")
    JWT_ACCESS_TOKEN_EXPIRE_MINUTES: int = int(
        os.getenv("JWT_ACCESS_TOKEN_EXPIRE_MINUTES", "30")
    )
    JWT_REFRESH_TOKEN_EXPIRE_DAYS: int = int(
        os.getenv("JWT_REFRESH_TOKEN_EXPIRE_DAYS", "7")
    )

    # Service Discovery
    AUTH_SERVICE_URL: str = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8000")
    MOVIE_SERVICE_URL: str = os.getenv("MOVIE_SERVICE_URL", "http://movie-service:8000")
    BOOKING_SERVICE_URL: str = os.getenv(
        "BOOKING_SERVICE_URL", "http://booking-service:8000"
    )
    PAYMENT_SERVICE_URL: str = os.getenv(
        "PAYMENT_SERVICE_URL", "http://payment-service:8000"
    )
    NOTIFICATION_SERVICE_URL: str = os.getenv(
        "NOTIFICATION_SERVICE_URL", "http://notification-service:8000"
    )

    # Tracing
    JAEGER_AGENT_HOST: str = os.getenv("JAEGER_AGENT_HOST", "jaeger")
    JAEGER_AGENT_PORT: int = int(os.getenv("JAEGER_AGENT_PORT", "6831"))
    OTEL_EXPORTER_OTLP_ENDPOINT: str = os.getenv(
        "OTEL_EXPORTER_OTLP_ENDPOINT", "http://otel-collector:4317"
    )

    # Metrics
    METRICS_ENABLED: bool = os.getenv("METRICS_ENABLED", "true").lower() == "true"

    class Config:
        env_file = ".env"
        case_sensitive = True


class AuthServiceConfig(BaseConfig):
    """Auth service specific configuration"""

    SERVICE_NAME: str = "auth-service"
    SERVICE_PORT: int = 8000

    # Password hashing
    PASSWORD_MIN_LENGTH: int = 8
    PASSWORD_HASH_ROUNDS: int = 12

    # Token blacklist TTL (seconds)
    TOKEN_BLACKLIST_TTL: int = 86400  # 24 hours


class MovieServiceConfig(BaseConfig):
    """Movie service specific configuration"""

    SERVICE_NAME: str = "movie-service"
    SERVICE_PORT: int = 8000

    # Cache
    MOVIE_CACHE_TTL: int = 300  # 5 minutes
    SHOWTIME_CACHE_TTL: int = 60  # 1 minute


class BookingServiceConfig(BaseConfig):
    """Booking service specific configuration"""

    SERVICE_NAME: str = "booking-service"
    SERVICE_PORT: int = 8000

    # Seat reservation
    SEAT_RESERVATION_TTL: int = 600  # 10 minutes
    BOOKING_EXPIRY_MINUTES: int = 15

    # Lock
    SEAT_LOCK_TTL: int = 30  # seconds
    SEAT_LOCK_RETRY: int = 3


class PaymentServiceConfig(BaseConfig):
    """Payment service specific configuration"""

    SERVICE_NAME: str = "payment-service"
    SERVICE_PORT: int = 8000

    # Payment
    PAYMENT_TIMEOUT: int = 30  # seconds
    IDEMPOTENCY_KEY_TTL: int = 86400  # 24 hours

    # Circuit breaker
    CIRCUIT_BREAKER_THRESHOLD: int = 5
    CIRCUIT_BREAKER_TIMEOUT: int = 30


class NotificationServiceConfig(BaseConfig):
    """Notification service specific configuration"""

    SERVICE_NAME: str = "notification-service"
    SERVICE_PORT: int = 8000

    # Email
    SMTP_HOST: str = os.getenv("SMTP_HOST", "smtp.gmail.com")
    SMTP_PORT: int = int(os.getenv("SMTP_PORT", "587"))
    SMTP_USER: str = os.getenv("SMTP_USER", "")
    SMTP_PASSWORD: str = os.getenv("SMTP_PASSWORD", "")
    SMTP_FROM: str = os.getenv("SMTP_FROM", "noreply@movie-booking.com")

    # Queue
    NOTIFICATION_QUEUE: str = "notification_queue"
    MAX_RETRY_ATTEMPTS: int = 3


@lru_cache()
def get_config(service_name: str) -> BaseConfig:
    """Get configuration for a specific service"""
    config_map = {
        "auth-service": AuthServiceConfig,
        "movie-service": MovieServiceConfig,
        "booking-service": BookingServiceConfig,
        "payment-service": PaymentServiceConfig,
        "notification-service": NotificationServiceConfig,
    }

    config_class = config_map.get(service_name, BaseConfig)
    return config_class()
