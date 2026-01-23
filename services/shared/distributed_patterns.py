"""
Shared utility module for distributed patterns
Used across all microservices for consistent implementations
"""

import asyncio
import functools
import logging
import random
import time
from typing import Any, Callable, Optional, TypeVar

import redis.asyncio as redis

logger = logging.getLogger(__name__)

T = TypeVar("T")


class CircuitBreaker:
    """
    Circuit Breaker Pattern Implementation
    
    States:
    - CLOSED: Normal operation, requests pass through
    - OPEN: Circuit is open, requests fail fast
    - HALF_OPEN: Testing if service recovered
    
    Usage:
        cb = CircuitBreaker(failure_threshold=5, recovery_timeout=30)
        
        @cb.call
        async def call_external_service():
            ...
    """
    
    CLOSED = "CLOSED"
    OPEN = "OPEN"
    HALF_OPEN = "HALF_OPEN"
    
    def __init__(
        self,
        failure_threshold: int = 5,
        recovery_timeout: int = 30,
        half_open_max_calls: int = 3
    ):
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.half_open_max_calls = half_open_max_calls
        
        self.state = self.CLOSED
        self.failure_count = 0
        self.success_count = 0
        self.last_failure_time = 0
        self.half_open_calls = 0
        
    def _should_allow_request(self) -> bool:
        """Check if request should be allowed based on circuit state"""
        if self.state == self.CLOSED:
            return True
            
        if self.state == self.OPEN:
            # Check if recovery timeout has passed
            if time.time() - self.last_failure_time >= self.recovery_timeout:
                self.state = self.HALF_OPEN
                self.half_open_calls = 0
                logger.info("Circuit breaker transitioning to HALF_OPEN")
                return True
            return False
            
        if self.state == self.HALF_OPEN:
            return self.half_open_calls < self.half_open_max_calls
            
        return False
        
    def _record_success(self):
        """Record successful call"""
        if self.state == self.HALF_OPEN:
            self.success_count += 1
            if self.success_count >= self.half_open_max_calls:
                self.state = self.CLOSED
                self.failure_count = 0
                self.success_count = 0
                logger.info("Circuit breaker CLOSED - service recovered")
        else:
            self.failure_count = 0
            
    def _record_failure(self):
        """Record failed call"""
        self.failure_count += 1
        self.last_failure_time = time.time()
        
        if self.state == self.HALF_OPEN:
            self.state = self.OPEN
            logger.warning("Circuit breaker OPEN - service still failing")
        elif self.failure_count >= self.failure_threshold:
            self.state = self.OPEN
            logger.warning(f"Circuit breaker OPEN after {self.failure_count} failures")
            
    def call(self, func: Callable[..., T]) -> Callable[..., T]:
        """Decorator to wrap function with circuit breaker"""
        @functools.wraps(func)
        async def wrapper(*args, **kwargs) -> T:
            if not self._should_allow_request():
                raise CircuitBreakerOpenError(
                    f"Circuit breaker is {self.state}. Service unavailable."
                )
                
            if self.state == self.HALF_OPEN:
                self.half_open_calls += 1
                
            try:
                result = await func(*args, **kwargs)
                self._record_success()
                return result
            except Exception as e:
                self._record_failure()
                raise
                
        return wrapper


class CircuitBreakerOpenError(Exception):
    """Raised when circuit breaker is open"""
    pass


class DistributedLock:
    """
    Distributed Lock using Redis
    
    Implements the Redlock algorithm for distributed locking.
    
    Usage:
        lock = DistributedLock(redis_client, "resource_name")
        async with lock:
            # Critical section
            ...
    """
    
    def __init__(
        self,
        redis_client: redis.Redis,
        resource_name: str,
        ttl_seconds: int = 30,
        retry_times: int = 3,
        retry_delay_ms: int = 200
    ):
        self.redis = redis_client
        self.resource_name = f"lock:{resource_name}"
        self.ttl_seconds = ttl_seconds
        self.retry_times = retry_times
        self.retry_delay_ms = retry_delay_ms
        self.lock_value: Optional[str] = None
        
    async def acquire(self) -> bool:
        """Attempt to acquire the lock"""
        import uuid
        self.lock_value = str(uuid.uuid4())
        
        for attempt in range(self.retry_times):
            # SET NX with TTL
            acquired = await self.redis.set(
                self.resource_name,
                self.lock_value,
                nx=True,
                ex=self.ttl_seconds
            )
            
            if acquired:
                logger.debug(f"Lock acquired: {self.resource_name}")
                return True
                
            # Wait before retry with jitter
            delay = (self.retry_delay_ms + random.randint(0, 50)) / 1000
            await asyncio.sleep(delay)
            
        logger.warning(f"Failed to acquire lock: {self.resource_name}")
        return False
        
    async def release(self) -> bool:
        """Release the lock if we own it"""
        if not self.lock_value:
            return False
            
        # Lua script for atomic check-and-delete
        lua_script = """
        if redis.call("get", KEYS[1]) == ARGV[1] then
            return redis.call("del", KEYS[1])
        else
            return 0
        end
        """
        
        try:
            result = await self.redis.eval(
                lua_script,
                1,
                self.resource_name,
                self.lock_value
            )
            released = result == 1
            if released:
                logger.debug(f"Lock released: {self.resource_name}")
            return released
        except Exception as e:
            logger.error(f"Error releasing lock: {e}")
            return False
            
    async def extend(self, additional_seconds: int) -> bool:
        """Extend lock TTL if we own it"""
        if not self.lock_value:
            return False
            
        lua_script = """
        if redis.call("get", KEYS[1]) == ARGV[1] then
            return redis.call("expire", KEYS[1], ARGV[2])
        else
            return 0
        end
        """
        
        try:
            result = await self.redis.eval(
                lua_script,
                1,
                self.resource_name,
                self.lock_value,
                self.ttl_seconds + additional_seconds
            )
            return result == 1
        except Exception as e:
            logger.error(f"Error extending lock: {e}")
            return False
            
    async def __aenter__(self):
        acquired = await self.acquire()
        if not acquired:
            raise LockAcquisitionError(f"Could not acquire lock: {self.resource_name}")
        return self
        
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        await self.release()
        return False


class LockAcquisitionError(Exception):
    """Raised when lock cannot be acquired"""
    pass


class Retry:
    """
    Retry with exponential backoff
    
    Usage:
        @Retry(max_attempts=3, base_delay=1, max_delay=30)
        async def unreliable_operation():
            ...
    """
    
    def __init__(
        self,
        max_attempts: int = 3,
        base_delay: float = 1.0,
        max_delay: float = 60.0,
        exponential_base: float = 2.0,
        jitter: bool = True,
        exceptions: tuple = (Exception,)
    ):
        self.max_attempts = max_attempts
        self.base_delay = base_delay
        self.max_delay = max_delay
        self.exponential_base = exponential_base
        self.jitter = jitter
        self.exceptions = exceptions
        
    def __call__(self, func: Callable[..., T]) -> Callable[..., T]:
        @functools.wraps(func)
        async def wrapper(*args, **kwargs) -> T:
            last_exception = None
            
            for attempt in range(self.max_attempts):
                try:
                    return await func(*args, **kwargs)
                except self.exceptions as e:
                    last_exception = e
                    
                    if attempt == self.max_attempts - 1:
                        break
                        
                    # Calculate delay with exponential backoff
                    delay = min(
                        self.base_delay * (self.exponential_base ** attempt),
                        self.max_delay
                    )
                    
                    # Add jitter
                    if self.jitter:
                        delay = delay * (0.5 + random.random())
                        
                    logger.warning(
                        f"Attempt {attempt + 1}/{self.max_attempts} failed: {e}. "
                        f"Retrying in {delay:.2f}s"
                    )
                    await asyncio.sleep(delay)
                    
            raise last_exception
            
        return wrapper


class IdempotencyChecker:
    """
    Idempotency check using Redis
    
    Ensures operations are only executed once for a given idempotency key.
    
    Usage:
        checker = IdempotencyChecker(redis_client)
        
        async def process_payment(idempotency_key, amount):
            is_new, result = await checker.check_and_store(
                idempotency_key,
                lambda: do_payment(amount)
            )
            return result
    """
    
    def __init__(
        self,
        redis_client: redis.Redis,
        prefix: str = "idempotency",
        ttl_seconds: int = 86400  # 24 hours
    ):
        self.redis = redis_client
        self.prefix = prefix
        self.ttl_seconds = ttl_seconds
        
    def _get_key(self, idempotency_key: str) -> str:
        return f"{self.prefix}:{idempotency_key}"
        
    async def check(self, idempotency_key: str) -> Optional[dict]:
        """Check if operation was already performed"""
        key = self._get_key(idempotency_key)
        result = await self.redis.get(key)
        
        if result:
            import json
            return json.loads(result)
        return None
        
    async def store(self, idempotency_key: str, result: dict) -> None:
        """Store operation result"""
        import json
        key = self._get_key(idempotency_key)
        await self.redis.set(
            key,
            json.dumps(result),
            ex=self.ttl_seconds
        )
        
    async def check_and_execute(
        self,
        idempotency_key: str,
        operation: Callable[[], T]
    ) -> tuple[bool, T]:
        """
        Check idempotency and execute operation if new.
        
        Returns:
            tuple: (is_new, result)
            - is_new: True if operation was executed, False if cached
            - result: The operation result
        """
        # Check existing result
        existing = await self.check(idempotency_key)
        if existing is not None:
            logger.info(f"Idempotency hit for key: {idempotency_key}")
            return False, existing
            
        # Execute operation
        result = await operation() if asyncio.iscoroutinefunction(operation) else operation()
        
        # Store result
        await self.store(idempotency_key, result)
        
        return True, result


class RateLimiter:
    """
    Rate limiter using Redis sliding window
    
    Usage:
        limiter = RateLimiter(redis_client, limit=100, window_seconds=60)
        
        if await limiter.is_allowed("user:123"):
            # Process request
        else:
            # Rate limited
    """
    
    def __init__(
        self,
        redis_client: redis.Redis,
        limit: int = 100,
        window_seconds: int = 60
    ):
        self.redis = redis_client
        self.limit = limit
        self.window_seconds = window_seconds
        
    async def is_allowed(self, identifier: str) -> bool:
        """Check if request is allowed under rate limit"""
        key = f"ratelimit:{identifier}"
        now = time.time()
        window_start = now - self.window_seconds
        
        pipe = self.redis.pipeline()
        
        # Remove old entries
        pipe.zremrangebyscore(key, 0, window_start)
        
        # Count entries in window
        pipe.zcard(key)
        
        # Add current request
        pipe.zadd(key, {str(now): now})
        
        # Set TTL
        pipe.expire(key, self.window_seconds)
        
        results = await pipe.execute()
        request_count = results[1]
        
        if request_count >= self.limit:
            # Remove the entry we just added
            await self.redis.zrem(key, str(now))
            return False
            
        return True
        
    async def get_remaining(self, identifier: str) -> int:
        """Get remaining requests in current window"""
        key = f"ratelimit:{identifier}"
        now = time.time()
        window_start = now - self.window_seconds
        
        # Clean and count
        await self.redis.zremrangebyscore(key, 0, window_start)
        count = await self.redis.zcard(key)
        
        return max(0, self.limit - count)
